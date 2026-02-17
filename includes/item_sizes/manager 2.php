<?php
/**
 * Item Sizes Manager Logic
 */

require_once __DIR__ . '/../stock_manager.php';

function get_item_sizes($item_sku, $color_id = null, $gender = null, $includeInactive = false)
{
    $where = "s.item_sku = ?";
    if (!$includeInactive) $where .= " AND s.is_active = 1";
    $params = [$item_sku];

    if ($color_id !== null) {
        if ($color_id === '0' || $color_id === 'null') {
            $where .= " AND s.color_id IS NULL";
        } else {
            $where .= " AND s.color_id = ?";
            $params[] = (int)$color_id;
        }
    }

    if ($gender !== null && $gender !== '') {
        $where .= " AND (s.gender = ? OR s.gender IS NULL)";
        $params[] = $gender;
    }

    $sql = "SELECT s.*, c.color_name, c.color_code
            FROM item_sizes s
            LEFT JOIN item_colors c ON s.color_id = c.id
            WHERE $where AND (s.color_id IS NULL OR c.is_active = 1 OR ? = 1)
            ORDER BY s.display_order ASC, s.size_name ASC";
    $params[] = $includeInactive ? 1 : 0;

    return Database::queryAll($sql, $params);
}

function handle_add_size($data)
{
    $sql = "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    Database::execute($sql, [
        $data['item_sku'], $data['color_id'] ?? null, $data['size_name'], 
        $data['size_code'], $data['initial_stock'] ?? 0, 
        $data['price_adjustment'] ?? 0.00, $data['display_order'] ?? 0
    ]);
    $sizeId = Database::lastInsertId();
    if (!empty($data['color_id'])) syncColorStockWithSizes(Database::getInstance(), $data['color_id']);
    $new_total = syncTotalStockWithSizes(Database::getInstance(), $data['item_sku']);
    return ['size_id' => $sizeId, 'new_total_stock' => $new_total];
}

function handle_update_size($data)
{
    $sql = "UPDATE item_sizes SET size_name=?, size_code=?, stock_level=?, price_adjustment=?, display_order=?, is_active=? WHERE id=?";
    Database::execute($sql, [
        $data['size_name'], $data['size_code'], $data['stock_level'], 
        $data['price_adjustment'], $data['display_order'], 
        $data['is_active'], $data['size_id']
    ]);
    $current = Database::queryOne("SELECT item_sku, color_id FROM item_sizes WHERE id = ?", [$data['size_id']]);
    if (!empty($current['color_id'])) syncColorStockWithSizes(Database::getInstance(), $current['color_id']);
    $new_total = syncTotalStockWithSizes(Database::getInstance(), $current['item_sku']);
    return ['new_total_stock' => $new_total];
}
