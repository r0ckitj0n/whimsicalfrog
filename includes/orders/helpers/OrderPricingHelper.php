<?php
// includes/orders/helpers/OrderPricingHelper.php

require_once __DIR__ . '/../../Constants.php';

class OrderPricingHelper
{
    private static $salePctCache = [];

    /**
     * Compute subtotal, shipping, tax, and total for an order
     */
    public static function computePricing($item_ids, $quantities, $shipping_method, $shipping_address, $coupon_code, $debug = false, $user_id = null)
    {
        $subtotal = 0.0;
        $itemsDebug = [];
        $sale_items_sku_col = self::getSaleItemsSkuColumn();

        for ($i = 0; $i < count($item_ids); $i++) {
            $sku = $item_ids[$i];
            $qty = (int) ($quantities[$i] ?? 0);
            if (!$sku || $qty <= 0)
                continue;

            $pricing = self::resolveItemPrice($sku);
            $effectivePrice = $pricing['price'];
            $sale_pct = self::getActiveSalePct($pricing['sku'], $sale_items_sku_col);

            if ($sale_pct > 0 && $effectivePrice > 0) {
                $effectivePrice = round($effectivePrice * (1 - ($sale_pct / 100)), 2);
            }

            $subtotal += $effectivePrice * $qty;

            if ($debug) {
                $itemsDebug[] = [
                    'sku' => $sku,
                    'lookupSku' => $pricing['sku'],
                    'qty' => $qty,
                    'price' => $effectivePrice,
                    'extended' => round($effectivePrice * $qty, 2),
                    'sale_pct' => $sale_pct,
                ];
            }
        }

        // Fetch VIP status
        $isVip = false;
        if ($user_id) {
            require_once __DIR__ . '/../../user_meta.php';
            $meta = get_user_meta_bulk($user_id);
            $isVip = ($meta['vip'] ?? '0') === '1';
        }

        $shipping = self::calculateShipping($subtotal, $shipping_method, $isVip);
        $coupon = self::calculateCoupon($subtotal, $coupon_code);
        $tax = self::calculateTax($subtotal, $coupon['discount'], $shipping, $shipping_address);

        $total = round($subtotal - $coupon['discount'] + $shipping + $tax['amount'], 2);

        $result = [
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'discount' => round($coupon['discount'], 2),
            'coupon' => $coupon['code'],
            'tax' => $tax['amount'],
            'tax_details' => $tax,
            'total' => $total
        ];

        if ($debug) {
            $result['items_debug'] = $itemsDebug;
        }

        return $result;
    }

    /**
     * Resolve item price with SKU normalization fallback
     */
    public static function resolveItemPrice($sku)
    {
        $row = Database::queryOne("SELECT retail_price FROM items WHERE sku = ?", [$sku]);
        if ($row && (float) $row['retail_price'] > 0) {
            return ['sku' => $sku, 'price' => (float) $row['retail_price']];
        }

        // Normalization fallback
        $candidates = self::getSkuCandidates($sku);
        foreach ($candidates as $cand) {
            $rowCand = Database::queryOne("SELECT retail_price FROM items WHERE sku = ?", [$cand]);
            if ($rowCand && (float) $rowCand['retail_price'] > 0) {
                return ['sku' => $cand, 'price' => (float) $rowCand['retail_price']];
            }
        }

        return ['sku' => $sku, 'price' => 0.0];
    }

    private static function getSkuCandidates($sku)
    {
        $candidates = [];
        $skuStr = (string) $sku;
        $bases = [$skuStr];

        $lettersStripped = preg_replace('/[A-Za-z]+$/', '', $skuStr);
        if ($lettersStripped !== $skuStr)
            $bases[] = $lettersStripped;

        foreach (array_unique(array_filter($bases)) as $base) {
            $current = $base;
            while (true) {
                $ls = preg_replace('/[A-Za-z]+$/', '', $current);
                if ($ls !== $current)
                    $candidates[] = $ls;
                if (strpos($current, '-') === false)
                    break;
                $current = preg_replace('/-[^-]*$/', '', $current);
                if ($current)
                    $candidates[] = $current;
            }
        }
        return array_unique($candidates);
    }

    private static function getSaleItemsSkuColumn()
    {
        try {
            return Database::queryOne("SHOW COLUMNS FROM sale_items LIKE 'sku'") ? 'sku' : 'item_sku';
        } catch (Throwable $e) {
            return 'sku';
        }
    }

    private static function getActiveSalePct($sku, $skuCol)
    {
        if (isset(self::$salePctCache[$sku]))
            return self::$salePctCache[$sku];

        try {
            $row = Database::queryOne(
                "SELECT MAX(s.discount_percentage) AS discount_percentage
                 FROM sales s JOIN sale_items si ON s.id = si.sale_id
                 WHERE si.`$skuCol` = ? AND s.is_active = 1 AND NOW() BETWEEN s.start_date AND s.end_date",
                [$sku]
            );
            $pct = $row ? (float) $row['discount_percentage'] : 0.0;
        } catch (Throwable $e) {
            $pct = 0.0;
        }

        return self::$salePctCache[$sku] = $pct;
    }

    public static function calculateShipping($subtotal, $method, $isVip = false)
    {
        if ($isVip)
            return 0.0;
        $cfg = BusinessSettings::getShippingConfig(false);
        $freeThreshold = (float) $cfg['free_shipping_threshold'];

        if ($method === WF_Constants::SHIPPING_METHOD_PICKUP)
            return 0.0;
        if ($method === WF_Constants::SHIPPING_METHOD_LOCAL)
            return 75.00;
        if ($freeThreshold > 0 && $subtotal >= $freeThreshold)
            return 0.0;

        $rates = [
            WF_Constants::SHIPPING_METHOD_USPS => (float) $cfg['shipping_rate_usps'],
            WF_Constants::SHIPPING_METHOD_FEDEX => (float) $cfg['shipping_rate_fedex'],
            WF_Constants::SHIPPING_METHOD_UPS => (float) $cfg['shipping_rate_ups']
        ];

        return $rates[$method] ?? $rates[WF_Constants::SHIPPING_METHOD_USPS];
    }

    public static function calculateCoupon($subtotal, $code)
    {
        if (!$code)
            return ['code' => null, 'discount' => 0.0];

        $coupon = Database::queryOne("SELECT * FROM coupons WHERE code = ? AND active = 1", [$code]);
        if (!$coupon)
            return ['code' => $code, 'discount' => 0.0];

        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time())
            return ['code' => $code, 'discount' => 0.0];
        if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit'])
            return ['code' => $code, 'discount' => 0.0];
        if ($coupon['min_order_amount'] > 0 && $subtotal < $coupon['min_order_amount'])
            return ['code' => $code, 'discount' => 0.0];

        $discount = ($coupon['type'] === WF_Constants::COUPON_TYPE_PERCENTAGE)
            ? $subtotal * ($coupon['value'] / 100)
            : (float) $coupon['value'];

        return [
            'code' => $code,
            'discount' => min($discount, $subtotal)
        ];
    }

    public static function calculateTax($subtotal, $discount, $shipping, $address)
    {
        $cfg = BusinessSettings::getTaxConfig(false);
        if (!(bool) $cfg['enabled'])
            return ['amount' => 0.0, 'rate' => 0.0, 'source' => 'disabled'];

        $zip = trim((string) ($address['zip_code'] ?? ''));
        if (!$zip)
            $zip = (string) BusinessSettings::getBusinessPostal();

        $rate = 0.0;
        $source = 'settings';

        if ($zip) {
            $zipRate = (float) TaxService::getTaxRateForZip($zip);
            if ($zipRate > 0) {
                $rate = $zipRate;
                $source = 'zip';
            }
        }

        if ($rate === 0.0) {
            $rate = (float) $cfg['rate'];
        }

        $taxBase = max(0, $subtotal - $discount) + ((bool) $cfg['taxShipping'] ? $shipping : 0.0);
        return [
            'amount' => round($taxBase * $rate, 2),
            'rate' => $rate,
            'source' => $source,
            'zip' => $zip,
            'taxBase' => $taxBase
        ];
    }
}
