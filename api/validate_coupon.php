<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');
$cartTotal = floatval($input['cartTotal'] ?? 0);

if (!$code) {
    echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ?");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid coupon code']);
        exit;
    }
    
    if (!$coupon[WF_Constants::FIELD_ACTIVE]) {
        echo json_encode(['success' => false, 'message' => 'This coupon is inactive']);
        exit;
    }
    
    if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This coupon has expired']);
        exit;
    }
    
    if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'This coupon has reached its usage limit']);
        exit;
    }
    
    if ($coupon['min_order_amount'] > 0 && $cartTotal < $coupon['min_order_amount']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Minimum order amount not met. Spend $' . number_format($coupon['min_order_amount'], 2) . ' to use this coupon.'
        ]);
        exit;
    }
    
    // Calculate discount
    $discount = 0;
    if ($coupon['type'] === WF_Constants::COUPON_TYPE_PERCENTAGE) {
        $discount = $cartTotal * ($coupon['value'] / 100);
    } else {
        $discount = $coupon['value'];
    }
    
    // Ensure discount doesn't exceed total
    if ($discount > $cartTotal) {
        $discount = $cartTotal;
    }
    
    echo json_encode([
        'success' => true,
        'coupon' => [
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'value' => $coupon['value'],
            'discount_amount' => $discount
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error validating coupon']);
}
