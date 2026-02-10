<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    // Create database connection
    Database::getInstance();

    // Get order ID
    $order_id = $_GET['id'] ?? '';
    if (empty($order_id)) {
        Response::error('Order ID is required', null, 400);
    }

    // Get order with user information (use LEFT JOIN to support guest orders or missing user profiles)
    // Preference: Use order-specific address fields if present, otherwise fall back to user profile defaults
    $order = Database::queryOne("SELECT 
                                o.*, 
                                u.username, 
                                u.email, 
                                COALESCE(NULLIF(o.address_line_1, ''), pa.address_line_1) as address_line_1,
                                COALESCE(NULLIF(o.address_line_2, ''), pa.address_line_2) as address_line_2,
                                COALESCE(NULLIF(o.city, ''), pa.city) as city,
                                COALESCE(NULLIF(o.state, ''), pa.state) as state,
                                COALESCE(NULLIF(o.zip_code, ''), pa.zip_code) as zip_code
                          FROM orders o 
                          LEFT JOIN users u ON o.user_id = u.id OR o.user_id = u.email OR o.user_id = u.username 
                          LEFT JOIN (
                              SELECT ca1.owner_id, ca1.address_line_1, ca1.address_line_2, ca1.city, ca1.state, ca1.zip_code
                              FROM addresses ca1
                              LEFT JOIN addresses ca2
                                ON ca2.owner_type = ca1.owner_type
                               AND ca2.owner_id = ca1.owner_id
                               AND (
                                    ca2.is_default > ca1.is_default
                                    OR (ca2.is_default = ca1.is_default AND ca2.id < ca1.id)
                               )
                              WHERE ca1.owner_type = 'customer' AND ca2.id IS NULL
                          ) pa ON pa.owner_id = u.id
                          WHERE o.id = ?", [$order_id]);

    if (!$order) {
        Response::notFound('Order not found');
    }

    // Get order items
    $items = Database::queryAll("SELECT oi.*, COALESCE(i.name, oi.sku) as name, oi.unit_price as price 
                          FROM order_items oi 
                          LEFT JOIN items i ON oi.sku COLLATE utf8mb4_unicode_ci = i.sku COLLATE utf8mb4_unicode_ci 
                          WHERE oi.order_id = ?", [$order_id]);

    // Get order notes
    $notes = Database::queryAll("SELECT * FROM order_notes WHERE order_id = ? ORDER BY created_at DESC", [$order_id]);

    // Return the order details
    Response::success(['order' => $order, 'items' => $items, 'notes' => $notes]);

} catch (PDOException $e) {
    // Handle database errors
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
