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
    // Keep legacy helper aligned with OrderPricingHelper (USPS-only free threshold).
    require_once __DIR__ . '/../orders/helpers/OrderPricingHelper.php';
    return OrderPricingHelper::calculateShipping($subtotal, $method, $isVip);
}
