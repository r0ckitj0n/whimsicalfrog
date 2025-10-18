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
        // Update items table
        Database::execute("UPDATE items SET category = ? WHERE category = ?", [$newName, $oldName]);
        // Update categories table
        Database::execute("UPDATE categories SET name = ? WHERE name = ?", [$newName, $oldName]);
        // Update sku_rules table
        Database::execute("UPDATE sku_rules SET category_name = ? WHERE category_name = ?", [$newName, $oldName]);
        Database::commit();
        Response::json(['success' => true, 'message' => 'Category name updated successfully.']);
    } catch (Exception $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }

} elseif ($action === 'update_sku_code') {
    $categoryName = $input['category_name'] ?? '';
    $newSku = $input['new_sku'] ?? '';

    if (empty($categoryName) || empty($newSku)) {
        Response::error('Category name and new SKU code are required.', null, 400);
    }

    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing rules
        Database::execute("
            INSERT INTO sku_rules (category_name, sku_prefix) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE sku_prefix = ?
        ", [$categoryName, $newSku, $newSku]);
        Response::json(['success' => true, 'message' => 'SKU code updated successfully.']);
    } catch (Exception $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }

} elseif ($action === 'add_category') {
    $name = trim((string)($input['name'] ?? ''));
    $description = isset($input['description']) ? trim((string)$input['description']) : '';

    if ($name === '') {
        Response::error('Category name is required.', null, 400);
    }

    try {
        $existing = Database::queryOne('SELECT id FROM categories WHERE name = ?', [$name]);
        if ($existing && isset($existing['id'])) {
            Response::error('Category already exists.', null, 409);
        }
        Database::execute('INSERT INTO categories (name, description) VALUES (?, ?)', [$name, $description]);
        $id = Database::lastInsertId();
        Response::json(['success' => true, 'id' => $id, 'message' => 'Category added successfully.']);
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
        } else {
            // If id provided but name not, fetch name for cleanup in sku_rules
            if ($name === '') {
                $row = Database::queryOne('SELECT name FROM categories WHERE id = ?', [$id]);
                $name = $row && isset($row['name']) ? (string)$row['name'] : '';
            }
        }

        if (!$id) {
            Database::rollBack();
            Response::notFound('Category not found.');
        }

        // Remove dependent room-category assignments
        Database::execute('DELETE FROM room_category_assignments WHERE category_id = ?', [$id]);
        // Remove SKU rule tied to category name, if present
        if ($name !== '') {
            Database::execute('DELETE FROM sku_rules WHERE category_name = ?', [$name]);
        }
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
