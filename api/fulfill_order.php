<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}
try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
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
    echo json_encode(['success'=>false,'error'=>'Missing or invalid parameters']);
    exit;
}
try {
    if ($action === 'ship') {
        if ($tracking === '') {
            echo json_encode(['success'=>false,'error'=>'Tracking number required']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE orders SET status='Shipped', trackingNumber=?, fulfillmentNotes = CASE WHEN ?='' THEN fulfillmentNotes ELSE CONCAT_WS('\n', fulfillmentNotes, ?) END, paymentNotes = CASE WHEN ?='' THEN paymentNotes ELSE CONCAT_WS('\n', paymentNotes, ?) END, paymentStatus=IF(paymentStatus='Received', paymentStatus, 'Received') WHERE id=?");
        $stmt->execute([$tracking, $noteLine, $noteLine, $payLine, $payLine, $orderId]);
        echo json_encode(['success'=>true,'message'=>'Order marked as shipped.']);
    } elseif ($action === 'deliver') {
        $stmt = $pdo->prepare("UPDATE orders SET status='Delivered', fulfillmentNotes = CASE WHEN ?='' THEN fulfillmentNotes ELSE CONCAT_WS('\n', fulfillmentNotes, ?) END, paymentNotes = CASE WHEN ?='' THEN paymentNotes ELSE CONCAT_WS('\n', paymentNotes, ?) END WHERE id=?");
        $stmt->execute([$noteLine, $noteLine, $payLine, $payLine, $orderId]);
        echo json_encode(['success'=>true,'message'=>'Order marked as delivered.']);
    } elseif ($action === 'note') {
        if ($noteLine === '' && $payLine === '') {
            echo json_encode(['success'=>false,'error'=>'No note provided']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE orders SET fulfillmentNotes = CASE WHEN ?='' THEN fulfillmentNotes ELSE CONCAT_WS('\n', fulfillmentNotes, ?) END, paymentNotes = CASE WHEN ?='' THEN paymentNotes ELSE CONCAT_WS('\n', paymentNotes, ?) END WHERE id=?");
        $stmt->execute([$noteLine, $noteLine, $payLine, $payLine, $orderId]);
        echo json_encode(['success'=>true,'message'=>'Notes saved.']);
    } elseif ($action === 'updateField') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        
        
        // Validate field and value
        $allowedFields = ['status', 'paymentMethod', 'shippingMethod', 'paymentStatus', 'paymentDate'];
        if (!in_array($field, $allowedFields)) {
            echo json_encode(['success'=>false,'error'=>'Invalid field']);
            exit;
        }
        
        if ($value === '' && $field !== 'paymentDate') {
            echo json_encode(['success'=>false,'error'=>'Value cannot be empty']);
            exit;
        }
        
        // Validate field-specific values
        $allowedValues = [
            'status' => ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
            'paymentMethod' => ['Credit Card', 'Cash', 'Check', 'PayPal', 'Venmo', 'Other'],
            'shippingMethod' => ['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS'],
            'paymentStatus' => ['Pending', 'Received', 'Refunded', 'Failed']
        ];
        
        // Special handling for paymentDate
        if ($field === 'paymentDate') {
            if ($value !== '' && !DateTime::createFromFormat('Y-m-d', $value)) {
                echo json_encode(['success'=>false,'error'=>'Invalid date format. Use YYYY-MM-DD']);
                exit;
            }
        } elseif (!in_array($value, $allowedValues[$field])) {
            echo json_encode(['success'=>false,'error'=>'Invalid value for field']);
            exit;
        }
        
        // Update the field
        $stmt = $pdo->prepare("UPDATE orders SET `$field` = ? WHERE id = ?");
        $stmt->execute([$value, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success'=>true,'message'=>ucfirst($field) . ' updated successfully']);
        } else {
            // Check if order exists
            $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
            $checkStmt->execute([$orderId]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success'=>true,'message'=>'No change needed - ' . ucfirst($field) . ' is already set to that value']);
            } else {
                echo json_encode(['success'=>false,'error'=>'Order not found']);
            }
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
} 