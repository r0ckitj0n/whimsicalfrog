<?php
// Checkout Pricing API
// Computes subtotal, shipping, tax, and total for a proposed checkout

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/tax_service.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $itemIds = $input['itemIds'] ?? [];
    $quantities = $input['quantities'] ?? [];
    $shippingMethod = $input['shippingMethod'] ?? 'Customer Pickup';
    $zip = isset($input['zip']) ? trim((string)$input['zip']) : null;
    $debug = !empty($input['debug']);

    if (!is_array($itemIds) || !is_array($quantities) || count($itemIds) !== count($quantities)) {
        echo json_encode(['success' => false, 'error' => 'Invalid items or quantities']);
        exit;
    }

    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("checkout_pricing.php: DB error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }

    // Compute subtotal based on current item prices
    $subtotal = 0.0;
    $priceStmt = $pdo->prepare('SELECT retailPrice FROM items WHERE sku = ?');
    $itemsDebug = [];

    for ($i = 0; $i < count($itemIds); $i++) {
        $sku = $itemIds[$i];
        $qty = (int)($quantities[$i] ?? 0);
        if (!$sku || $qty <= 0) continue;
        // Attempt primary and normalized price lookups
        $effectiveSku = $sku;
        $priceStmt->execute([$effectiveSku]);
        $price = $priceStmt->fetchColumn();

        // Build candidate SKUs by stripping trailing letters and progressively removing hyphenated segments
        if ($price === false || $price === null || (float)$price <= 0.0) {
            $candidates = [];
            $skuStr = (string)$sku;
            $bases = [];
            $bases[] = $skuStr;
            $lettersStripped = preg_replace('/[A-Za-z]+$/', '', $skuStr);
            if ($lettersStripped !== $skuStr) { $bases[] = $lettersStripped; }
            // From each base, progressively drop the last hyphen segment, and for each step also add letters-stripped variant
            foreach (array_unique(array_filter($bases)) as $base) {
                $current = $base;
                while (true) {
                    // Add letters-stripped variant if applicable
                    $ls = preg_replace('/[A-Za-z]+$/', '', $current);
                    if ($ls !== $current) { $candidates[] = $ls; }
                    // Drop last hyphen segment
                    if (strpos($current, '-') === false) { break; }
                    $current = preg_replace('/-[^-]*$/', '', $current);
                    if ($current) { $candidates[] = $current; }
                }
            }

            foreach ($candidates as $cand) {
                $priceStmt->execute([$cand]);
                $candPrice = $priceStmt->fetchColumn();
                if ($candPrice !== false && $candPrice !== null && (float)$candPrice > 0.0) {
                    error_log("checkout_pricing.php: Normalized SKU '$sku' -> '$cand' for pricing lookup");
                    $effectiveSku = $cand;
                    $price = $candPrice;
                    break;
                }
            }
        }

        if ($price === false || $price === null) { $price = 0.0; }

        $itemsDebug[] = [
            'sku' => (string)$sku,
            'lookupSku' => (string)$effectiveSku,
            'qty' => $qty,
            'price' => (float)$price,
            'extended' => round(((float)$price) * $qty, 2)
        ];
        $subtotal += ((float)$price) * $qty;
    }

    // Shipping rates and logic (no code fallbacks)
    $freeThresholdRaw   = BusinessSettings::get('free_shipping_threshold');
    $localDeliveryRaw   = BusinessSettings::get('local_delivery_fee');
    $rateUSPSRaw        = BusinessSettings::get('shipping_rate_usps');
    $rateFedExRaw       = BusinessSettings::get('shipping_rate_fedex');
    $rateUPSRaw         = BusinessSettings::get('shipping_rate_ups');

    $requiredShipping = [
        'free_shipping_threshold' => $freeThresholdRaw,
        'local_delivery_fee' => $localDeliveryRaw,
        'shipping_rate_usps' => $rateUSPSRaw,
        'shipping_rate_fedex' => $rateFedExRaw,
        'shipping_rate_ups' => $rateUPSRaw,
    ];
    foreach ($requiredShipping as $k => $v) {
        if ($v === null || $v === '') {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => "Missing required setting: {$k}"]);
            exit;
        }
        if (!is_numeric($v)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => "Invalid numeric setting: {$k}"]);
            exit;
        }
    }

    $freeThreshold   = (float)$freeThresholdRaw;
    $localDeliveryFee= (float)$localDeliveryRaw;
    $rateUSPS        = (float)$rateUSPSRaw;
    $rateFedEx       = (float)$rateFedExRaw;
    $rateUPS         = (float)$rateUPSRaw;

    $shipping = 0.0;
    $method = (string)$shippingMethod;

    if ($method === 'Customer Pickup') {
        $shipping = 0.0;
    } elseif ($subtotal >= $freeThreshold && $freeThreshold > 0) {
        $shipping = 0.0;
    } elseif ($method === 'Local Delivery') {
        $shipping = $localDeliveryFee;
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

    // Tax logic (no code fallbacks)
    $taxShippingVal = BusinessSettings::get('tax_shipping');
    if ($taxShippingVal === null || $taxShippingVal === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Missing required setting: tax_shipping']);
        exit;
    }
    $taxShipping = in_array(strtolower((string)$taxShippingVal), ['1','true','yes'], true);

    $settingsEnabled = (bool) BusinessSettings::isTaxEnabled();
    $settingsRateRaw = BusinessSettings::getTaxRate();
    if ($settingsEnabled) {
        if ($settingsRateRaw === null || $settingsRateRaw === '') {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Missing required setting: tax_rate']);
            exit;
        }
        if (!is_numeric($settingsRateRaw)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Invalid numeric setting: tax_rate']);
            exit;
        }
    }
    $settingsRate = (float)$settingsRateRaw;
    $zipForTax = $zip ?: (string) BusinessSettings::get('business_zip', '');
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
    // Choose rate: prefer positive ZIP rate; otherwise use settings rate if enabled and > 0
    $rateToUse = ($zipRate !== null && $zipRate > 0)
        ? $zipRate
        : (($settingsEnabled && $settingsRate > 0) ? $settingsRate : 0.0);
    $taxEnabled = $rateToUse > 0;
    $taxBase = $subtotal + ($taxShipping ? $shipping : 0.0);
    $tax = $taxEnabled ? round($taxBase * $rateToUse, 2) : 0.0;

    // Total
    $total = round($subtotal + $shipping + $tax, 2);

    // Currency (no code fallback)
    $currency = BusinessSettings::get('currency_code');
    if (!$currency) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Missing required setting: currency_code']);
        exit;
    }

    $response = [
        'success' => true,
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
        ];
        error_log('checkout_pricing.php debug: ' . json_encode($response['debug']));
    }

    echo json_encode($response);

} catch (Throwable $e) {
    error_log('checkout_pricing.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
