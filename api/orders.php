<?php
// Include the configuration file
require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Create database connection using config
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Query to get all orders with basic information
    $stmt = $pdo->query('SELECT * FROM orders');
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each order, get its items
    foreach ($orders as &$order) {
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE orderId = ?');
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert shippingAddress from JSON string to array if it exists
        if (isset($order['shippingAddress']) && is_string($order['shippingAddress'])) {
            $order['shippingAddress'] = json_decode($order['shippingAddress'], true);
        }
    }
    
    // Return orders as JSON
    echo json_encode($orders);
    
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
