<?php

// api/category_manager.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isAdminWithToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'update_category_name') {
    $oldName = $input['old_name'] ?? '';
    $newName = $input['new_name'] ?? '';

    if (empty($oldName) || empty($newName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Old and new category names are required.']);
        exit;
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
        echo json_encode(['success' => true, 'message' => 'Category name updated successfully.']);
    } catch (Exception $e) {
        Database::rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} elseif ($action === 'update_sku_code') {
    $categoryName = $input['category_name'] ?? '';
    $newSku = $input['new_sku'] ?? '';

    if (empty($categoryName) || empty($newSku)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category name and new SKU code are required.']);
        exit;
    }

    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing rules
        Database::execute("
            INSERT INTO sku_rules (category_name, sku_prefix) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE sku_prefix = ?
        ", [$categoryName, $newSku, $newSku]);
        echo json_encode(['success' => true, 'message' => 'SKU code updated successfully.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
