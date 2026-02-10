<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

try {
    // Get POST data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON body', null, 400);
    }

    // Validate required fields
    if (!isset($data['order_id']) || empty($data['order_id'])) {
        Response::error('Order ID is required', null, 400);
    }

    $order_id = trim((string) $data['order_id']);
    if ($order_id === '') {
        Response::error('Order ID is required', null, 400);
    }

    // Optional fields to update
    $updateMap = [];
    $params = [':order_id' => $order_id];

    // Payment status
    if (isset($data['newStatus']) && $data['newStatus'] !== '') {
        $allowedStatuses = [
            WF_Constants::PAYMENT_STATUS_PENDING,
            WF_Constants::PAYMENT_STATUS_PAID,
            WF_Constants::PAYMENT_STATUS_REFUNDED,
            WF_Constants::PAYMENT_STATUS_FAILED
        ];
        if (!in_array($data['newStatus'], $allowedStatuses, true)) {
            Response::error('Invalid payment status', null, 422);
        }
        $updateMap[] = 'payment_status = :payment_status';
        $params[':payment_status'] = $data['newStatus'];
    }

    // Shipping address (blank means revert to account address)
    if (array_key_exists('shipping_address', $data)) {
        $shippingAddress = trim((string) $data['shipping_address']);
        if ($shippingAddress === '') {
            $updateMap[] = 'shipping_address = NULL';
        } else {
            $updateMap[] = 'shipping_address = :shipping_address';
            $params[':shipping_address'] = substr($shippingAddress, 0, 1000);
        }
    }

    // Order status
    if (isset($data['status']) && $data['status'] !== '') {
        $allowedOrderStatuses = [
            WF_Constants::ORDER_STATUS_PENDING,
            WF_Constants::ORDER_STATUS_PROCESSING,
            WF_Constants::ORDER_STATUS_SHIPPED,
            WF_Constants::ORDER_STATUS_DELIVERED,
            WF_Constants::ORDER_STATUS_CANCELLED
        ];
        if (!in_array($data['status'], $allowedOrderStatuses, true)) {
            Response::error('Invalid order status', null, 422);
        }
        $updateMap[] = 'status = :status';
        $params[':status'] = $data['status'];
    }

    // Tracking number
    if (array_key_exists('tracking_number', $data)) {
        $updateMap[] = 'tracking_number = :tracking_number';
        $params[':tracking_number'] = substr(trim((string) $data['tracking_number']), 0, 100);
    }

    // Payment method
    if (isset($data['payment_method']) && $data['payment_method'] !== '') {
        $allowedPaymentMethods = [
            WF_Constants::PAYMENT_METHOD_SQUARE,
            WF_Constants::PAYMENT_METHOD_CASH,
            WF_Constants::PAYMENT_METHOD_CHECK,
            WF_Constants::PAYMENT_METHOD_PAYPAL,
            WF_Constants::PAYMENT_METHOD_VENMO,
            WF_Constants::PAYMENT_METHOD_OTHER
        ];
        if (!in_array($data['payment_method'], $allowedPaymentMethods, true)) {
            Response::error('Invalid payment method', null, 422);
        }
        $updateMap[] = 'payment_method = :payment_method';
        $params[':payment_method'] = $data['payment_method'];
    }

    // Check number
    if (array_key_exists('check_number', $data)) {
        $updateMap[] = 'check_number = :check_number';
        $params[':check_number'] = substr(trim((string) $data['check_number']), 0, 64);
    }

    // Payment.created_at
    if (isset($data['payment_at']) && $data['payment_at'] !== '') {
        $ts = strtotime((string) $data['payment_at']);
        if ($ts === false) {
            Response::error('Invalid payment date', null, 422);
        }
        $updateMap[] = 'payment_at = :payment_at';
        $params[':payment_at'] = date('Y-m-d H:i:s', $ts);
    }

    // Payment notes
    if (array_key_exists('payment_notes', $data)) {
        $updateMap[] = 'payment_notes = :payment_notes';
        $params[':payment_notes'] = substr(trim((string) $data['payment_notes']), 0, 2000);
    }

    if (empty($updateMap)) {
        Response::error('No valid fields supplied for update', null, 400);
    }

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if order exists
    $row = Database::queryOne('SELECT id FROM orders WHERE id = ?', [$order_id]);

    if (!$row) {
        Response::notFound('Order not found');
    }

    // Build dynamic update query
    $sql = 'UPDATE orders SET ' . implode(', ', $updateMap) . ' WHERE id = :order_id';
    $affected = Database::execute($sql, $params);

    if ($affected > 0) {
        Response::updated(['order_id' => $order_id]);
    } else {
        // No rows affected: treat as no-op success if the order still exists
        $stillExists = Database::queryOne('SELECT id FROM orders WHERE id = ?', [$order_id]);
        if ($stillExists) {
            Response::noChanges(['order_id' => $order_id]);
        } else {
            Response::notFound('Order not found');
        }
    }

} catch (PDOException $e) {
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
