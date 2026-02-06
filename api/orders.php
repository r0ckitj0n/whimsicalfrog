<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/Constants.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    // Create database connection (ensures DSN/env is initialized)
    // Note: Use Database helper methods below for queries
    Database::getInstance();

    // Get filter parameters
    $filter_created_at = $_GET['filter_created_at'] ?? '';
    $filter_items = $_GET['filter_items'] ?? '';
    $filter_status = $_GET['filter_status'] ?? '';
    $filter_payment_method = $_GET['filter_payment_method'] ?? '';
    $filter_shipping_method = $_GET['filter_shipping_method'] ?? '';
    $filter_payment_status = $_GET['filter_payment_status'] ?? '';

    // Build the WHERE clause based on filters
    $where_conditions = [];
    $params = [];

    if (!empty($filter_created_at)) {
        $where_conditions[] = "DATE(o.created_at) = ?";
        $params[] = $filter_created_at;
    }

    // Apply status filter only when explicitly provided
    if (!empty($filter_status)) {
        $where_conditions[] = "o.status = ?";
        $params[] = $filter_status;
    }

    if (!empty($filter_payment_method)) {
        $where_conditions[] = "o.payment_method = ?";
        $params[] = $filter_payment_method;
    }

    if (!empty($filter_shipping_method)) {
        $where_conditions[] = "o.shipping_method = ?";
        $params[] = $filter_shipping_method;
    }

    if (!empty($filter_payment_status)) {
        $where_conditions[] = "o.payment_status = ?";
        $params[] = $filter_payment_status;
    }

    // Handle items filter - this requires a subquery since items are in order_items table
    if (!empty($filter_items)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM order_items oi LEFT JOIN items i ON oi.sku = i.sku WHERE oi.order_id = o.id AND (COALESCE(i.name, oi.sku) LIKE ? OR oi.sku LIKE ?))";
        $params[] = "%{$filter_items}%";
        $params[] = "%{$filter_items}%";
    }

    $whereClause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get orders with user information
    $orders = Database::queryAll(
        "SELECT o.*, o.total_amount AS total, u.username, u.address_line_1, u.address_line_2, u.city, u.state, u.zip_code 
         FROM orders o LEFT JOIN users u ON o.user_id = u.id OR o.user_id = u.email OR o.user_id = u.username {$whereClause} ORDER BY o.created_at DESC",
        $params
    );

    // Add item count to each order
    foreach ($orders as &$order) {
        $total_items_row = Database::queryOne("SELECT SUM(quantity) as total_items FROM order_items WHERE order_id = ?", [$order['id']]);
        $order['total_items'] = $total_items_row['total_items'] ?? 0;
    }

    // Get available options for dropdowns (use Constants or fallback to DISTINCT if needed)
    $status_options = [
        WF_Constants::ORDER_STATUS_PENDING,
        WF_Constants::ORDER_STATUS_PROCESSING,
        WF_Constants::ORDER_STATUS_SHIPPED,
        WF_Constants::ORDER_STATUS_DELIVERED,
        WF_Constants::ORDER_STATUS_CANCELLED
    ];

    $payment_status_options = [
        WF_Constants::PAYMENT_STATUS_PENDING,
        WF_Constants::PAYMENT_STATUS_PAID,
        WF_Constants::PAYMENT_STATUS_FAILED,
        WF_Constants::PAYMENT_STATUS_REFUNDED
    ];

    $payment_method_options = array_column(Database::queryAll("SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method"), 'payment_method');
    $default_payment_methods = ['Square', 'Cash', 'Check', 'Other'];
    $payment_method_options = array_unique(array_merge($payment_method_options, $default_payment_methods));
    sort($payment_method_options);

    $shipping_method_options = array_column(Database::queryAll("SELECT DISTINCT shipping_method FROM orders WHERE shipping_method IS NOT NULL AND shipping_method != '' ORDER BY shipping_method"), 'shipping_method');
    $default_shipping_methods = ['USPS', 'UPS', 'FedEx', 'Customer Pickup', 'Local Delivery'];
    $shipping_method_options = array_unique(array_merge($shipping_method_options, $default_shipping_methods));
    sort($shipping_method_options);

    // Return structured response
    Response::success([
        'orders' => $orders,
        'status_options' => $status_options,
        'payment_method_options' => $payment_method_options,
        'shipping_method_options' => $shipping_method_options,
        'payment_status_options' => $payment_status_options,
        'filters' => [
            'filter_created_at' => $filter_created_at,
            'filter_items' => $filter_items,
            'filter_status' => $filter_status,
            'filter_payment_method' => $filter_payment_method,
            'filter_shipping_method' => $filter_shipping_method,
            'filter_payment_status' => $filter_payment_status
        ]
    ]);

} catch (PDOException $e) {
    // Handle database errors
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
