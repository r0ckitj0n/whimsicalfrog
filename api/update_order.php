<?php

// api/update-order.php
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

if (empty($input['orderId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'orderId required']);
    exit;
}
$orderId = $input['orderId'];

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
    // Ensure order exists
    $row = Database::queryOne('SELECT id FROM orders WHERE id = ?', [$orderId]);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    Database::beginTransaction();

    // -- Update order scalar fields (reuse logic similar to update-payment-status) --
    $updateMap = [];
    $params = [];
    $scalarFields = [
        'status' => 'order_status',
        'trackingNumber' => 'trackingNumber',
        'paymentMethod' => 'paymentMethod',
        'shippingMethod' => 'shippingMethod',
        'paymentStatus' => 'paymentStatus',
        'paymentDate' => 'paymentDate',
        'paymentNotes' => 'paymentNotes',
        'shippingAddress' => 'shippingAddress',
        'checkNumber' => 'checkNumber'
    ];
    foreach ($scalarFields as $k => $col) {
        if (array_key_exists($k, $input)) {
            $updateMap[] = "$col = ?";
            $params[] = ($input[$k] === '' ? null : $input[$k]);
        }
    }
    if ($updateMap) {
        $sql = 'UPDATE orders SET '.implode(', ', $updateMap).' WHERE id = ?';
        $params[] = $orderId;
        Database::execute($sql, $params);
    }

    // -- Update items --
    if (isset($input['items']) && is_array($input['items'])) {
        // delete existing items
        Database::execute('DELETE FROM order_items WHERE orderId = ?', [$orderId]);

        // Get the next order item ID sequence number
        $countRow = Database::queryOne('SELECT COUNT(*) AS c FROM order_items');
        $itemCount = $countRow ? (int)$countRow['c'] : 0;

        $itemIndex = 0;
        foreach ($input['items'] as $row) {
            if (empty($row['sku']) || empty($row['quantity'])) {
                continue;
            }

            // Generate streamlined order item ID
            $itemSequence = str_pad($itemCount + $itemIndex + 1, 3, '0', STR_PAD_LEFT);
            $itemId = 'OI' . $itemSequence;
            $itemIndex++;

            $qty = (int)$row['quantity'];
            $sku = $row['sku'];
            Database::execute('INSERT INTO order_items (id, orderId, sku, quantity, price) VALUES (?,?,?,?, (SELECT retailPrice FROM items WHERE sku = ?))', [$itemId, $orderId, $sku, $qty, $sku]);
        }
        // recalc total
        $totalRow = Database::queryOne('SELECT SUM(quantity*price) AS total FROM order_items WHERE orderId = ?', [$orderId]);
        $newTotal = $totalRow && $totalRow['total'] !== null ? (float)$totalRow['total'] : 0;
        Database::execute('UPDATE orders SET total = ? WHERE id = ?', [$newTotal, $orderId]);
    }

    Database::commit();
    echo json_encode(['success' => true,'orderId' => $orderId]);
} catch (Exception $e) {
    Database::rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Server error','details' => $e->getMessage()]);
}
