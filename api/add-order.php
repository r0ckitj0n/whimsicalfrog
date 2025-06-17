<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
    exit;
}
$pdo = new PDO($dsn, $user, $pass, $options);
// Validate required fields
$required = ['customerId','productIds','quantities','paymentMethod','total'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success'=>false,'error'=>'Missing field: '.$field]);
        exit;
    }
}
$productIds = $input['productIds'];
$quantities = $input['quantities'];
if (!is_array($productIds) || !is_array($quantities) || count($productIds)!==count($quantities)) {
    echo json_encode(['success'=>false,'error'=>'Invalid items array']);
    exit;
}
$paymentMethod = $input['paymentMethod'];
$shippingMethod = $input['shippingMethod'] ?? 'Customer Pickup'; // Default to Customer Pickup if not provided
$paymentStatus = in_array($paymentMethod, ['Cash','Check']) ? 'Pending' : 'Received';
$orderStatus   = in_array($paymentMethod, ['Cash','Check']) ? 'Pending' : 'Processing';

// Generate compact order ID format: [CustomerNum][MonthDay][ShippingCode][RandomNum]
// Example: 01A15P23 (8 characters total)
$date = date('Y-m-d H:i:s');
$customerId = $input['customerId'];

// Get last 2 digits of customer number
$customerNum = '00';
if (preg_match('/U(\d+)/', $customerId, $matches)) {
    $customerNum = str_pad($matches[1] % 100, 2, '0', STR_PAD_LEFT);
} else {
    // For non-standard user IDs, create a hash-based 2-digit number
    $customerNum = str_pad(abs(crc32($customerId)) % 100, 2, '0', STR_PAD_LEFT);
}

// Get compact date format: Month letter (A-L) + Day (01-31)
$monthLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
$monthLetter = $monthLetters[date('n') - 1]; // n = 1-12, array is 0-11
$dayOfMonth = date('d');
$compactDate = $monthLetter . $dayOfMonth;

// Get single-character shipping method code
$shippingCodes = [
    'Customer Pickup' => 'P',
    'Local Delivery' => 'L',
    'USPS' => 'U',
    'FedEx' => 'F',
    'UPS' => 'X'
];
$shippingCode = $shippingCodes[$shippingMethod] ?? 'P';

// Generate random 2-digit number
$randomNum = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);

// Create compact order ID: 01A15P23
$orderId = $customerNum . $compactDate . $shippingCode . $randomNum;

$pdo->beginTransaction();
try {
    // Add shippingMethod to the insert statement
    $stmt = $pdo->prepare("INSERT INTO orders (id, userId, total, paymentMethod, shippingMethod, status, date, paymentStatus) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$orderId, $input['customerId'], $input['total'], $paymentMethod, $shippingMethod, $orderStatus, $date, $paymentStatus]);
    $itemStmt = $pdo->prepare("INSERT INTO order_items (id, orderId, productId, quantity, price) VALUES (?,?,?,?,?)");
    $updateInv = $pdo->prepare("UPDATE inventory SET stockLevel = GREATEST(stockLevel - ?, 0) WHERE productId = ?");
            $priceStmt = $pdo->prepare("SELECT retailPrice as basePrice FROM items WHERE id = ?");
    
    // Get the next order item ID sequence number
    $itemCountStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items');
    $itemCountStmt->execute();
    $itemCount = $itemCountStmt->fetchColumn();
    
    for ($i=0;$i<count($productIds);$i++) {
        $itemSequence = str_pad($itemCount + $i + 1, 3, '0', STR_PAD_LEFT);
        $itemId = 'OI' . $itemSequence;
        $pid = $productIds[$i];
        $qty = (int)$quantities[$i];
        
        // Get the product price
        $priceStmt->execute([$pid]);
        $productPrice = $priceStmt->fetchColumn();
        
        // If no price found, use 0.00 as fallback
        if ($productPrice === false || $productPrice === null) {
            $productPrice = 0.00;
        }
        
        $itemStmt->execute([$itemId, $orderId, $pid, $qty, $productPrice]);
        $updateInv->execute([$qty, $pid]);
    }
    $pdo->commit();
    
    // Send order confirmation emails
    $emailResults = sendOrderConfirmationEmails($orderId, $pdo);
    
    // Log email results but don't fail the order if emails fail
    if ($emailResults) {
        if ($emailResults['customer']) {
            error_log("Order $orderId: Customer confirmation email sent successfully");
        } else {
            error_log("Order $orderId: Failed to send customer confirmation email");
        }
        
        if ($emailResults['admin']) {
            error_log("Order $orderId: Admin notification email sent successfully");
        } else {
            error_log("Order $orderId: Failed to send admin notification email");
        }
    }
    
    echo json_encode(['success'=>true,'orderId'=>$orderId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?> 