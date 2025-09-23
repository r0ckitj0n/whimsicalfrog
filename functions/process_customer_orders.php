<?php
// Include the configuration file (absolute path)
require_once dirname(__DIR__) . '/api/config.php';

// Require admin authentication for order management
require_once dirname(__DIR__) . '/includes/auth_helper.php';
AuthHelper::requireAdmin();

// Set appropriate headers
header('Content-Type: application/json');

try {
    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if customer ID is provided
    $customerId = isset($_GET['customerId']) ? $_GET['customerId'] : null;

    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer ID is required']);
        exit;
    }

    // Fetch customer orders
    $orders = Database::queryAll('
        SELECT id, order_status, totalAmount, paymentMethod, shippingMethod, paymentStatus, 
               createdAt, shippingAddress
        FROM orders 
        WHERE userId = ? 
        ORDER BY createdAt DESC
    ', [$customerId]);

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