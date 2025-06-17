<?php
// Include the configuration file
require_once __DIR__ . '/config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['sku']) || empty($data['sku'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item SKU is required']);
        exit;
    }
    
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Prepare update fields
    $updateFields = [];
    $params = ['sku' => $data['sku']];
    
    // Check which fields to update
    $allowedFields = ['name', 'retailPrice', 'description', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'imageUrl'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = :$field";
            $params[$field] = $data[$field];
        }
    }
    
    // Only proceed if there are fields to update
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        exit;
    }
    
    // Build and execute the update query
    $sql = "UPDATE items SET " . implode(', ', $updateFields) . " WHERE sku = :sku";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Item updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No changes made or item not found'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update item',
            'details' => $stmt->errorInfo()
        ]);
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to update item',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
