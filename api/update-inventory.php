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
    
    // Create database connection using config
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Handle field updates
    if (isset($data['sku']) && isset($data['field']) && isset($data['value'])) {
        $sku = $data['sku'];
        $field = $data['field'];
        $value = $data['value'];
        
        // Validate field
        $allowedFields = ['name', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description'];
        if (!in_array($field, $allowedFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid field']);
            exit;
        }
        
        // Update the field
        $stmt = $pdo->prepare("UPDATE items SET `$field` = ? WHERE sku = ?");
        $result = $stmt->execute([$value, $sku]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => ucfirst($field) . ' updated successfully'
            ]);
        } else {
            throw new Exception('Failed to update ' . $field);
        }
    } else {
        // Handle full item updates
        $requiredFields = ['sku', 'name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                http_response_code(400);
                echo json_encode(['error' => ucfirst($field) . ' is required']);
                exit;
            }
        }
        
        $sku = $data['sku'];
        $name = $data['name'];
        $category = $data['category'] ?? '';
        $stockLevel = intval($data['stockLevel'] ?? 0);
        $reorderPoint = intval($data['reorderPoint'] ?? 5);
        $costPrice = floatval($data['costPrice'] ?? 0);
        $retailPrice = floatval($data['retailPrice'] ?? 0);
        $description = $data['description'] ?? '';
        
        // Update the item
        $stmt = $pdo->prepare('UPDATE items SET name = ?, category = ?, stockLevel = ?, reorderPoint = ?, costPrice = ?, retailPrice = ?, description = ? WHERE sku = ?');
        $result = $stmt->execute([$name, $category, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description, $sku]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Item updated successfully'
            ]);
        } else {
            throw new Exception('Failed to update item');
        }
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
