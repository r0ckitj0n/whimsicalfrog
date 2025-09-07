<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/database_logger.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false,'error' => 'Method not allowed']);
    exit;
}

try {
    Database::getInstance();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw $e;
}

// Updated to use SKU instead of inventoryId
$sku = $_POST['sku'] ?? $_POST['inventoryId'] ?? ''; // Support both for backward compatibility
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (!$sku || !$field) {
    echo json_encode(['success' => false,'error' => 'Missing SKU or field']);
    exit;
}

try {
    // Updated field validation to match current database structure
    $allowedFields = ['name', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'price'];
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success' => false,'error' => 'Invalid field: ' . $field . '. Allowed fields: ' . implode(', ', $allowedFields)]);
        exit;
    }

    if ($value === '') {
        echo json_encode(['success' => false,'error' => 'Value cannot be empty']);
        exit;
    }

    // Validate numeric fields
    if (in_array($field, ['stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'price'])) {
        if (!is_numeric($value) || $value < 0) {
            echo json_encode(['success' => false,'error' => 'Value must be a positive number']);
            exit;
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
            null, // old quantity not available for field updates
            null, // new quantity not available for field updates
            null, // old price not available for field updates
            null  // new price not available for field updates
        );

        echo json_encode(['success' => true, 'message' => 'Field updated successfully']);
    } else {
        // Check if item exists
        $exists = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
        if ($exists) {
            echo json_encode(['success' => true,'message' => 'No change needed - ' . ucfirst($field) . ' is already set to that value']);
        } else {
            echo json_encode(['success' => false,'error' => 'Item not found with SKU: ' . $sku]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false,'error' => 'Server error: '.$e->getMessage()]);
}
?> 