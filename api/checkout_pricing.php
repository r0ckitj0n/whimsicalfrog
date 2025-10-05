<?php

// Checkout Pricing API
// Computes subtotal, shipping, tax, and total for a proposed checkout

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/tax_service.php';
require_once __DIR__ . '/../includes/response.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        Response::error('Invalid JSON', null, 400);
    }

    $itemIds = $input['itemIds'] ?? [];
    $quantities = $input['quantities'] ?? [];
    $shippingMethod = $input['shippingMethod'] ?? 'USPS';
    $zip = isset($input['zip']) ? trim((string)$input['zip']) : null;
    $debug = !empty($input['debug']);

    if (!is_array($itemIds) || !is_array($quantities) || count($itemIds) !== count($quantities)) {
        Response::error('Invalid items or quantities', null, 400);
    }

    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        if (class_exists('Logger')) {
            Logger::exception('Database error in checkout_pricing', $e, [
                'endpoint' => 'checkout_pricing',
                'stage' => 'db_connect',
            ]);
        }
        Response::serverError('Database error');
    }

    // Compute subtotal based on current item prices
    $subtotal = 0.0;
    $itemsDebug = [];

    for ($i = 0; $i < count($itemIds); $i++) {
        $sku = $itemIds[$i];
        $qty = (int)($quantities[$i] ?? 0);
        if (!$sku || $qty <= 0) {
            continue;
        }
        // Attempt primary and normalized price lookups
        $effectiveSku = $sku;
        $rowPrice = Database::queryOne('SELECT retailPrice FROM items WHERE sku = ?', [$effectiveSku]);
        $price = $rowPrice ? $rowPrice['retailPrice'] : null;

        // Build candidate SKUs by stripping trailing letters and progressively removing hyphenated segments
        if ($price === false || $price === null || (float)$price <= 0.0) {
            $candidates = [];
            $skuStr = (string)$sku;
            $bases = [];
            $bases[] = $skuStr;
            $lettersStripped = preg_replace('/[A-Za-z]+$/', '', $skuStr);
            if ($lettersStripped !== $skuStr) {
                $bases[] = $lettersStripped;
            }
            // From each base, progressively drop the last hyphen segment, and for each step also add letters-stripped variant
            foreach (array_unique(array_filter($bases)) as $base) {
                $current = $base;
                while (true) {
                    // Add letters-stripped variant if applicable
                    $ls = preg_replace('/[A-Za-z]+$/', '', $current);
                    if ($ls !== $current) {
                        $candidates[] = $ls;
                    }
                    // Drop last hyphen segment
                    if (strpos($current, '-') === false) {
                        break;
                    }
                    $current = preg_replace('/-[^-]*$/', '', $current);
                    if ($current) {
                        $candidates[] = $current;
                    }
                }
            }

            foreach ($candidates as $cand) {
                $rowCand = Database::queryOne('SELECT retailPrice FROM items WHERE sku = ?', [$cand]);
                $candPrice = $rowCand ? $rowCand['retailPrice'] : null;
                if ($candPrice !== false && $candPrice !== null && (float)$candPrice > 0.0) {
                    if (class_exists('Logger')) {
                        Logger::info('Normalized SKU for pricing lookup', [
                            'endpoint' => 'checkout_pricing',
                            'original_sku' => (string)$sku,
                            'normalized_sku' => (string)$cand,
                        ]);
                    }
                    $effectiveSku = $cand;
                    $price = $candPrice;
                    break;
                }
            }
        }

        if ($price === false || $price === null) {
            $price = 0.0;
        }

        $itemsDebug[] = [
            'sku' => (string)$sku,
            'lookupSku' => (string)$effectiveSku,
            'qty' => $qty,
            'price' => (float)$price,
            'extended' => round(((float)$price) * $qty, 2)
        ];
        $subtotal += ((float)$price) * $qty;
    }

    // Shipping rates and logic (non-strict: allow sensible defaults during development)
    // We intentionally avoid throwing here to prevent checkout from failing when settings are missing.
    $shipCfg = BusinessSettings::getShippingConfig(false);
    $freeThreshold   = (float)$shipCfg['free_shipping_threshold'];
    $localDeliveryFee = (float)$shipCfg['local_delivery_fee'];
    $rateUSPS        = (float)$shipCfg['shipping_rate_usps'];
    $rateFedEx       = (float)$shipCfg['shipping_rate_fedex'];
    $rateUPS         = (float)$shipCfg['shipping_rate_ups'];

    $shipping = 0.0;
    $method = (string)$shippingMethod;

    // Shipping rules:
    // - Customer Pickup: always free ($0)
    // - Local Delivery: flat fee (override to 75.00 regardless of free threshold)
    // - USPS: eligible for free shipping threshold
    // - FedEx/UPS: NOT eligible for free shipping threshold
    if ($method === 'Customer Pickup') {
        $shipping = 0.0;
    } elseif ($method === 'Local Delivery') {
        $shipping = 75.00; // Flat fee, never free
    } elseif ($method === 'USPS' && $subtotal >= $freeThreshold && $freeThreshold > 0) {
        $shipping = 0.0;
    } elseif ($method === 'USPS') {
        $shipping = $rateUSPS;
    } elseif ($method === 'FedEx') {
        $shipping = $rateFedEx;
    } elseif ($method === 'UPS') {
        $shipping = $rateUPS;
    } else {
        // Unknown method -> default to flat USPS rate
        $shipping = $rateUSPS;
    }

    // Tax logic (non-strict: allow operation even if some settings are missing)
    $taxCfg = BusinessSettings::getTaxConfig(false);
    $taxShipping = (bool)$taxCfg['taxShipping'];
    $settingsEnabled = (bool)$taxCfg['enabled'];
    $settingsRate = (float)$taxCfg['rate'];
    // Prefer Business Info postal code; fallback to legacy business_zip
    $bizPostal = (string) BusinessSettings::get('business_postal', '');
    $bizZipLegacy = (string) BusinessSettings::get('business_zip', '');
    $zipForTax = $zip ?: ($bizPostal !== '' ? $bizPostal : $bizZipLegacy);
    $zipState = null;
    $zipRate = null;
    $taxSource = 'settings';
    if (!empty($zipForTax)) {
        $zipRate = (float) TaxService::getTaxRateForZip($zipForTax);
        $zipState = TaxService::lookupStateByZip($zipForTax);
        if ($zipRate > 0) {
            $taxSource = 'zip';
        }
    }
    // Choose rate: prefer ZIP rate; else settings; else fallback 7%
    $fallbackRate = 0.07;
    if ($zipRate !== null && $zipRate > 0) {
        $rateToUse = $zipRate;
        $taxSource = 'zip';
    } elseif ($settingsEnabled && $settingsRate > 0) {
        $rateToUse = $settingsRate;
        $taxSource = 'settings';
    } else {
        $rateToUse = $fallbackRate;
        $taxSource = 'fallback';
    }
    $taxEnabled = $rateToUse > 0;
    $taxBase = $subtotal + ($taxShipping ? $shipping : 0.0);
    $tax = $taxEnabled ? round($taxBase * $rateToUse, 2) : 0.0;

    // Total
    $total = round($subtotal + $shipping + $tax, 2);

    // Currency (fallback to USD if not configured to avoid hard failure in dev)
    $currency = BusinessSettings::get('currency_code', 'USD');

    $response = [
        'pricing' => [
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,
            'zip' => $zip,
            'shippingMethod' => $method,
            'freeShippingThreshold' => $freeThreshold,
        ]
    ];

    if ($debug) {
        $response['debug'] = [
            'taxEnabled' => $taxEnabled,
            'taxRate' => $rateToUse,
            'taxShipping' => $taxShipping,
            'taxBase' => round($taxBase, 2),
            'taxSource' => $taxSource,
            'zipUsed' => $zipForTax,
            'zipState' => $zipState,
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'method' => $method,
            'freeShippingThreshold' => $freeThreshold,
            'items' => $itemsDebug,
            // Which shipping settings fell back to defaults (if any)
            'shippingUsedDefaults' => isset($shipCfg['usedDefaults']) ? (array)$shipCfg['usedDefaults'] : [],
            'hasTaxShippingKey' => isset($taxCfg['hasTaxShippingKey']) ? (bool)$taxCfg['hasTaxShippingKey'] : null,
            'settingsEnabled' => $settingsEnabled,
            'settingsRate' => $settingsRate,
        ];
        if (class_exists('Logger')) {
            Logger::debug('checkout_pricing debug payload', [
                'endpoint' => 'checkout_pricing',
                'debug' => $response['debug']
            ]);
        }
    }

    Response::success($response);

} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::exception('Unhandled error in checkout_pricing', $e, [
            'endpoint' => 'checkout_pricing',
        ]);
    }
    Response::serverError('Server error');
}
