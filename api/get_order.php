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
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Create database connection
    $pdo = Database::getInstance();

    // Get order ID
    $orderId = $_GET['id'] ?? '';

    if (empty($orderId)) {
        echo json_encode(['success' => false, 'error' => 'Order ID is required']);
        exit;
    }

    // Get order with user information
    $stmt = $pdo->prepare("SELECT o.*, u.username, u.email, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Get order items
    $stmt = $pdo->prepare("SELECT oi.*, COALESCE(i.name, oi.sku) as item_name, i.retailPrice 
                          FROM order_items oi 
                          LEFT JOIN items i ON oi.sku = i.sku 
                          WHERE oi.orderId = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the order details
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
