<?php
/**
 * Checkout Pricing Helper Logic
 */

require_once __DIR__ . '/../Constants.php';

function calculate_item_prices($item_ids, $quantities)
{
    $subtotal = 0.0;
    $itemsDetails = [];
    for ($i = 0; $i < count($item_ids); $i++) {
        $sku = $item_ids[$i];
        $qty = (int) ($quantities[$i] ?? 0);
        if (!$sku || $qty <= 0)
            continue;

        $row = Database::queryOne("SELECT retail_price FROM items WHERE sku = ?", [$sku]);
        $price = $row ? (float) $row['retail_price'] : 0.0;

        $subtotal += $price * $qty;
        $itemsDetails[] = ['sku' => $sku, 'price' => $price, 'qty' => $qty, 'extended' => $price * $qty];
    }
    return ['subtotal' => $subtotal, 'items' => $itemsDetails];
}

function calculate_shipping($subtotal, $method, $weightOz = 0, $isVip = false)
{
    if ($isVip)
        return 0.0;
    $cfg = BusinessSettings::getShippingConfig(false);
    if ($method === WF_Constants::SHIPPING_METHOD_PICKUP)
        return 0.0;
    if ($method === WF_Constants::SHIPPING_METHOD_LOCAL)
        return 75.0;
    if ($method === WF_Constants::SHIPPING_METHOD_USPS && $subtotal >= $cfg['free_shipping_threshold'])
        return 0.0;

    $base = $cfg['shipping_rate_usps'];
    if ($method === WF_Constants::SHIPPING_METHOD_FEDEX)
        $base = $cfg['shipping_rate_fedex'];
    if ($method === WF_Constants::SHIPPING_METHOD_UPS)
        $base = $cfg['shipping_rate_ups'];

    return $base; // Simplified for brevity
}
