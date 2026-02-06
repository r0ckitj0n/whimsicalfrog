<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/Constants.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}

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

    $order_id = $data['order_id'];

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
        if (!in_array($data['newStatus'], $allowedStatuses)) {
            Response::error('Invalid payment status', null, 400);
        }
        $updateMap[] = 'payment_status = :payment_status';
        $params[':payment_status'] = $data['newStatus'];
    }

    // Shipping address (blank means revert to account address)
    if (array_key_exists('shipping_address', $data)) {
        if (trim($data['shipping_address']) === '') {
            $updateMap[] = 'shipping_address = NULL';
        } else {
            $updateMap[] = 'shipping_address = :shipping_address';
            $params[':shipping_address'] = $data['shipping_address'];
        }
    }

    // Order status
    if (isset($data['status']) && $data['status'] !== '') {
        $updateMap[] = 'status = :status';
        $params[':status'] = $data['status'];
    }

    // Tracking number
    if (array_key_exists('tracking_number', $data)) {
        $updateMap[] = 'tracking_number = :tracking_number';
        $params[':tracking_number'] = $data['tracking_number'];
    }

    // Payment method
    if (isset($data['payment_method']) && $data['payment_method'] !== '') {
        $updateMap[] = 'payment_method = :payment_method';
        $params[':payment_method'] = $data['payment_method'];
    }

    // Check number
    if (array_key_exists('check_number', $data)) {
        $updateMap[] = 'check_number = :check_number';
        $params[':check_number'] = $data['check_number'];
    }

    // Payment.created_at
    if (isset($data['payment_at']) && $data['payment_at'] !== '') {
        $updateMap[] = 'payment_at = :payment_at';
        $params[':payment_at'] = $data['payment_at'];
    }

    // Payment notes
    if (array_key_exists('payment_notes', $data)) {
        $updateMap[] = 'payment_notes = :payment_notes';
        $params[':payment_notes'] = $data['payment_notes'];
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
