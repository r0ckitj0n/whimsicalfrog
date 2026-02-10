<?php

// api/update_order.php
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    Response::error('Invalid JSON', null, 400);
}

if (empty($input['order_id'])) {
    Response::error('order_id required', null, 400);
}
$order_id = trim((string) $input['order_id']);
if ($order_id === '') {
    Response::error('order_id required', null, 400);
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        throw $e;
    }

    // Ensure order exists and get current status for transition detection
    $currentOrder = Database::queryOne('SELECT id, payment_status, payment_at FROM orders WHERE id = ?', [$order_id]);
    if (!$currentOrder) {
        Response::notFound('Order not found');
    }

    Database::beginTransaction();

    // -- Update order scalar fields --
    $updateMap = [];
    $params = [];
    $scalarFields = [
        'status' => 'status',
        'tracking_number' => 'tracking_number',
        'payment_method' => 'payment_method',
        'shipping_method' => 'shipping_method',
        'payment_status' => 'payment_status',
        'payment_at' => 'payment_at',
        'payment_notes' => 'payment_notes',
        'shipping_address' => 'shipping_address',
        'address_line_1' => 'address_line_1',
        'address_line_2' => 'address_line_2',
        'city' => 'city',
        'state' => 'state',
        'zip_code' => 'zip_code',
        'shipping_cost' => 'shipping_cost',
        'tax_amount' => 'tax_amount',
        'check_number' => 'check_number',
        'total' => 'total_amount',
        'total_amount' => 'total_amount',
        'discount_amount' => 'discount_amount',
        'coupon_code' => 'coupon_code',
        'created_at' => 'created_at',
        'user_id' => 'user_id',
        'cashier_id' => 'cashier_id',
        'fulfillment_notes' => 'fulfillment_notes'
    ];

    error_log("[OrderUpdate] Processing input for Order ID: " . $order_id);
    error_log("[OrderUpdate] Raw input: " . json_encode($input));

    // Automatic Payment Date: If changing status to 'paid', set payment_at if not provided
    if (isset($input['payment_status'])) {
        $newPS = strtolower(trim((string) $input['payment_status']));
        if ($newPS === 'received')
            $newPS = 'paid'; // normalize

        if ($newPS === 'paid' && $currentOrder['payment_status'] !== 'paid') {
            if (empty($input['payment_at']) || $input['payment_at'] === '—') {
                $tz = BusinessSettings::get('business_timezone', 'America/New_York');
                try {
                    $dt = new DateTime('now', new DateTimeZone($tz));
                    $input['payment_at'] = $dt->format('Y-m-d H:i:s');
                    error_log("[OrderUpdate] Auto-setting payment_at to current timestamp ({$tz}) due to status transition to 'paid'");
                } catch (Exception $e) {
                    $input['payment_at'] = date('Y-m-d H:i:s');
                    error_log("[OrderUpdate] Failed to use timezone {$tz}, falling back to system time: " . $e->getMessage());
                }
            }
        }
    }

    foreach ($scalarFields as $k => $col) {
        if (array_key_exists($k, $input)) {
            $val = $input[$k];
            error_log("[OrderUpdate] Field detected: $k (mapped to $col) with value: " . var_export($val, true));

            // Normalize enum values for payment_status
            if ($k === 'payment_status' && $val !== null) {
                $val = strtolower(trim((string) $val));
                if ($val === 'received') {
                    $val = 'paid';
                }

                $allowed = ['pending', 'paid', 'failed', 'refunded'];
                if (!in_array($val, $allowed, true)) {
                    Response::error('Invalid payment_status value', null, 422);
                }
            }
            if ($k === 'status' && $val !== null) {
                $allowedOrderStatuses = [
                    WF_Constants::ORDER_STATUS_PENDING,
                    WF_Constants::ORDER_STATUS_PROCESSING,
                    WF_Constants::ORDER_STATUS_SHIPPED,
                    WF_Constants::ORDER_STATUS_DELIVERED,
                    WF_Constants::ORDER_STATUS_CANCELLED
                ];
                if (!in_array((string) $val, $allowedOrderStatuses, true)) {
                    Response::error('Invalid status value', null, 422);
                }
            }
            if ($k === 'payment_method' && $val !== null && trim((string) $val) !== '') {
                $allowedPaymentMethods = [
                    WF_Constants::PAYMENT_METHOD_SQUARE,
                    WF_Constants::PAYMENT_METHOD_CASH,
                    WF_Constants::PAYMENT_METHOD_CHECK,
                    WF_Constants::PAYMENT_METHOD_PAYPAL,
                    WF_Constants::PAYMENT_METHOD_VENMO,
                    WF_Constants::PAYMENT_METHOD_OTHER
                ];
                if (!in_array((string) $val, $allowedPaymentMethods, true)) {
                    Response::error('Invalid payment_method value', null, 422);
                }
            }
            if ($k === 'shipping_method' && $val !== null && trim((string) $val) !== '') {
                $allowedShippingMethods = [
                    WF_Constants::SHIPPING_METHOD_PICKUP,
                    WF_Constants::SHIPPING_METHOD_LOCAL,
                    WF_Constants::SHIPPING_METHOD_USPS,
                    WF_Constants::SHIPPING_METHOD_FEDEX,
                    WF_Constants::SHIPPING_METHOD_UPS
                ];
                if (!in_array((string) $val, $allowedShippingMethods, true)) {
                    Response::error('Invalid shipping_method value', null, 422);
                }
            }

            // Trim strings for varchar columns
            if (is_string($val) && in_array($col, ['payment_method', 'shipping_method', 'status', 'tracking_number'])) {
                $val = trim($val);
            }

            // Date Normalization
            if (in_array($col, ['created_at', 'payment_at', 'updated_at'])) {
                if ($val === '' || $val === null || $val === '—') {
                    $val = null;
                } else {
                    $ts = @strtotime((string) $val);
                    if ($ts !== false) {
                        $val = date('Y-m-d H:i:s', $ts);
                    } else {
                        $val = null;
                    }
                }
            }

            // Ensure decimal fields are numeric or null
            if (in_array($col, ['total_amount', 'discount_amount'])) {
                if ($val === '' || $val === null) {
                    $val = 0.00;
                } else {
                    $val = (float) $val;
                }
            }

            $updateMap[] = "$col = ?";
            $params[] = $val;
        }
    }

    if ($updateMap) {
        // If any address components changed, rebuild the shipping_address string
        $addressChanged = false;
        $addrParts = ['address_line_1', 'address_line_2', 'city', 'state', 'zip_code'];
        foreach ($addrParts as $p) {
            if (array_key_exists($p, $input)) {
                $addressChanged = true;
                break;
            }
        }

        if ($addressChanged) {
            // Fetch current state to fill in missing parts if only some changed
            $current = Database::queryOne('SELECT address_line_1, address_line_2, city, state, zip_code FROM orders WHERE id = ?', [$order_id]);
            $a1 = array_key_exists('address_line_1', $input) ? $input['address_line_1'] : ($current['address_line_1'] ?? '');
            $a2 = array_key_exists('address_line_2', $input) ? $input['address_line_2'] : ($current['address_line_2'] ?? '');
            $ct = array_key_exists('city', $input) ? $input['city'] : ($current['city'] ?? '');
            $st = array_key_exists('state', $input) ? $input['state'] : ($current['state'] ?? '');
            $zp = array_key_exists('zip_code', $input) ? $input['zip_code'] : ($current['zip_code'] ?? '');

            $fullAddr = trim($a1);
            if (!empty($a2))
                $fullAddr .= "\n" . trim($a2);
            $fullAddr .= "\n" . trim($ct) . ", " . trim($st) . " " . trim($zp);

            // Add shipping_address to update map if not already explicitly provided
            if (!array_key_exists('shipping_address', $input)) {
                $updateMap[] = "shipping_address = ?";
                $params[] = $fullAddr;
            }
        }

        $sql = 'UPDATE orders SET ' . implode(', ', $updateMap) . ' WHERE id = ?';
        $params[] = $order_id;
        error_log("[OrderUpdate] Executing SQL: $sql with params: " . json_encode($params));
        Database::execute($sql, $params);
    }

    // -- Update items --
    if (isset($input['items']) && is_array($input['items'])) {
        require_once __DIR__ . '/../includes/orders/helpers/OrderSchemaHelper.php';

        // delete existing items
        Database::execute('DELETE FROM order_items WHERE order_id = ?', [$order_id]);

        // Get robust sequence info for order item IDs
        $seq_info = OrderSchemaHelper::getOrderItemSequenceInfo();
        $next_seq = $seq_info['nextSequence'];

        $itemIndex = 0;
        foreach ($input['items'] as $row) {
            if (!is_array($row) || empty($row['sku']) || empty($row['quantity'])) {
                continue;
            }

            // Generate robust order item ID
            $id = 'OI' . str_pad($next_seq + $itemIndex, 10, '0', STR_PAD_LEFT);
            $itemIndex++;

            $qty = (int) $row['quantity'];
            $sku = trim((string) $row['sku']);
            if ($qty <= 0 || $qty > 100000) {
                Response::error('Invalid item quantity', null, 422);
            }
            if ($sku === '' || strlen($sku) > 64) {
                Response::error('Invalid item sku', null, 422);
            }

            error_log("[OrderUpdate] Inserting item: $id, SKU: $sku, Qty: $qty");

            Database::execute(
                'INSERT INTO order_items (id, order_id, sku, quantity, unit_price) 
                 VALUES (?, ?, ?, ?, (SELECT COALESCE(retail_price, 0) FROM items WHERE sku = ? LIMIT 1))',
                [$id, $order_id, $sku, $qty, $sku]
            );
        }

    }

    // -- Recalculate total_amount --
    // We update this if items were changed OR if shipping/tax/discount were changed
    $finFields = ['items', 'shipping_cost', 'tax_amount', 'discount_amount'];
    $needsRecalc = false;
    foreach ($finFields as $ff) {
        if (array_key_exists($ff, $input)) {
            $needsRecalc = true;
            break;
        }
    }

    if ($needsRecalc) {
        // recalc subtotal from current order_items
        $totalRow = Database::queryOne('SELECT SUM(quantity*unit_price) AS total FROM order_items WHERE order_id = ?', [$order_id]);
        $subtotal = $totalRow && $totalRow['total'] !== null ? (float) $totalRow['total'] : 0;

        // Get current values for shipping, tax, discount if not in input
        $currentFin = Database::queryOne('SELECT shipping_cost, tax_amount, discount_amount FROM orders WHERE id = ?', [$order_id]);

        $shipping = array_key_exists('shipping_cost', $input) ? (float) $input['shipping_cost'] : (float) ($currentFin['shipping_cost'] ?? 0);
        $tax = array_key_exists('tax_amount', $input) ? (float) $input['tax_amount'] : (float) ($currentFin['tax_amount'] ?? 0);
        $discount = array_key_exists('discount_amount', $input) ? (float) $input['discount_amount'] : (float) ($currentFin['discount_amount'] ?? 0);

        $new_total = $subtotal + $shipping + $tax - $discount;

        Database::execute('UPDATE orders SET total_amount = ? WHERE id = ?', [$new_total, $order_id]);
        error_log("[OrderUpdate] Recalculated total_amount: $new_total (Subtotal: $subtotal, Shipping: $shipping, Tax: $tax, Discount: $discount)");
    }

    // -- Update notes --
    if (isset($input['new_fulfillment_note']) && !empty(trim($input['new_fulfillment_note']))) {
        $author = (string) (getCurrentUser()['username'] ?? 'Admin');
        Database::execute(
            'INSERT INTO order_notes (order_id, note_type, note_text, author_username) VALUES (?, "fulfillment", ?, ?)',
            [$order_id, substr(trim((string) $input['new_fulfillment_note']), 0, 4000), $author]
        );
    }
    if (isset($input['new_payment_note']) && !empty(trim($input['new_payment_note']))) {
        $author = (string) (getCurrentUser()['username'] ?? 'Admin');
        Database::execute(
            'INSERT INTO order_notes (order_id, note_type, note_text, author_username) VALUES (?, "payment", ?, ?)',
            [$order_id, substr(trim((string) $input['new_payment_note']), 0, 4000), $author]
        );
    }

    Database::commit();
    if (ob_get_length()) {
        ob_clean();
    }
    Response::success(['order_id' => $order_id]);
} catch (Exception $e) {
    if (Database::getInstance()->inTransaction()) {
        Database::rollBack();
    }
    error_log("Order update failed for ID $order_id: " . $e->getMessage());
    Response::serverError('Server error', $e->getMessage());
}
