<?php
// Include the configuration file
require_once 'config.php';

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
    if (!isset($data['orderId']) || empty($data['orderId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID is required']);
        exit;
    }
    
    if (!isset($data['newStatus']) || empty($data['newStatus'])) {
        http_response_code(400);
        echo json_encode(['error' => 'New payment status is required']);
        exit;
    }
    
    $orderId = $data['orderId'];
    $newStatus = $data['newStatus'];
    
    // Validate status value
    $allowedStatuses = ['Pending', 'Processing', 'Received', 'Refunded', 'Failed'];
    if (!in_array($newStatus, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid payment status',
            'allowedValues' => $allowedStatuses
        ]);
        exit;
    }
    
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if order exists
    $checkStmt = $pdo->prepare('SELECT id FROM orders WHERE id = ?');
    $checkStmt->execute([$orderId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    // Update payment status
    $stmt = $pdo->prepare('UPDATE orders SET paymentStatus = ? WHERE id = ?');
    $result = $stmt->execute([$newStatus, $orderId]);
    
    if ($result) {
        // Success response
        echo json_encode([
            'success' => true,
            'message' => 'Payment status updated successfully',
            'orderId' => $orderId,
            'newStatus' => $newStatus
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update payment status']);
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
