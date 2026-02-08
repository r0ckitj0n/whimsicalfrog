<?php
/**
 * Checkout Pricing API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders/helpers/OrderPricingHelper.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/tax_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('POST required');
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $item_ids = $input['item_ids'] ?? [];
    $quantities = $input['quantities'] ?? [];
    $method = $input['shipping_method'] ?? WF_Constants::SHIPPING_METHOD_USPS;
    $couponCode = isset($input['coupon_code']) ? trim((string) $input['coupon_code']) : null;
    $shippingAddress = is_array($input['shipping_address'] ?? null) ? $input['shipping_address'] : null;
    $userId = $input['user_id'] ?? getUserId();

    Database::getInstance();
    $pricing = OrderPricingHelper::computePricing(
        $item_ids,
        $quantities,
        $method,
        $shippingAddress,
        $couponCode,
        false,
        $userId
    );

    Response::success([
        'pricing' => [
            'subtotal' => (float) ($pricing['subtotal'] ?? 0),
            'shipping' => (float) ($pricing['shipping'] ?? 0),
            'tax' => (float) ($pricing['tax'] ?? 0),
            'discount' => (float) ($pricing['discount'] ?? 0),
            'coupon' => !empty($pricing['coupon']) ? ['code' => (string) $pricing['coupon']] : null,
            'total' => (float) ($pricing['total'] ?? 0),
            'currency' => BusinessSettings::get('currency_code', 'USD')
        ]
    ]);
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
