<?php

// api/update-order.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    Response::error('Invalid JSON', null, 400);
}

if (empty($input['orderId'])) {
    Response::error('orderId required', null, 400);
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
        Response::notFound('Order not found');
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
        'checkNumber' => 'checkNumber',
        // New: allow updating the main order date
        'date' => 'date',
    ];
    foreach ($scalarFields as $k => $col) {
        if (array_key_exists($k, $input)) {
            $val = $input[$k];
            // Normalize date values to full datetime if necessary
            if ($k === 'date' || $k === 'paymentDate') {
                if ($val === '' || $val === null) {
                    $val = null;
                } else {
                    // Accept date-only (YYYY-MM-DD), datetime-local, or any strtotime-compatible string
                    $ts = @strtotime((string)$val);
                    if ($ts !== false) {
                        $val = date('Y-m-d H:i:s', $ts);
                    }
                }
            }
            $updateMap[] = "$col = ?";
            $params[] = $val;
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
    Response::success(['orderId' => $orderId]);
} catch (Exception $e) {
    Database::rollBack();
    Response::serverError('Server error', $e->getMessage());
}
