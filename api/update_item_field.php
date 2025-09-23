<?php

/**
 * Update Item Field API
 * Handles inline editing updates for inventory items
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? '';

    // Validate required fields
    if (empty($sku)) {
        throw new Exception('SKU is required');
    }

    if (empty($field)) {
        throw new Exception('Field is required');
    }

    // Validate allowed fields for security
    $allowedFields = ['name', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
    if (!in_array($field, $allowedFields)) {
        throw new Exception('Field not allowed for inline editing');
    }

    // Validate and sanitize value based on field type
    switch ($field) {
        case 'name':
            if (empty($value)) {
                throw new Exception('Name cannot be empty');
            }
            $value = trim($value);
            break;

        case 'category':
            $value = trim($value);
            // Allow empty category (will set to NULL in database)
            break;

        case 'stockLevel':
        case 'reorderPoint':
            if (!is_numeric($value) || $value < 0) {
                throw new Exception(ucfirst($field) . ' must be a non-negative number');
            }
            $value = (int)$value;
            break;

        case 'costPrice':
        case 'retailPrice':
            if (!is_numeric($value) || $value < 0) {
                throw new Exception(ucfirst($field) . ' must be a non-negative number');
            }
            $value = round((float)$value, 2);
            break;
    }

    // Check if item exists
    $existingItem = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
    if (!$existingItem) {
        throw new Exception('Item not found');
    }

    // Update the field
    $sql = "UPDATE items SET {$field} = ? WHERE sku = ?";
    $dbValue = ($field === 'category' && empty($value)) ? null : $value;
    $result = Database::execute($sql, [$dbValue, $sku]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => ucfirst($field) . ' updated successfully',
            'field' => $field,
            'value' => $value,
            'sku' => $sku
        ]);
    } else {
        throw new Exception('Failed to update database');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
