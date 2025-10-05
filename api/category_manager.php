<?php

// api/category_manager.php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';

if (!isAdminWithToken()) {
    Response::forbidden('Authentication required.');
}

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

} else {
    Response::error('Invalid action.', null, 400);
}
