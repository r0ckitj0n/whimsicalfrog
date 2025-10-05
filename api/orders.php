<?php

// Include the configuration file
require_once 'config.php';
require_once __DIR__ . '/../includes/response.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    // Create database connection (ensures DSN/env is initialized)
    // Note: Use Database helper methods below for queries
    Database::getInstance();

    // Get filter parameters
    $filterDate = $_GET['filter_date'] ?? '';
    $filterItems = $_GET['filter_items'] ?? '';
    $filterStatus = $_GET['filter_status'] ?? '';
    $filterPaymentMethod = $_GET['filter_payment_method'] ?? '';
    $filterShippingMethod = $_GET['filter_shipping_method'] ?? '';
    $filterPaymentStatus = $_GET['filter_payment_status'] ?? '';

    // Build the WHERE clause based on filters
    $whereConditions = [];
    $params = [];

    // Default to Processing status if no status filter is provided, but allow "All" to show everything
    if (!isset($_GET['filter_status'])) {
        // No filter parameter provided at all (first page load), default to Processing
        $defaultStatus = 'Processing';
    } else {
        // Filter parameter exists - could be empty string for "All" or specific status
        $defaultStatus = $filterStatus;
    }

    if (!empty($filterDate)) {
        $whereConditions[] = "DATE(o.date) = ?";
        $params[] = $filterDate;
    }

    // Apply status filter (defaults to Processing if not specified, but allows "All" to show everything)
    if (!empty($defaultStatus)) {
        $whereConditions[] = "o.order_status = ?";
        $params[] = $defaultStatus;
    }

    if (!empty($filterPaymentMethod)) {
        $whereConditions[] = "o.paymentMethod = ?";
        $params[] = $filterPaymentMethod;
    }

    if (!empty($filterShippingMethod)) {
        $whereConditions[] = "o.shippingMethod = ?";
        $params[] = $filterShippingMethod;
    }

    if (!empty($filterPaymentStatus)) {
        $whereConditions[] = "o.paymentStatus = ?";
        $params[] = $filterPaymentStatus;
    }

    // Handle items filter - this requires a subquery since items are in order_items table
    if (!empty($filterItems)) {
        $whereConditions[] = "EXISTS (SELECT 1 FROM order_items oi LEFT JOIN items i ON oi.sku = i.sku WHERE oi.orderId = o.id AND (COALESCE(i.name, oi.sku) LIKE ? OR oi.sku LIKE ?))";
        $params[] = "%{$filterItems}%";
        $params[] = "%{$filterItems}%";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get orders with user information
    $orders = Database::queryAll(
        "SELECT o.*, u.username, u.addressLine1, u.addressLine2, u.city, u.state, u.zipCode 
         FROM orders o JOIN users u ON o.userId = u.id {$whereClause} ORDER BY o.date DESC",
        $params
    );

    // Add item count to each order
    foreach ($orders as &$order) {
        $totalItemsRow = Database::queryOne("SELECT SUM(quantity) as total_items FROM order_items WHERE orderId = ?", [$order['id']]);
        $order['totalItems'] = $totalItemsRow['total_items'] ?? 0;
    }

    // Get unique values for filter dropdowns
    $statusOptions = array_column(Database::queryAll("SELECT DISTINCT order_status FROM orders WHERE order_status IN ('Pending','Processing','Shipped','Delivered','Cancelled') ORDER BY order_status"), 'order_status');
    $paymentMethodOptions = array_column(Database::queryAll("SELECT DISTINCT paymentMethod FROM orders WHERE paymentMethod IS NOT NULL AND paymentMethod != '' ORDER BY paymentMethod"), 'paymentMethod');
    $shippingMethodOptions = array_column(Database::queryAll("SELECT DISTINCT shippingMethod FROM orders WHERE shippingMethod IS NOT NULL AND shippingMethod != '' ORDER BY shippingMethod"), 'shippingMethod');
    $paymentStatusOptions = array_column(Database::queryAll("SELECT DISTINCT paymentStatus FROM orders WHERE paymentStatus IS NOT NULL AND paymentStatus != '' ORDER BY paymentStatus"), 'paymentStatus');

    // Return structured response
    Response::success([
        'orders' => $orders,
        'statusOptions' => $statusOptions,
        'paymentMethodOptions' => $paymentMethodOptions,
        'shippingMethodOptions' => $shippingMethodOptions,
        'paymentStatusOptions' => $paymentStatusOptions,
        'filters' => [
            'filter_date' => $filterDate,
            'filter_items' => $filterItems,
            'filter_status' => $defaultStatus,
            'filter_payment_method' => $filterPaymentMethod,
            'filter_shipping_method' => $filterShippingMethod,
            'filter_payment_status' => $filterPaymentStatus
        ]
    ]);

} catch (PDOException $e) {
    // Handle database errors
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
