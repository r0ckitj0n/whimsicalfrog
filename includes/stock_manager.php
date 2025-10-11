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
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        Logger::error('Database connection failed', ['error' => $e->getMessage(), 'context' => 'stock_manager']);
        throw $e;
    }
}

/**
 * Sync total stock with color quantities
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @return int|false New total stock or false on error
 */
function syncTotalStockWithColors($pdo, $itemSku)
{
    try {
        // Calculate total stock from all active colors
        $result = Database::queryOne("\n            SELECT COALESCE(SUM(stock_level), 0) as total_color_stock\n            FROM item_colors \n            WHERE item_sku = ? AND is_active = 1\n        ", [$itemSku]);
        $totalColorStock = (int)($result['total_color_stock'] ?? 0);

        // Update the main item's stock level
        Database::execute("UPDATE items SET stockLevel = ? WHERE sku = ?", [$totalColorStock, $itemSku]);

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
function syncColorStockWithSizes($pdo, $colorId)
{
    try {
        $result = Database::queryOne("\n            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock\n            FROM item_sizes \n            WHERE color_id = ? AND is_active = 1\n        ", [$colorId]);
        $totalSizeStock = (int)($result['total_size_stock'] ?? 0);

        // Update the color's stock level
        Database::execute("UPDATE item_colors SET stock_level = ? WHERE id = ?", [$totalSizeStock, $colorId]);

        return $totalSizeStock;
    } catch (Exception $e) {
        Logger::error('Color stock sync failed', ['color_id' => $colorId, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}

/**
 * Sync total item stock with all sizes
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @return int|false New total stock or false on error
 */
function syncTotalStockWithSizes($pdo, $itemSku)
{
    try {
        // Calculate total stock from all active sizes
        $result = Database::queryOne("\n            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock\n            FROM item_sizes \n            WHERE item_sku = ? AND is_active = 1\n        ", [$itemSku]);
        $totalSizeStock = (int)($result['total_size_stock'] ?? 0);

        // Update the main item's stock level
        Database::execute("UPDATE items SET stockLevel = ? WHERE sku = ?", [$totalSizeStock, $itemSku]);

        // Also sync color stocks if there are color-specific sizes
        $rows = Database::queryAll("\n            SELECT DISTINCT color_id \n            FROM item_sizes \n            WHERE item_sku = ? AND color_id IS NOT NULL\n        ", [$itemSku]);
        $colorIds = array_map(function ($r) { return $r['color_id']; }, $rows);

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
function reduceStockForSaleByColor($pdo, $itemSku, $colorName, $quantity, $useTransaction = true)
{
    try {
        if ($useTransaction) {
            Database::beginTransaction();
        }

        if (!empty($colorName)) {
            // Reduce color-specific stock
            Database::execute("\n                UPDATE item_colors \n                SET stock_level = GREATEST(stock_level - ?, 0) \n                WHERE item_sku = ? AND color_name = ? AND is_active = 1\n            ", [$quantity, $itemSku, $colorName]);

            // Sync total stock with color quantities
            syncTotalStockWithColors($pdo, $itemSku);
        } else {
            // No color specified, reduce total stock only
            Database::execute("\n                UPDATE items \n                SET stockLevel = GREATEST(stockLevel - ?, 0) \n                WHERE sku = ?\n            ", [$quantity, $itemSku]);
        }

        if ($useTransaction) {
            Database::commit();
        }
        return true;
    } catch (Exception $e) {
        if ($useTransaction) {
            Database::rollBack();
        }
        Logger::error('Stock reduction by color failed', ['item_sku' => $itemSku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
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
function reduceStockForSaleBySize($pdo, $itemSku, $sizeCode, $quantity, $colorName = null, $useTransaction = true)
{
    try {
        if ($useTransaction) {
            Database::beginTransaction();
        }

        // Get color ID if color is specified
        $colorId = null;
        if (!empty($colorName)) {
            $row = Database::queryOne("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1", [$itemSku, $colorName]);
            $colorId = $row ? $row['id'] : null;
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
        Database::execute("\n            UPDATE item_sizes \n            SET stock_level = GREATEST(stock_level - ?, 0) \n            WHERE $whereClause\n        ", array_merge([$quantity], $params));

        // Sync stock levels
        if ($colorId) {
            syncColorStockWithSizes($pdo, $colorId);
        }
        syncTotalStockWithSizes($pdo, $itemSku);

        if ($useTransaction) {
            Database::commit();
        }
        return true;
    } catch (Exception $e) {
        if ($useTransaction) {
            Database::rollBack();
        }
        Logger::error('Stock reduction by size failed', ['item_sku' => $itemSku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
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
function reduceStockForSale($pdo, $itemSku, $quantity, $colorName = null, $sizeCode = null, $useTransaction = true)
{
    try {
        if ($useTransaction) {
            Database::beginTransaction();
        }

        $stockReduced = false;

        // Priority 1: Size-specific stock reduction (most specific)
        if (!empty($sizeCode)) {
            $stockReduced = reduceStockForSaleBySize($pdo, $itemSku, $sizeCode, $quantity, $colorName, false);
            if ($stockReduced) {
                Logger::info('Stock reduced by size', ['item_sku' => $itemSku, 'size_code' => $sizeCode, 'color_name' => $colorName, 'context' => 'stock_manager']);
            }
        }

        // Priority 2: Color-specific stock reduction
        if (!$stockReduced && !empty($colorName)) {
            $stockReduced = reduceStockForSaleByColor($pdo, $itemSku, $colorName, $quantity, false);
            if ($stockReduced) {
                Logger::info('Stock reduced by color', ['item_sku' => $itemSku, 'color_name' => $colorName, 'context' => 'stock_manager']);
            }
        }

        // Priority 3: General stock reduction (fallback)
        if (!$stockReduced) {
            Database::execute("UPDATE items SET stockLevel = GREATEST(stockLevel - ?, 0) WHERE sku = ?", [$quantity, $itemSku]);
            $stockReduced = true;
            Logger::info('General stock reduced', ['item_sku' => $itemSku, 'context' => 'stock_manager']);
        }

        if ($useTransaction) {
            Database::commit();
        }

        return $stockReduced;
    } catch (Exception $e) {
        if ($useTransaction) {
            Database::rollBack();
        }
        Logger::error('Stock reduction failed', ['item_sku' => $itemSku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
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
function getStockLevel($pdo, $itemSku, $colorName = null, $sizeCode = null)
{
    try {
        // Most specific: size + color
        if (!empty($sizeCode) && !empty($colorName)) {
            $row = Database::queryOne("\n                SELECT s.stock_level \n                FROM item_sizes s\n                JOIN item_colors c ON s.color_id = c.id\n                WHERE s.item_sku = ? AND c.color_name = ? AND s.size_code = ? AND s.is_active = 1 AND c.is_active = 1\n            ", [$itemSku, $colorName, $sizeCode]);
            $result = $row !== null ? $row['stock_level'] : false;
            if ($result !== false) {
                return (int)$result;
            }
        }

        // Size only (no color)
        if (!empty($sizeCode)) {
            $row = Database::queryOne("\n                SELECT stock_level \n                FROM item_sizes \n                WHERE item_sku = ? AND size_code = ? AND color_id IS NULL AND is_active = 1\n            ", [$itemSku, $sizeCode]);
            $result = $row !== null ? $row['stock_level'] : false;
            if ($result !== false) {
                return (int)$result;
            }
        }

        // Color only
        if (!empty($colorName)) {
            $row = Database::queryOne("\n                SELECT stock_level \n                FROM item_colors \n                WHERE item_sku = ? AND color_name = ? AND is_active = 1\n            ", [$itemSku, $colorName]);
            $result = $row !== null ? $row['stock_level'] : false;
            if ($result !== false) {
                return (int)$result;
            }
        }

        // Aggregated stock across sizes (active rows only)
        $agg = Database::queryOne(
            "SELECT COALESCE(SUM(stock_level), 0) AS total FROM item_sizes WHERE item_sku = ? AND is_active = 1",
            [$itemSku]
        );
        if ($agg && isset($agg['total'])) {
            $total = (int)$agg['total'];
            // Prefer aggregate when it shows real availability; otherwise defer to legacy
            if ($total > 0) {
                return $total;
            }
        }

        // General item stock fallback
        $row = Database::queryOne("SELECT stockLevel FROM items WHERE sku = ?", [$itemSku]);
        $result = $row !== null ? $row['stockLevel'] : false;
        return $result !== false ? (int)$result : 0;

    } catch (Exception $e) {
        Logger::error('Error getting stock level', ['item_sku' => $itemSku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
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
function hasStockAvailable($pdo, $itemSku, $requiredQuantity, $colorName = null, $sizeCode = null)
{
    $currentStock = getStockLevel($pdo, $itemSku, $colorName, $sizeCode);
    return $currentStock !== false && $currentStock >= $requiredQuantity;
}

/**
 * Get stock breakdown for an item (colors and sizes)
 * @param PDO $pdo Database connection
 * @param string $itemSku Item SKU
 * @return array Stock breakdown
 */
function getStockBreakdown($pdo, $itemSku)
{
    try {
        $breakdown = [
            'total' => 0,
            'colors' => [],
            'sizes' => [],
            'color_sizes' => []
        ];

        // Get total stock
        $row = Database::queryOne("SELECT stockLevel FROM items WHERE sku = ?", [$itemSku]);
        $breakdown['total'] = (int)($row['stockLevel'] ?? 0);

        // Get color breakdown
        $breakdown['colors'] = Database::queryAll("\n            SELECT color_name, stock_level \n            FROM item_colors \n            WHERE item_sku = ? AND is_active = 1\n        ", [$itemSku]);

        // Get size breakdown
        $breakdown['sizes'] = Database::queryAll("\n            SELECT size_code, stock_level \n            FROM item_sizes \n            WHERE item_sku = ? AND color_id IS NULL AND is_active = 1\n        ", [$itemSku]);

        // Get color+size breakdown
        $breakdown['color_sizes'] = Database::queryAll("\n            SELECT c.color_name, s.size_code, s.stock_level\n            FROM item_sizes s\n            JOIN item_colors c ON s.color_id = c.id\n            WHERE s.item_sku = ? AND s.is_active = 1 AND c.is_active = 1\n        ", [$itemSku]);

        return $breakdown;
    } catch (Exception $e) {
        Logger::error('Error getting stock breakdown', ['item_sku' => $itemSku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}
