<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
try {
    Database::getInstance();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw $e;
}
$orderId = $_POST['orderId'] ?? '';
$action = $_POST['action'] ?? '';
$tracking = trim($_POST['trackingNumber'] ?? '');
$noteRaw = trim($_POST['note'] ?? '');
$payRaw = trim($_POST['paynote'] ?? '');
$noteLine = '';
$payLine = '';
if ($noteRaw !== '') {
    $noteLine = date('Y-m-d H:i') . ' - ' . $noteRaw;
}
if ($payRaw !== '') {
    $payLine = date('Y-m-d H:i') . ' - ' . $payRaw;
}
if (!$orderId || !in_array($action, ['ship','deliver','note','updateField'])) {
    Response::error('Missing or invalid parameters', null, 400);
}
try {
    if ($action === 'ship') {
        if ($tracking === '') {
            Response::error('Tracking number required', null, 400);
        }
        Database::execute("UPDATE orders SET order_status='Shipped', trackingNumber=?, fulfillmentNotes = CASE WHEN ?='' THEN fulfillmentNotes ELSE CONCAT_WS('\n', fulfillmentNotes, ?) END, paymentNotes = CASE WHEN ?='' THEN paymentNotes ELSE CONCAT_WS('\n', paymentNotes, ?) END, paymentStatus=IF(paymentStatus='Received', paymentStatus, 'Received') WHERE id=?", [$tracking, $noteLine, $noteLine, $payLine, $payLine, $orderId]);

        // Log admin activity
        if (class_exists('DatabaseLogger')) {
            DatabaseLogger::logAdminActivity(
                'order_shipped',
                "Marked order $orderId as shipped with tracking: $tracking",
                'order',
                $orderId
            );
        }

        Response::updated(['message' => 'Order marked as shipped.']);
    } elseif ($action === 'deliver') {
        Database::execute("UPDATE orders SET order_status='Delivered', fulfillmentNotes = CASE WHEN ?='' THEN fulfillmentNotes ELSE CONCAT_WS('\n', fulfillmentNotes, ?) END, paymentNotes = CASE WHEN ?='' THEN paymentNotes ELSE CONCAT_WS('\n', paymentNotes, ?) END WHERE id=?", [$noteLine, $noteLine, $payLine, $payLine, $orderId]);

        // Log admin activity
        if (class_exists('DatabaseLogger')) {
            DatabaseLogger::logAdminActivity(
                'order_delivered',
                "Marked order $orderId as delivered",
                'order',
                $orderId
            );
        }

        Response::updated(['message' => 'Order marked as delivered.']);
    } elseif ($action === 'note') {
        if ($noteLine === '' && $payLine === '') {
            Response::error('No note provided', null, 400);
        }
        Database::execute("UPDATE orders SET fulfillmentNotes = CASE WHEN ?='' THEN fulfillmentNotes ELSE CONCAT_WS('\n', fulfillmentNotes, ?) END, paymentNotes = CASE WHEN ?='' THEN paymentNotes ELSE CONCAT_WS('\n', paymentNotes, ?) END WHERE id=?", [$noteLine, $noteLine, $payLine, $payLine, $orderId]);

        // Log admin activity
        if (class_exists('DatabaseLogger')) {
            $noteDescription = "Added notes to order $orderId";
            if ($noteRaw) {
                $noteDescription .= " (fulfillment: $noteRaw)";
            }
            if ($payRaw) {
                $noteDescription .= " (payment: $payRaw)";
            }

            DatabaseLogger::logAdminActivity(
                'order_note_added',
                $noteDescription,
                'order',
                $orderId
            );
        }

        Response::updated(['message' => 'Notes saved.']);
    } elseif ($action === 'updateField') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        // Validate field and value
        $allowedFields = ['status', 'order_status', 'paymentMethod', 'shippingMethod', 'paymentStatus', 'paymentDate', 'date'];
        if (!in_array($field, $allowedFields)) {
            Response::error('Invalid field', null, 400);
        }

        // Map field names to actual database column names
        $fieldMapping = [
            'status' => 'order_status',
            'order_status' => 'order_status',
            'paymentMethod' => 'paymentMethod',
            'shippingMethod' => 'shippingMethod',
            'paymentStatus' => 'paymentStatus',
            'paymentDate' => 'paymentDate',
            'date' => 'date'
        ];
        $dbField = $fieldMapping[$field];

        if ($value === '' && $field !== 'paymentDate') {
            Response::error('Value cannot be empty', null, 400);
        }

        // Validate field-specific values
        $allowedValues = [
            'status' => ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
            'order_status' => ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
            'paymentMethod' => ['Credit Card', 'Cash', 'Check', 'PayPal', 'Venmo', 'Other'],
            'shippingMethod' => ['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS'],
            'paymentStatus' => ['Pending', 'Received', 'Refunded', 'Failed']
        ];

        // Special handling for date fields
        if ($field === 'paymentDate' || $field === 'date') {
            if ($value !== '' && !DateTime::createFromFormat('Y-m-d', $value)) {
                Response::error('Invalid date format. Use YYYY-MM-DD', null, 400);
            }
        } elseif (!in_array($value, $allowedValues[$field])) {
            Response::error('Invalid value for field', null, 400);
        }

        // Update the field
        $affected = Database::execute("UPDATE orders SET `$dbField` = ? WHERE id = ?", [$value, $orderId]);

        // Create proper display name for field
        $displayField = ($field === 'order_status' || $field === 'status') ? 'OrderStatus' :
                       (($field === 'date') ? 'Date' : ucfirst($field));

        if ($affected > 0) {
            Response::updated(['message' => $displayField . ' updated successfully']);
        } else {
            // Check if order exists
            $exists = Database::queryOne("SELECT id FROM orders WHERE id = ?", [$orderId]);
            if ($exists) {
                Response::noChanges(['message' => 'No change needed - ' . $displayField . ' is already set to that value']);
            } else {
                Response::notFound('Order not found');
            }
        }
    }
} catch (Exception $e) {
    Response::serverError('Server error: ' . $e->getMessage());
}
