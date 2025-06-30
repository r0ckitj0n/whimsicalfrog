<?php
// Include the configuration file
require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Allow both DELETE and GET methods (GET with a parameter for compatibility with browsers)
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get customer ID from URL parameter
    $customerId = $_GET['id'] ?? null;
    
    // If no ID provided, check request body (for DELETE requests with body)
    if (!$customerId && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $customerId = $data['id'] ?? null;
    }
    
    // Validate customer ID
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer ID is required']);
        exit;
    }
    
    // Create database connection using config
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // First check if the customer exists
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $checkStmt->execute([$customerId]);
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Customer not found']);
        exit;
    }
    
    // Delete the customer
    $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $deleteStmt->execute([$customerId]);
    
    // Check if deletion was successful
    if ($deleteStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete customer']);
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
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
