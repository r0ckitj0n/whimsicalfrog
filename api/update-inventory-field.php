<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

$pdo = new PDO($dsn, $user, $pass, $options);

// Updated to use SKU instead of inventoryId
$sku = $_POST['sku'] ?? $_POST['inventoryId'] ?? ''; // Support both for backward compatibility
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (!$sku || !$field) {
    echo json_encode(['success'=>false,'error'=>'Missing SKU or field']);
    exit;
}

try {
    // Updated field validation to match current database structure
    $allowedFields = ['name', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'price'];
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success'=>false,'error'=>'Invalid field: ' . $field . '. Allowed fields: ' . implode(', ', $allowedFields)]);
        exit;
    }
    
    if ($value === '') {
        echo json_encode(['success'=>false,'error'=>'Value cannot be empty']);
        exit;
    }
    
    // Validate numeric fields
    if (in_array($field, ['stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'price'])) {
        if (!is_numeric($value) || $value < 0) {
            echo json_encode(['success'=>false,'error'=>'Value must be a positive number']);
            exit;
        }
    }
    
    // Update the field in items table using SKU as primary key
    $stmt = $pdo->prepare("UPDATE items SET `$field` = ? WHERE sku = ?");
    $stmt->execute([$value, $sku]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success'=>true,'message'=>ucfirst($field) . ' updated successfully']);
    } else {
        // Check if item exists
        $checkStmt = $pdo->prepare("SELECT sku FROM items WHERE sku = ?");
        $checkStmt->execute([$sku]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success'=>true,'message'=>'No change needed - ' . ucfirst($field) . ' is already set to that value']);
        } else {
            echo json_encode(['success'=>false,'error'=>'Item not found with SKU: ' . $sku]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
}
?> 