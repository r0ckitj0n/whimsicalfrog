<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

$pdo = new PDO($dsn, $user, $pass, $options);
$inventoryId = $_POST['inventoryId'] ?? '';
$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (!$inventoryId || !$field) {
    echo json_encode(['success'=>false,'error'=>'Missing inventory ID or field']);
    exit;
}

try {
    // Validate field and value
    $allowedFields = ['name', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success'=>false,'error'=>'Invalid field']);
        exit;
    }
    
    if ($value === '') {
        echo json_encode(['success'=>false,'error'=>'Value cannot be empty']);
        exit;
    }
    
    // Validate numeric fields
    if (in_array($field, ['stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'])) {
        if (!is_numeric($value) || $value < 0) {
            echo json_encode(['success'=>false,'error'=>'Value must be a positive number']);
            exit;
        }
    }
    
    // Update the field in items table
    $stmt = $pdo->prepare("UPDATE items SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $inventoryId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success'=>true,'message'=>ucfirst($field) . ' updated successfully']);
    } else {
        // Check if item exists
        $checkStmt = $pdo->prepare("SELECT id FROM items WHERE id = ?");
        $checkStmt->execute([$inventoryId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success'=>true,'message'=>'No change needed - ' . ucfirst($field) . ' is already set to that value']);
        } else {
            echo json_encode(['success'=>false,'error'=>'Item not found']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
}
?> 