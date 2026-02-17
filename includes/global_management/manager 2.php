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
    return Database::queryAll("SELECT id, color_name, color_code, category, description, display_order, is_active FROM global_colors WHERE $where ORDER BY display_order ASC, color_name ASC", $params);
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

function handle_update_global_color($data)
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new Exception('Color ID required');

    $existing = Database::queryOne("SELECT id FROM global_colors WHERE id = ? LIMIT 1", [$id]);
    if (!$existing) throw new Exception('Color not found');

    // Allow partial updates; only update provided keys.
    $fields = [];
    $params = [];

    if (array_key_exists('color_name', $data)) {
        $name = trim((string)($data['color_name'] ?? ''));
        if ($name === '') throw new Exception('Color name cannot be empty');
        $fields[] = "color_name = ?";
        $params[] = $name;
    }
    if (array_key_exists('color_code', $data)) {
        $code = trim((string)($data['color_code'] ?? ''));
        $fields[] = "color_code = ?";
        $params[] = $code;
    }
    if (array_key_exists('category', $data)) {
        $fields[] = "category = ?";
        $params[] = trim((string)($data['category'] ?? 'General'));
    }
    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $params[] = trim((string)($data['description'] ?? ''));
    }
    if (array_key_exists('display_order', $data)) {
        $fields[] = "display_order = ?";
        $params[] = (int)($data['display_order'] ?? 0);
    }
    if (array_key_exists('is_active', $data)) {
        $fields[] = "is_active = ?";
        $params[] = (int)(!!$data['is_active']);
    }

    if (empty($fields)) {
        return ['updated' => 0];
    }

    $params[] = $id;
    $sql = "UPDATE global_colors SET " . implode(', ', $fields) . " WHERE id = ?";
    $affected = Database::execute($sql, $params);
    return ['updated' => $affected];
}

function handle_delete_global_color($data)
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new Exception('Color ID required');
    $affected = Database::execute("UPDATE global_colors SET is_active = 0 WHERE id = ?", [$id]);
    return ['deleted' => $affected];
}

function get_global_sizes($category = '')
{
    $where = "is_active = 1";
    $params = [];
    if (!empty($category)) {
        $where .= " AND category = ?";
        $params[] = $category;
    }
    return Database::queryAll("SELECT id, size_name, size_code, category, description, display_order, is_active FROM global_sizes WHERE $where ORDER BY display_order ASC, size_name ASC", $params);
}

function handle_add_global_size($data)
{
    $name = trim((string)($data['size_name'] ?? ''));
    $code = trim((string)($data['size_code'] ?? ''));
    if ($name === '' || $code === '') throw new Exception('Size name and code required');
    Database::execute("INSERT INTO global_sizes (size_name, size_code, category, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)", [
        $name,
        $code,
        trim((string)($data['category'] ?? 'General')),
        trim((string)($data['description'] ?? '')),
        (int)($data['display_order'] ?? 0),
    ]);
    return ['size_id' => Database::lastInsertId()];
}

function handle_update_global_size($data)
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new Exception('Size ID required');
    $existing = Database::queryOne("SELECT id FROM global_sizes WHERE id = ? LIMIT 1", [$id]);
    if (!$existing) throw new Exception('Size not found');

    $fields = [];
    $params = [];

    if (array_key_exists('size_name', $data)) {
        $name = trim((string)($data['size_name'] ?? ''));
        if ($name === '') throw new Exception('Size name cannot be empty');
        $fields[] = "size_name = ?";
        $params[] = $name;
    }
    if (array_key_exists('size_code', $data)) {
        $code = trim((string)($data['size_code'] ?? ''));
        if ($code === '') throw new Exception('Size code cannot be empty');
        $fields[] = "size_code = ?";
        $params[] = $code;
    }
    if (array_key_exists('category', $data)) {
        $fields[] = "category = ?";
        $params[] = trim((string)($data['category'] ?? 'General'));
    }
    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $params[] = trim((string)($data['description'] ?? ''));
    }
    if (array_key_exists('display_order', $data)) {
        $fields[] = "display_order = ?";
        $params[] = (int)($data['display_order'] ?? 0);
    }
    if (array_key_exists('is_active', $data)) {
        $fields[] = "is_active = ?";
        $params[] = (int)(!!$data['is_active']);
    }

    if (empty($fields)) return ['updated' => 0];
    $params[] = $id;
    $sql = "UPDATE global_sizes SET " . implode(', ', $fields) . " WHERE id = ?";
    $affected = Database::execute($sql, $params);
    return ['updated' => $affected];
}

function handle_delete_global_size($data)
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new Exception('Size ID required');
    $affected = Database::execute("UPDATE global_sizes SET is_active = 0 WHERE id = ?", [$id]);
    return ['deleted' => $affected];
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
    return Database::queryAll("SELECT id, gender_name, description, display_order, is_active FROM global_genders WHERE is_active = 1 ORDER BY display_order ASC, gender_name ASC");
}

function handle_add_global_gender($data)
{
    $name = trim((string)($data['gender_name'] ?? ''));
    if ($name === '') throw new Exception('Gender name required');
    Database::execute("INSERT INTO global_genders (gender_name, description, display_order, is_active) VALUES (?, ?, ?, 1)", [
        $name,
        trim((string)($data['description'] ?? '')),
        (int)($data['display_order'] ?? 0),
    ]);
    return ['gender_id' => Database::lastInsertId()];
}

function handle_update_global_gender($data)
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new Exception('Gender ID required');
    $existing = Database::queryOne("SELECT id FROM global_genders WHERE id = ? LIMIT 1", [$id]);
    if (!$existing) throw new Exception('Gender not found');

    $fields = [];
    $params = [];

    if (array_key_exists('gender_name', $data)) {
        $name = trim((string)($data['gender_name'] ?? ''));
        if ($name === '') throw new Exception('Gender name cannot be empty');
        $fields[] = "gender_name = ?";
        $params[] = $name;
    }
    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $params[] = trim((string)($data['description'] ?? ''));
    }
    if (array_key_exists('display_order', $data)) {
        $fields[] = "display_order = ?";
        $params[] = (int)($data['display_order'] ?? 0);
    }
    if (array_key_exists('is_active', $data)) {
        $fields[] = "is_active = ?";
        $params[] = (int)(!!$data['is_active']);
    }

    if (empty($fields)) return ['updated' => 0];
    $params[] = $id;
    $sql = "UPDATE global_genders SET " . implode(', ', $fields) . " WHERE id = ?";
    $affected = Database::execute($sql, $params);
    return ['updated' => $affected];
}

function handle_delete_global_gender($data)
{
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new Exception('Gender ID required');
    $affected = Database::execute("UPDATE global_genders SET is_active = 0 WHERE id = ?", [$id]);
    return ['deleted' => $affected];
}
