<?php

// api/category_manager.php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';
require_once dirname(__DIR__) . '/includes/response.php';

AuthHelper::requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'update_category_name') {
    $oldName = $input['old_name'] ?? '';
    $newName = $input['new_name'] ?? '';

    if (empty($oldName) || empty($newName)) {
        Response::error('Old and new category names are required.', null, 400);
    }

    try {
        Database::beginTransaction();
        // Update items table (loose coupling string match)
        Database::execute("UPDATE items SET category = ? WHERE category = ?", [$newName, $oldName]);
        // Update categories table
        Database::execute("UPDATE categories SET name = ? WHERE name = ?", [$newName, $oldName]);
        Database::commit();
        Response::json(['success' => true, 'message' => 'Category name updated successfully.']);
    } catch (Exception $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }

} elseif ($action === 'update_sku_code') {
    $categoryName = $input['category_name'] ?? '';
    $newSku = trim(strtoupper($input['new_sku'] ?? ''));

    if (empty($categoryName)) {
        Response::error('Category name is required.', null, 400);
    }

    try {
        // Update categories table directly. 
        // We use name for lookup because the frontend sends name, and name is unique.
        // If the category exists in 'categories', update it.
        $exists = Database::queryOne("SELECT id FROM categories WHERE name = ?", [$categoryName]);
        if ($exists) {
            Database::execute("UPDATE categories SET sku_rules = ? WHERE id = ?", [$newSku, $exists['id']]);
        } else {
            // If it doesn't exist (e.g. ad-hoc from items), create it now to store the rule.
            Database::execute("INSERT INTO categories (name, sku_rules) VALUES (?, ?)", [$categoryName, $newSku]);
        }
        Response::json(['success' => true, 'message' => 'SKU code updated successfully.']);
    } catch (Exception $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }

} elseif ($action === 'add_category') {
    $name = trim((string)($input['name'] ?? ''));
    $skuRules = isset($input['sku_prefix']) ? trim(strtoupper((string)$input['sku_prefix'])) : null;
    $description = isset($input['description']) ? trim((string)$input['description']) : '';

    if ($name === '') {
        Response::error('Category name is required.', null, 400);
    }
    if ($skuRules === '') $skuRules = null;
    // If no rule provided, generate default from name
    if ($skuRules === null) {
        $skuRules = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 2));
    }

    try {
        $existing = Database::queryOne('SELECT id FROM categories WHERE name = ?', [$name]);
        if ($existing && isset($existing['id'])) {
            Response::error('Category already exists.', null, 409);
        }
        
        Database::execute(
            'INSERT INTO categories (name, description, sku_rules) VALUES (?, ?, ?)', 
            [$name, $description, $skuRules]
        );
        $id = Database::lastInsertId();
        Response::json(['success' => true, 'id' => $id, 'sku_code' => $skuRules, 'message' => 'Category added successfully.']);
    } catch (Exception $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }

} elseif ($action === 'delete_category') {
    $id = isset($input['id']) ? (int)$input['id'] : null;
    $name = isset($input['name']) ? trim((string)$input['name']) : '';

    if (!$id && $name === '') {
        Response::error('Category id or name is required.', null, 400);
    }
    try {
        Database::beginTransaction();
        if (!$id) {
            $row = Database::queryOne('SELECT id FROM categories WHERE name = ?', [$name]);
            $id = $row && isset($row['id']) ? (int)$row['id'] : null;
        }

        if (!$id) {
            // If definition is missing but name is provided, check if we need to clean up items
            if ($name !== '') {
                Database::execute('UPDATE items SET category = NULL WHERE category = ?', [$name]);
                Database::commit();
                Response::json(['success' => true, 'message' => 'Category removed from items (definition was missing).']);
                return;
            }
            Database::rollBack();
            Response::notFound('Category not found.');
        }

        // Remove dependent room-category assignments
        Database::execute('DELETE FROM room_category_assignments WHERE category_id = ?', [$id]);
        
        // Detach items from this category (set to NULL) so they don't resurrect the category as ad-hoc
        Database::execute('UPDATE items SET category = NULL WHERE category = ?', [$name]);

        // Delete the category record
        $deleted = Database::execute('DELETE FROM categories WHERE id = ?', [$id]);
        Database::commit();
        if ($deleted > 0) {
            Response::json(['success' => true, 'message' => 'Category deleted successfully.']);
        } else {
            Response::notFound('Category not found.');
        }
    } catch (Exception $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }

} else {
    Response::error('Invalid action.', null, 400);
}
