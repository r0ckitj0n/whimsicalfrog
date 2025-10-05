<?php

// Include the configuration file
require_once 'config.php';
require_once __DIR__ . '/../includes/response.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    // Create database connection
    Database::getInstance();

    // Get order ID
    $orderId = $_GET['id'] ?? '';
    if (empty($orderId)) {
        Response::error('Order ID is required', null, 400);
    }

    // Get order with user information
    $order = Database::queryOne("SELECT o.*, u.username, u.email, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
                          FROM orders o 
                          JOIN users u ON o.userId = u.id 
                          WHERE o.id = ?", [$orderId]);

    if (!$order) {
        Response::notFound('Order not found');
    }

    // Get order items
    $items = Database::queryAll("SELECT oi.*, COALESCE(i.name, oi.sku) as item_name, i.retailPrice 
                          FROM order_items oi 
                          LEFT JOIN items i ON oi.sku = i.sku 
                          WHERE oi.orderId = ?", [$orderId]);

    // Return the order details
    Response::success(['order' => $order, 'items' => $items]);

} catch (PDOException $e) {
    // Handle database errors
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
