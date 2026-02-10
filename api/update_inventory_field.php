<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/database_logger.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

try {
    Database::getInstance();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    Response::serverError('Database connection failed', $e->getMessage());
}

// Updated to use SKU instead of inventoryId
$sku = $_POST['sku'] ?? $_POST['inventoryId'] ?? ''; // Support both for backward compatibility
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (!$sku || !$field) {
    Response::error('Missing SKU or field', null, 400);
}
$sku = trim((string)$sku);
if (preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku) !== 1) {
    Response::error('Invalid SKU format', null, 422);
}

try {
    // Updated field validation to match current database structure
    $allowedFields = ['name', 'category', 'stock_quantity', 'reorder_point', 'cost_price', 'retail_price', 'description', 'price'];
    if (!in_array($field, $allowedFields)) {
        Response::error('Invalid field: ' . $field . '. Allowed fields: ' . implode(', ', $allowedFields), null, 400);
    }

    if ($value === '') {
        Response::error('Value cannot be empty', null, 400);
    }
    if (!in_array($field, ['description'], true) && is_string($value) && strlen($value) > 255) {
        Response::error('Value too long', null, 422);
    }
    if ($field === 'description' && is_string($value) && strlen($value) > 4000) {
        Response::error('Description too long', null, 422);
    }

    // Validate numeric fields
    if (in_array($field, ['stock_quantity', 'reorder_point', 'cost_price', 'retail_price', 'price'])) {
        if (!is_numeric($value) || $value < 0) {
            Response::error('Value must be a positive number', null, 400);
        }
    }

    // Get old value for logging
    $row = Database::queryOne("SELECT `$field` as field_value FROM items WHERE sku = ?", [$sku]);
    $oldValue = $row['field_value'] ?? null;

    // Update the field in items table using SKU as primary key
    $affected = Database::execute("UPDATE items SET `$field` = ? WHERE sku = ?", [$value, $sku]);

    if ($affected > 0) {
        // Log inventory change
        $description = "Field '$field' updated to '$value'";
        DatabaseLogger::logInventoryChange(
            $sku,
            'field_update',
            $description,
            null,
            null,
            null,
            null
        );
        Response::updated(['message' => 'Field updated successfully']);
    } else {
        // Check if item exists
        $exists = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
        if ($exists) {
            Response::noChanges(['message' => 'No change needed - ' . ucfirst($field) . ' is already set to that value']);
        } else {
            Response::notFound('Item not found with SKU: ' . $sku);
        }
    }

} catch (Exception $e) {
    Response::serverError('Server error', $e->getMessage());
}
