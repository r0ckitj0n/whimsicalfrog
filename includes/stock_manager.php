<?php
/**
 * Centralized Stock Management System for WhimsicalFrog
 * 
 * This file provides consistent stock management functions
 * across the entire application for colors, sizes, and general stock.
 */

// Ensure database connection is available
if (!isset($pdo)) {
    require_once __DIR__ . '/../api/config.php';
    $pdo = new PDO($dsn, $user, $pass, $options);
}

/**
 * Sync total stock with color quantities
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @return int|false New total stock or false on error
 */
function syncTotalStockWithColors($pdo, $itemSku) {
    try {
        // Calculate total stock from all active colors
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_level), 0) as total_color_stock
            FROM item_colors 
            WHERE item_sku = ? AND is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalColorStock = $result['total_color_stock'];
        
        // Update the main item's stock level
        $updateStmt = $pdo->prepare("UPDATE items SET stockLevel = ? WHERE sku = ?");
        $updateStmt->execute([$totalColorStock, $itemSku]);
        
        return $totalColorStock;
    } catch (Exception $e) {
        error_log("Error syncing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

/**
 * Sync color stock with its sizes
 * @param PDO $pdo Database connection
 * @param int $colorId Color ID
 * @return int|false New color stock or false on error
 */
function syncColorStockWithSizes($pdo, $colorId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock
            FROM item_sizes 
            WHERE color_id = ? AND is_active = 1
        ");
        $stmt->execute([$colorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSizeStock = $result['total_size_stock'];
        
        // Update the color's stock level
        $updateStmt = $pdo->prepare("UPDATE item_colors SET stock_level = ? WHERE id = ?");
        $updateStmt->execute([$totalSizeStock, $colorId]);
        
        return $totalSizeStock;
    } catch (Exception $e) {
        error_log("Error syncing color stock for color ID $colorId: " . $e->getMessage());
        return false;
    }
}

/**
 * Sync total item stock with all sizes
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @return int|false New total stock or false on error
 */
function syncTotalStockWithSizes($pdo, $itemSku) {
    try {
        // Calculate total stock from all active sizes
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock
            FROM item_sizes 
            WHERE item_sku = ? AND is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSizeStock = $result['total_size_stock'];
        
        // Update the main item's stock level
        $updateStmt = $pdo->prepare("UPDATE items SET stockLevel = ? WHERE sku = ?");
        $updateStmt->execute([$totalSizeStock, $itemSku]);
        
        // Also sync color stocks if there are color-specific sizes
        $colorStmt = $pdo->prepare("
            SELECT DISTINCT color_id 
            FROM item_sizes 
            WHERE item_sku = ? AND color_id IS NOT NULL
        ");
        $colorStmt->execute([$itemSku]);
        $colorIds = $colorStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($colorIds as $colorId) {
            syncColorStockWithSizes($pdo, $colorId);
        }
        
        return $totalSizeStock;
    } catch (Exception $e) {
        error_log("Error syncing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

/**
 * Reduce stock for a sale by color
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @param string $colorName Color name
 * @param int $quantity Quantity to reduce
 * @param bool $useTransaction Whether to use transaction
 * @return bool Success status
 */
function reduceStockForSaleByColor($pdo, $itemSku, $colorName, $quantity, $useTransaction = true) {
    try {
        if ($useTransaction) {
            $pdo->beginTransaction();
        }
        
        if (!empty($colorName)) {
            // Reduce color-specific stock
            $stmt = $pdo->prepare("
                UPDATE item_colors 
                SET stock_level = GREATEST(stock_level - ?, 0) 
                WHERE item_sku = ? AND color_name = ? AND is_active = 1
            ");
            $stmt->execute([$quantity, $itemSku, $colorName]);
            
            // Sync total stock with color quantities
            syncTotalStockWithColors($pdo, $itemSku);
        } else {
            // No color specified, reduce total stock only
            $stmt = $pdo->prepare("
                UPDATE items 
                SET stockLevel = GREATEST(stockLevel - ?, 0) 
                WHERE sku = ?
            ");
            $stmt->execute([$quantity, $itemSku]);
        }
        
        if ($useTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if ($useTransaction) {
            $pdo->rollBack();
        }
        error_log("Error reducing stock by color for $itemSku: " . $e->getMessage());
        return false;
    }
}

/**
 * Reduce stock for a sale by size
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @param string $sizeCode Size code
 * @param string $colorName Optional color name
 * @param int $quantity Quantity to reduce
 * @param bool $useTransaction Whether to use transaction
 * @return bool Success status
 */
function reduceStockForSaleBySize($pdo, $itemSku, $sizeCode, $quantity, $colorName = null, $useTransaction = true) {
    try {
        if ($useTransaction) {
            $pdo->beginTransaction();
        }
        
        // Get color ID if color is specified
        $colorId = null;
        if (!empty($colorName)) {
            $colorStmt = $pdo->prepare("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1");
            $colorStmt->execute([$itemSku, $colorName]);
            $colorResult = $colorStmt->fetch(PDO::FETCH_ASSOC);
            $colorId = $colorResult ? $colorResult['id'] : null;
        }
        
        // Build WHERE clause for size reduction
        $whereClause = "item_sku = ? AND size_code = ? AND is_active = 1";
        $params = [$itemSku, $sizeCode];
        
        if ($colorId) {
            $whereClause .= " AND color_id = ?";
            $params[] = $colorId;
        } else {
            $whereClause .= " AND color_id IS NULL";
        }
        
        // Reduce size-specific stock
        $stmt = $pdo->prepare("
            UPDATE item_sizes 
            SET stock_level = GREATEST(stock_level - ?, 0) 
            WHERE $whereClause
        ");
        $stmt->execute(array_merge([$quantity], $params));
        
        // Sync stock levels
        if ($colorId) {
            syncColorStockWithSizes($pdo, $colorId);
        }
        syncTotalStockWithSizes($pdo, $itemSku);
        
        if ($useTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if ($useTransaction) {
            $pdo->rollBack();
        }
        error_log("Error reducing stock by size for $itemSku: " . $e->getMessage());
        return false;
    }
}

/**
 * Reduce stock for a sale with smart prioritization
 * This function will automatically choose the best method based on available data
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @param int $quantity Quantity to reduce
 * @param string $colorName Optional color name
 * @param string $sizeCode Optional size code
 * @param bool $useTransaction Whether to use transaction
 * @return bool Success status
 */
function reduceStockForSale($pdo, $itemSku, $quantity, $colorName = null, $sizeCode = null, $useTransaction = true) {
    try {
        if ($useTransaction) {
            $pdo->beginTransaction();
        }
        
        $stockReduced = false;
        
        // Priority 1: Size-specific stock reduction (most specific)
        if (!empty($sizeCode)) {
            $stockReduced = reduceStockForSaleBySize($pdo, $itemSku, $sizeCode, $quantity, $colorName, false);
            if ($stockReduced) {
                error_log("Stock reduced by size for SKU '$itemSku', Size '$sizeCode', Color '$colorName'");
            }
        }
        
        // Priority 2: Color-specific stock reduction
        if (!$stockReduced && !empty($colorName)) {
            $stockReduced = reduceStockForSaleByColor($pdo, $itemSku, $colorName, $quantity, false);
            if ($stockReduced) {
                error_log("Stock reduced by color for SKU '$itemSku', Color '$colorName'");
            }
        }
        
        // Priority 3: General stock reduction (fallback)
        if (!$stockReduced) {
            $stmt = $pdo->prepare("UPDATE items SET stockLevel = GREATEST(stockLevel - ?, 0) WHERE sku = ?");
            $stmt->execute([$quantity, $itemSku]);
            $stockReduced = true;
            error_log("General stock reduced for SKU '$itemSku'");
        }
        
        if ($useTransaction) {
            $pdo->commit();
        }
        
        return $stockReduced;
    } catch (Exception $e) {
        if ($useTransaction) {
            $pdo->rollBack();
        }
        error_log("Error reducing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current stock level for an item
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @param string $colorName Optional color name
 * @param string $sizeCode Optional size code
 * @return int|false Stock level or false on error
 */
function getStockLevel($pdo, $itemSku, $colorName = null, $sizeCode = null) {
    try {
        // Most specific: size + color
        if (!empty($sizeCode) && !empty($colorName)) {
            $stmt = $pdo->prepare("
                SELECT s.stock_level 
                FROM item_sizes s
                JOIN item_colors c ON s.color_id = c.id
                WHERE s.item_sku = ? AND c.color_name = ? AND s.size_code = ? AND s.is_active = 1 AND c.is_active = 1
            ");
            $stmt->execute([$itemSku, $colorName, $sizeCode]);
            $result = $stmt->fetchColumn();
            if ($result !== false) return (int)$result;
        }
        
        // Size only (no color)
        if (!empty($sizeCode)) {
            $stmt = $pdo->prepare("
                SELECT stock_level 
                FROM item_sizes 
                WHERE item_sku = ? AND size_code = ? AND color_id IS NULL AND is_active = 1
            ");
            $stmt->execute([$itemSku, $sizeCode]);
            $result = $stmt->fetchColumn();
            if ($result !== false) return (int)$result;
        }
        
        // Color only
        if (!empty($colorName)) {
            $stmt = $pdo->prepare("
                SELECT stock_level 
                FROM item_colors 
                WHERE item_sku = ? AND color_name = ? AND is_active = 1
            ");
            $stmt->execute([$itemSku, $colorName]);
            $result = $stmt->fetchColumn();
            if ($result !== false) return (int)$result;
        }
        
        // General item stock
        $stmt = $pdo->prepare("SELECT stockLevel FROM items WHERE sku = ?");
        $stmt->execute([$itemSku]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (int)$result : 0;
        
    } catch (Exception $e) {
        error_log("Error getting stock level for $itemSku: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if item has sufficient stock
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @param int $requiredQuantity Required quantity
 * @param string $colorName Optional color name
 * @param string $sizeCode Optional size code
 * @return bool Whether sufficient stock is available
 */
function hasStockAvailable($pdo, $itemSku, $requiredQuantity, $colorName = null, $sizeCode = null) {
    $currentStock = getStockLevel($pdo, $itemSku, $colorName, $sizeCode);
    return $currentStock !== false && $currentStock >= $requiredQuantity;
}

/**
 * Get stock breakdown for an item (colors and sizes)
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @return array Stock breakdown
 */
function getStockBreakdown($pdo, $itemSku) {
    try {
        $breakdown = [
            'total' => 0,
            'colors' => [],
            'sizes' => [],
            'color_sizes' => []
        ];
        
        // Get total stock
        $stmt = $pdo->prepare("SELECT stockLevel FROM items WHERE sku = ?");
        $stmt->execute([$itemSku]);
        $breakdown['total'] = (int)$stmt->fetchColumn();
        
        // Get color breakdown
        $stmt = $pdo->prepare("
            SELECT color_name, stock_level 
            FROM item_colors 
            WHERE item_sku = ? AND is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $breakdown['colors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get size breakdown
        $stmt = $pdo->prepare("
            SELECT size_code, stock_level 
            FROM item_sizes 
            WHERE item_sku = ? AND color_id IS NULL AND is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $breakdown['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get color+size breakdown
        $stmt = $pdo->prepare("
            SELECT c.color_name, s.size_code, s.stock_level
            FROM item_sizes s
            JOIN item_colors c ON s.color_id = c.id
            WHERE s.item_sku = ? AND s.is_active = 1 AND c.is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $breakdown['color_sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $breakdown;
    } catch (Exception $e) {
        error_log("Error getting stock breakdown for $itemSku: " . $e->getMessage());
        return false;
    }
} 