<?php
/**
 * Global Management Helper - Logic for colors, sizes, and genders.
 */

function get_global_colors($category = '')
{
    $where = "is_active = 1";
    $params = [];
    if (!empty($category)) {
        $where .= " AND category = ?";
        $params[] = $category;
    }
    return Database::queryAll("SELECT id, color_name, color_code, category, description, display_order FROM global_colors WHERE $where ORDER BY display_order ASC, color_name ASC", $params);
}

function handle_add_global_color($data)
{
    $name = trim($data['color_name'] ?? '');
    if (empty($name)) throw new Exception('Color name required');
    Database::execute("INSERT INTO global_colors (color_name, color_code, category, description, display_order) VALUES (?, ?, ?, ?, ?)", [
        $name, $data['color_code'] ?? '', trim($data['category'] ?? 'General'), trim($data['description'] ?? ''), (int)($data['display_order'] ?? 0)
    ]);
    return ['color_id' => Database::lastInsertId()];
}

function get_global_sizes($category = '')
{
    $where = "is_active = 1";
    $params = [];
    if (!empty($category)) {
        $where .= " AND category = ?";
        $params[] = $category;
    }
    return Database::queryAll("SELECT id, size_name, size_code, category, description, display_order FROM global_sizes WHERE $where ORDER BY display_order ASC, size_name ASC", $params);
}

function handle_assign_sizes($data)
{
    $sku = $data['item_sku'] ?? '';
    $ids = $data['size_ids'] ?? [];
    if (empty($sku) || empty($ids)) throw new Exception('SKU and IDs required');
    Database::beginTransaction();
    try {
        if ($data['replace_existing'] ?? false) Database::execute("DELETE FROM item_size_assignments WHERE item_sku = ?", [$sku]);
        foreach ($ids as $id) Database::execute("INSERT IGNORE INTO item_size_assignments (item_sku, global_size_id) VALUES (?, ?)", [$sku, (int)$id]);
        Database::commit();
    } catch (Exception $e) { Database::rollBack(); throw $e; }
    return true;
}

function get_global_genders()
{
    return Database::queryAll("SELECT id, gender_name, description, display_order FROM global_genders WHERE is_active = 1 ORDER BY display_order ASC, gender_name ASC");
}
