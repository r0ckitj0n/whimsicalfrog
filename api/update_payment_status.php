<?php

// Include the configuration file
require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['orderId']) || empty($data['orderId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID is required']);
        exit;
    }

    $orderId = $data['orderId'];

    // Optional fields to update
    $updateMap = [];
    $params = [':orderId' => $orderId];

    // Payment status
    if (isset($data['newStatus']) && $data['newStatus'] !== '') {
        $allowedStatuses = ['Pending', 'Processing', 'Received', 'Refunded', 'Failed'];
        if (!in_array($data['newStatus'], $allowedStatuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payment status']);
            exit;
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
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields supplied for update']);
        exit;
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
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    // Build dynamic update query
    $sql = 'UPDATE orders SET ' . implode(', ', $updateMap) . ' WHERE id = :orderId';
    $result = Database::execute($sql, $params);

    if ($result) {
        echo json_encode(['success' => true,'message' => 'Order updated','orderId' => $orderId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update order']);
    }

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
