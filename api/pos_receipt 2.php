<?php
/**
 * Get Receipt Data API v1.3.1
 * Returns structured JSON for the ReceiptModal/ReceiptView components.
 */

try {
    // 1. Core Includes
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/../includes/response.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/business_settings_helper.php';
    require_once __DIR__ . '/../includes/receipt_helper.php';

    Response::validateMethod('GET');

    // 2. Database Initialization
    Database::getInstance();

    // 3. Input Validation
    $order_id = $_GET['order_id'] ?? '';
    if (empty($order_id)) {
        Response::error('Order ID is required', null, 400);
    }

    // 4. Fetch Order
    $order = Database::queryOne('SELECT * FROM orders WHERE id = ?', [$order_id]);
    if (!$order) {
        Response::notFound("Order #{$order_id} not found.");
    }

    // 5. Fetch Order Items
    $orderItems = Database::queryAll("
        SELECT 
            oi.sku,
            oi.quantity,
            oi.unit_price as price,
            i.name as item_name,
            i.category,
            i.retail_price AS itemRetailPrice
        FROM order_items oi
        LEFT JOIN items i ON oi.sku COLLATE utf8mb4_unicode_ci = i.sku COLLATE utf8mb4_unicode_ci
        WHERE oi.order_id = ?
    ", [$order_id]);

    // 6. Data Transformation
    $itemsSubtotal = 0.0;
    $itemsData = [];
    foreach ($orderItems as $it) {
        $q = (float) ($it['quantity'] ?? 0);
        $unit = (float) ($it['price'] ?? 0);
        $line = $q * $unit;
        $itemsSubtotal += $line;

        $itemsData[] = [
            'sku' => (string) ($it['sku'] ?? 'N/A'),
            'item_name' => (string) ($it['item_name'] ?? $it['sku'] ?? 'Unknown Item'),
            'quantity' => (int) $q,
            'price' => number_format($unit, 2),
            'ext_price' => number_format($line, 2)
        ];
    }

    // 7. Calculate Totals
    $shipping_amount = (float) ($order['shipping_cost'] ?? $order['shipping_amount'] ?? $order['shipping'] ?? 0);
    $tax_amount = (float) ($order['tax_amount'] ?? $order['tax'] ?? 0);
    $discount_amount = (float) ($order['discount_amount'] ?? 0);
    $total_amount = (float) ($order['total_amount'] ?? $order['total'] ?? 0);

    // Safety fallback for total and reconstruction for missing tax/shipping
    if ($total_amount <= 0) {
        $total_amount = $itemsSubtotal - $discount_amount + $shipping_amount + $tax_amount;
    }

    // Reconstruction logic for existing orders with missing tax/shipping columns
    // If tax_amount is 0 but (total - subtotal + discount - shipping) is positive, that's likely the tax.
    if ($tax_amount <= 0 && $total_amount > ($itemsSubtotal - $discount_amount + $shipping_amount)) {
        $tax_amount = round($total_amount - ($itemsSubtotal - $discount_amount + $shipping_amount), 2);
    }

    // 8. Business Info
    $business_info = [
        'name' => (string) BusinessSettings::getBusinessName(),
        'tagline' => (string) BusinessSettings::get('business_tagline', 'Pond-erful Crafts, Hoppy by Design!'),
        'phone' => (string) BusinessSettings::get('business_phone', ''),
        'domain' => (string) BusinessSettings::getBusinessDomain(),
        'url' => (string) BusinessSettings::getSiteUrl(''),
        'address_block' => (string) BusinessSettings::getBusinessAddressBlock(),
        'owner' => (string) BusinessSettings::get('business_owner', '')
    ];

    // 9. Policies
    $policy_links = [];
    $policies = ['policy' => 'Policies', 'privacy' => 'Privacy', 'terms' => 'Terms'];
    foreach ($policies as $key => $label) {
        $url = BusinessSettings::get("business_{$key}_url", '');
        if ($url) {
            $policy_links[] = [
                'label' => $label,
                'url' => $url[0] === '/' ? BusinessSettings::getSiteUrl($url) : $url
            ];
        }
    }

    // 10. Receipt Data Assembly
    $receipt_data = [
        'order_id' => (string) $order_id,
        'date' => date('M d, Y', strtotime($order['created_at'] ?? 'now')),
        'payment_status' => (string) ($order['payment_status'] ?? 'pending'),
        'items' => $itemsData,
        'subtotal' => number_format($itemsSubtotal, 2),
        'discount' => number_format($discount_amount, 2),
        'coupon_code' => !empty($order['coupon_code']) ? (string) $order['coupon_code'] : null,
        'shipping' => number_format($shipping_amount, 2),
        'tax' => number_format($tax_amount, 2),
        'total' => number_format($total_amount, 2),
        'receipt_message' => getReceiptMessage($order, $orderItems),
        'sales_verbiage' => getSalesVerbiage(),
        'business_info' => $business_info,
        'policy_links' => $policy_links
    ];

    Response::success($receipt_data);

} catch (Throwable $e) {
    // Log to a safer location
    error_log("[pos_receipt.php] Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    Response::serverError($e->getMessage(), [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
