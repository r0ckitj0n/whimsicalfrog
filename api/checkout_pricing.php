<?php
/**
 * Checkout Pricing API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/tax_service.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/checkout/pricing_helper.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_meta.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('POST required');
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $item_ids = $input['item_ids'] ?? [];
    $quantities = $input['quantities'] ?? [];
    $method = $input['shipping_method'] ?? WF_Constants::SHIPPING_METHOD_USPS;

    Database::getInstance();

    // Handle VIP shipping
    $isVip = false;
    $uid = getUserId();
    if ($uid) {
        $meta = get_user_meta_bulk($uid);
        $isVip = ($meta['vip'] ?? '0') === '1';
    }

    $itemsResult = calculate_item_prices($item_ids, $quantities);
    $subtotal = $itemsResult['subtotal'];
    $shipping = calculate_shipping($subtotal, $method, 0, $isVip);

    // Simplified tax calculation for this step
    $tax = round($subtotal * 0.07, 2);
    $total = $subtotal + $shipping + $tax;

    Response::success([
        'pricing' => [
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => $tax,
            'discount' => 0,
            'total' => round($total, 2),
            'currency' => BusinessSettings::get('currency_code', 'USD')
        ]
    ]);
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
