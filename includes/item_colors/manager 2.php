<?php
/**
 * Item Colors Manager Logic
 */

function get_item_colors($item_sku, $includeInactive = false)
{
    $where = "item_sku = ?";
    if (!$includeInactive) $where .= " AND is_active = 1";
    return Database::queryAll(
        "SELECT id, item_sku, color_name, color_code, image_path, stock_level, is_active, display_order
         FROM item_colors 
         WHERE $where 
         ORDER BY display_order ASC, color_name ASC",
        [$item_sku]
    );
}

function handle_add_item_color($data)
{
    $sku = $data['item_sku'] ?? '';
    $name = trim($data['color_name'] ?? '');
    if (empty($sku) || empty($name)) throw new Exception('SKU and name required');
    
    $code = $data['color_code'] ?? '';
    if (!empty($code) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $code)) throw new Exception('Invalid color code');

    Database::execute(
        "INSERT INTO item_colors (item_sku, color_name, color_code, image_path, stock_level, display_order) 
         VALUES (?, ?, ?, ?, ?, ?)",
        [$sku, $name, $code, trim($data['image_path'] ?? ''), (int)($data['stock_level'] ?? 0), (int)($data['display_order'] ?? 0)]
    );
    $id = Database::lastInsertId();
    require_once __DIR__ . '/../stock_manager.php';
    return ['color_id' => $id, 'new_total_stock' => syncTotalStockWithColors(Database::getInstance(), $sku)];
}

function handle_update_item_color($data)
{
    $id = (int)($data['color_id'] ?? 0);
    if ($id <= 0) throw new Exception('ID required');
    
    Database::execute(
        "UPDATE item_colors SET color_name=?, color_code=?, image_path=?, stock_level=?, display_order=?, is_active=? WHERE id=?",
        [$data['color_name'], $data['color_code'], $data['image_path'], $data['stock_level'], $data['display_order'], $data['is_active'], $id]
    );
    
    $row = Database::queryOne("SELECT item_sku FROM item_colors WHERE id = ?", [$id]);
    require_once __DIR__ . '/../stock_manager.php';
    return ['new_total_stock' => syncTotalStockWithColors(Database::getInstance(), $row['item_sku'])];
}
