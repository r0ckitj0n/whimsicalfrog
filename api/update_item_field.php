<?php

/**
 * Update Item Field API
 * Handles inline editing updates for inventory items
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

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

    // If updating stockLevel, ensure no options/dimensions exist or are enabled
    if ($field === 'stockLevel') {
        // Check option settings
        $settings = Database::queryOne("SELECT enabled_dimensions FROM item_option_settings WHERE item_sku = ?", [$sku]);
        $enabledDims = [];
        if ($settings && !empty($settings['enabled_dimensions'])) {
            $decoded = json_decode($settings['enabled_dimensions'], true);
            if (is_array($decoded)) { $enabledDims = $decoded; }
        }
        $hasEnabledDims = !empty(array_intersect($enabledDims, ['gender','size','color']));
        // Check presence of option rows
        $hasColorsRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM item_colors WHERE item_sku = ? AND is_active = 1", [$sku]);
        $hasSizesRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM item_sizes WHERE item_sku = ? AND is_active = 1", [$sku]);
        $hasGendersRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM item_genders WHERE item_sku = ?", [$sku]);
        $hasColors = (int)($hasColorsRow['cnt'] ?? 0) > 0;
        $hasSizes = (int)($hasSizesRow['cnt'] ?? 0) > 0;
        $hasGenders = (int)($hasGendersRow['cnt'] ?? 0) > 0;
        if ($hasEnabledDims || $hasColors || $hasSizes || $hasGenders) {
            throw new Exception('This item has options (gender/size/color). Edit stock via the item editor.');
        }
    }

    // Update the field
    $sql = "UPDATE items SET {$field} = ? WHERE sku = ?";
    $dbValue = ($field === 'category' && empty($value)) ? null : $value;
    $affected = Database::execute($sql, [$dbValue, $sku]);

    if ($affected > 0) {
        Response::updated(['field' => $field, 'value' => $value, 'sku' => $sku]);
    } else {
        // No rows updated could mean no change; verify item exists
        $exists = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
        if ($exists) {
            Response::noChanges(['field' => $field, 'value' => $value, 'sku' => $sku]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Item not found']);
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
