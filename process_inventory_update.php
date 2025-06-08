<?php
// Include the configuration file
require_once 'api/config.php';

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
    
    // Validate ID field
    if (!isset($data['id']) || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item ID is required']);
        exit;
    }
    
    // Validate required fields
    $requiredFields = ['itemName', 'category', 'quantity', 'unit', 'costPerUnit'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            exit;
        }
    }
    
    // Extract data
    $id = $data['id'];
    $itemName = $data['itemName'];
    $category = $data['category'];
    $quantity = floatval($data['quantity']);
    $unit = $data['unit'];
    $costPerUnit = floatval($data['costPerUnit']);
    $totalCost = floatval($data['totalCost'] ?? ($quantity * $costPerUnit));
    $notes = $data['notes'] ?? '';
    
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if item exists
    $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE id = ?');
    $checkStmt->execute([$id]);
    if ($checkStmt->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Inventory item not found']);
        exit;
    }
    
    // Update inventory item
    $stmt = $pdo->prepare('UPDATE inventory SET itemName = ?, category = ?, quantity = ?, unit = ?, costPerUnit = ?, totalCost = ?, notes = ? WHERE id = ?');
    $result = $stmt->execute([$itemName, $category, $quantity, $unit, $costPerUnit, $totalCost, $notes, $id]);
    
    if ($result) {
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Inventory item updated successfully',
            'id' => $id
        ]);
    } else {
        throw new Exception('Failed to update inventory item');
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
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
?>
