<?php

// Include the configuration file
require_once 'config.php';
require_once __DIR__ . '/../includes/response.php';

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
    if (!isset($data['orderId']) || empty($data['orderId'])) {
        Response::error('Order ID is required', null, 400);
    }

    $orderId = $data['orderId'];

    // Optional fields to update
    $updateMap = [];
    $params = [':orderId' => $orderId];

    // Payment status
    if (isset($data['newStatus']) && $data['newStatus'] !== '') {
        $allowedStatuses = ['Pending', 'Processing', 'Received', 'Refunded', 'Failed'];
        if (!in_array($data['newStatus'], $allowedStatuses)) {
            Response::error('Invalid payment status', null, 400);
        }
        $updateMap[] = 'paymentStatus = :paymentStatus';
        $params[':paymentStatus'] = $data['newStatus'];
    }

    // Shipping address (blank means revert to account address)
    if (array_key_exists('shippingAddress', $data)) {
        if (trim($data['shippingAddress']) === '') {
            $updateMap[] = 'shippingAddress = NULL';
        } else {
            $updateMap[] = 'shippingAddress = :shippingAddress';
            $params[':shippingAddress'] = $data['shippingAddress'];
        }
    }

    // Order status
    if (isset($data['status']) && $data['status'] !== '') {
        $updateMap[] = 'order_status = :orderStatus';
        $params[':orderStatus'] = $data['status'];
    }

    // Tracking number
    if (array_key_exists('trackingNumber', $data)) {
        $updateMap[] = 'trackingNumber = :trackingNumber';
        $params[':trackingNumber'] = $data['trackingNumber'];
    }

    // Payment method
    if (isset($data['paymentMethod']) && $data['paymentMethod'] !== '') {
        $updateMap[] = 'paymentMethod = :paymentMethod';
        $params[':paymentMethod'] = $data['paymentMethod'];
    }

    // Check number
    if (array_key_exists('checkNumber', $data)) {
        $updateMap[] = 'checkNumber = :checkNumber';
        $params[':checkNumber'] = $data['checkNumber'];
    }

    // Payment date
    if (isset($data['paymentDate']) && $data['paymentDate'] !== '') {
        $updateMap[] = 'paymentDate = :paymentDate';
        $params[':paymentDate'] = $data['paymentDate'];
    }

    // Payment notes
    if (array_key_exists('paymentNotes', $data)) {
        $updateMap[] = 'paymentNotes = :paymentNotes';
        $params[':paymentNotes'] = $data['paymentNotes'];
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
    $row = Database::queryOne('SELECT id FROM orders WHERE id = ?', [$orderId]);

    if (!$row) {
        Response::notFound('Order not found');
    }

    // Build dynamic update query
    $sql = 'UPDATE orders SET ' . implode(', ', $updateMap) . ' WHERE id = :orderId';
    $affected = Database::execute($sql, $params);

    if ($affected > 0) {
        Response::updated(['orderId' => $orderId]);
    } else {
        // No rows affected: treat as no-op success if the order still exists
        $stillExists = Database::queryOne('SELECT id FROM orders WHERE id = ?', [$orderId]);
        if ($stillExists) {
            Response::noChanges(['orderId' => $orderId]);
        } else {
            Response::notFound('Order not found');
        }
    }

} catch (PDOException $e) {
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
