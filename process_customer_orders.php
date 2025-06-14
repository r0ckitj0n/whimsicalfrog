<?php
// Include the configuration file
require_once 'api/config.php';

// Set appropriate headers
header('Content-Type: application/json');

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if customer ID is provided
    $customerId = isset($_GET['customerId']) ? $_GET['customerId'] : null;
    
    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer ID is required']);
        exit;
    }
    
    // Fetch customer orders
    $stmt = $pdo->prepare('
        SELECT id, status, totalAmount, paymentMethod, shippingMethod, paymentStatus, 
               createdAt, shippingAddress
        FROM orders 
        WHERE userId = ? 
        ORDER BY createdAt DESC
    ');
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
?> 