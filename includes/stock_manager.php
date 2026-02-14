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
 * @param string $item_sku Item SKU
 * @return int|false New total stock or false on error
 */
function syncTotalStockWithColors($pdo, $item_sku)
{
    try {
        // Master stock mode: items.stock_quantity is manually managed and must not be overwritten
        // by summing variant rows.
        $row = Database::queryOne("SELECT COALESCE(stock_quantity, 0) AS stock_quantity FROM items WHERE sku = ? LIMIT 1", [$item_sku]);
        return (int)($row['stock_quantity'] ?? 0);
    } catch (Exception $e) {
        error_log("Error syncing stock for $item_sku: " . $e->getMessage());
        return false;
    }
}

/**
 * Sync color stock with its sizes
 * @param PDO $pdo Database connection
 * @param int $color_id Color ID
 * @return int|false New color stock or false on error
 */
function syncColorStockWithSizes($pdo, $color_id)
{
    try {
        $result = Database::queryOne("\n            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock\n            FROM item_sizes \n            WHERE color_id = ? AND is_active = 1\n        ", [$color_id]);
        $totalSizeStock = (int)($result['total_size_stock'] ?? 0);

        // Update the color's stock level
        Database::execute("UPDATE item_colors SET stock_level = ? WHERE id = ?", [$totalSizeStock, $color_id]);

        return $totalSizeStock;
    } catch (Exception $e) {
        Logger::error('Color stock sync failed', ['color_id' => $color_id, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}

/**
 * Sync total item stock with all sizes
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @return int|false New total stock or false on error
 */
function syncTotalStockWithSizes($pdo, $item_sku)
{
    try {
        // Master stock mode: items.stock_quantity is manually managed and must not be overwritten
        // by summing variant rows.
        $row = Database::queryOne("SELECT COALESCE(stock_quantity, 0) AS stock_quantity FROM items WHERE sku = ? LIMIT 1", [$item_sku]);
        $master = (int)($row['stock_quantity'] ?? 0);

        // Still keep color rollups consistent for admin tooling.
        $rows = Database::queryAll("\n            SELECT DISTINCT color_id \n            FROM item_sizes \n            WHERE item_sku = ? AND color_id IS NOT NULL\n        ", [$item_sku]);
        $colorIds = array_map(function ($r) { return $r['color_id']; }, $rows);

        foreach ($colorIds as $color_id) {
            syncColorStockWithSizes($pdo, $color_id);
        }

        return $master;
    } catch (Exception $e) {
        error_log("Error syncing stock for $item_sku: " . $e->getMessage());
        return false;
    }
}

/**
 * Reduce stock for a sale by color
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @param string $colorName Color name
 * @param int $quantity Quantity to reduce
 * @param bool $useTransaction Whether to use transaction
 * @return bool Success status
 */
function reduceStockForSaleByColor($pdo, $item_sku, $colorName, $quantity, $useTransaction = true)
{
    try {
        if ($useTransaction) {
            Database::beginTransaction();
        }

        if (!empty($colorName)) {
            // Reduce color-specific stock
            Database::execute("\n                UPDATE item_colors \n                SET stock_level = GREATEST(stock_level - ?, 0) \n                WHERE item_sku = ? AND color_name = ? AND is_active = 1\n            ", [$quantity, $item_sku, $colorName]);

            // Sync total stock with color quantities
            syncTotalStockWithColors($pdo, $item_sku);
        } else {
            // No color specified, reduce total stock only
            Database::execute("\n                UPDATE items \n                SET stock_quantity = GREATEST(stock_quantity - ?, 0) \n                WHERE sku = ?\n            ", [$quantity, $item_sku]);
        }

        if ($useTransaction) {
            Database::commit();
        }
        return true;
    } catch (Exception $e) {
        if ($useTransaction) {
            Database::rollBack();
        }
        Logger::error('Stock reduction by color failed', ['item_sku' => $item_sku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}

/**
 * Reduce stock for a sale by size
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @param string $sizeCode Size code
 * @param string $colorName Optional color name
 * @param int $quantity Quantity to reduce
 * @param bool $useTransaction Whether to use transaction
 * @return bool Success status
 */
function reduceStockForSaleBySize($pdo, $item_sku, $sizeCode, $quantity, $colorName = null, $useTransaction = true)
{
    try {
        if ($useTransaction) {
            Database::beginTransaction();
        }

        // Get color ID if color is specified
        $color_id = null;
        if (!empty($colorName)) {
            $row = Database::queryOne("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1", [$item_sku, $colorName]);
            $color_id = $row ? $row['id'] : null;
        }

        // Build WHERE clause for size reduction
        $whereClause = "item_sku = ? AND size_code = ? AND is_active = 1";
        $params = [$item_sku, $sizeCode];

        if ($color_id) {
            $whereClause .= " AND color_id = ?";
            $params[] = $color_id;
        } else {
            $whereClause .= " AND color_id IS NULL";
        }

        // Reduce size-specific stock
        Database::execute("\n            UPDATE item_sizes \n            SET stock_level = GREATEST(stock_level - ?, 0) \n            WHERE $whereClause\n        ", array_merge([$quantity], $params));

        // Sync stock levels
        if ($color_id) {
            syncColorStockWithSizes($pdo, $color_id);
        }
        syncTotalStockWithSizes($pdo, $item_sku);

        if ($useTransaction) {
            Database::commit();
        }
        return true;
    } catch (Exception $e) {
        if ($useTransaction) {
            Database::rollBack();
        }
        Logger::error('Stock reduction by size failed', ['item_sku' => $item_sku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}

/**
 * Reduce stock for a sale with smart prioritization
 * This function will automatically choose the best method based on available data
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @param int $quantity Quantity to reduce
 * @param string $colorName Optional color name
 * @param string $sizeCode Optional size code
 * @param bool $useTransaction Whether to use transaction
 * @return bool Success status
 */
function reduceStockForSale($pdo, $item_sku, $quantity, $colorName = null, $sizeCode = null, $useTransaction = true)
{
    try {
        if ($useTransaction) {
            Database::beginTransaction();
        }

        $stock_reduced = false;

        // Priority 1: Size-specific stock reduction (most specific)
        if (!empty($sizeCode)) {
            $stock_reduced = reduceStockForSaleBySize($pdo, $item_sku, $sizeCode, $quantity, $colorName, false);
            if ($stock_reduced) {
                Logger::info('Stock reduced by size', ['item_sku' => $item_sku, 'size_code' => $sizeCode, 'color_name' => $colorName, 'context' => 'stock_manager']);
            }
        }

        // Priority 2: Color-specific stock reduction
        if (!$stock_reduced && !empty($colorName)) {
            $stock_reduced = reduceStockForSaleByColor($pdo, $item_sku, $colorName, $quantity, false);
            if ($stock_reduced) {
                Logger::info('Stock reduced by color', ['item_sku' => $item_sku, 'color_name' => $colorName, 'context' => 'stock_manager']);
            }
        }

        // Priority 3: General stock reduction (fallback)
        if (!$stock_reduced) {
            Database::execute("UPDATE items SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE sku = ?", [$quantity, $item_sku]);
            $stock_reduced = true;
            Logger::info('General stock reduced', ['item_sku' => $item_sku, 'context' => 'stock_manager']);
        }

        if ($useTransaction) {
            Database::commit();
        }

        return $stock_reduced;
    } catch (Exception $e) {
        if ($useTransaction) {
            Database::rollBack();
        }
        Logger::error('Stock reduction failed', ['item_sku' => $item_sku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}

/**
 * Get current stock level for an item
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @param string $colorName Optional color name
 * @param string $sizeCode Optional size code
 * @return int|false Stock level or false on error
 */
function getStockLevel($pdo, $item_sku, $colorName = null, $sizeCode = null)
{
    try {
        // Master stock mode: treat items.stock_quantity as the single source of truth for selling.
        // Variant (color/size/gender) stock is presentation-only in this mode.
        $row = Database::queryOne("SELECT stock_quantity FROM items WHERE sku = ?", [$item_sku]);
        $result = $row !== null ? $row['stock_quantity'] : false;
        return $result !== false ? (int)$result : 0;

    } catch (Exception $e) {
        Logger::error('Error getting stock level', ['item_sku' => $item_sku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}

/**
 * Check if item has sufficient stock
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @param int $requiredQuantity Required quantity
 * @param string $colorName Optional color name
 * @param string $sizeCode Optional size code
 * @return bool Whether sufficient stock is available
 */
function hasStockAvailable($pdo, $item_sku, $requiredQuantity, $colorName = null, $sizeCode = null)
{
    $currentStock = getStockLevel($pdo, $item_sku, $colorName, $sizeCode);
    return $currentStock !== false && $currentStock >= $requiredQuantity;
}

/**
 * Get stock breakdown for an item (colors and sizes)
 * @param PDO $pdo Database connection
 * @param string $item_sku Item SKU
 * @return array Stock breakdown
 */
function getStockBreakdown($pdo, $item_sku)
{
    try {
        $breakdown = [
            'total' => 0,
            'colors' => [],
            'sizes' => [],
            'color_sizes' => []
        ];

        // Get total stock
        $row = Database::queryOne("SELECT stock_quantity FROM items WHERE sku = ?", [$item_sku]);
        $breakdown['total'] = (int)($row['stock_quantity'] ?? 0);

        // Get color breakdown
        $breakdown['colors'] = Database::queryAll("\n            SELECT color_name, stock_level \n            FROM item_colors \n            WHERE item_sku = ? AND is_active = 1\n        ", [$item_sku]);

        // Get size breakdown
        $breakdown['sizes'] = Database::queryAll("\n            SELECT size_code, stock_level \n            FROM item_sizes \n            WHERE item_sku = ? AND color_id IS NULL AND is_active = 1\n        ", [$item_sku]);

        // Get color+size breakdown
        $breakdown['color_sizes'] = Database::queryAll("\n            SELECT c.color_name, s.size_code, s.stock_level\n            FROM item_sizes s\n            JOIN item_colors c ON s.color_id = c.id\n            WHERE s.item_sku = ? AND s.is_active = 1 AND c.is_active = 1\n        ", [$item_sku]);

        return $breakdown;
    } catch (Exception $e) {
        Logger::error('Error getting stock breakdown', ['item_sku' => $item_sku, 'error' => $e->getMessage(), 'context' => 'stock_manager']);
        return false;
    }
}
