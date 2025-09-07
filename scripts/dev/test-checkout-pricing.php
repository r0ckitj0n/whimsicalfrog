<?php
// CLI test for checkout pricing using DB-driven settings (mirrors api/checkout_pricing.php)
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';
require_once __DIR__ . '/../../includes/tax_service.php';

$inputJson = $argv[1] ?? '';
$input = $inputJson ? json_decode($inputJson, true) : null;
if (!$input) {
    // Default sample
    $input = [
        'itemIds' => ['WF-AR-001A'],
        'quantities' => [1],
        'shippingMethod' => 'USPS',
        'zip' => '15301',
        'debug' => true,
    ];
}

try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    fwrite(STDERR, "DB error: " . $e->getMessage() . "\n");
    exit(1);
}

$itemIds = $input['itemIds'] ?? [];
$quantities = $input['quantities'] ?? [];
$shippingMethod = $input['shippingMethod'] ?? 'Customer Pickup';
$zip = isset($input['zip']) ? trim((string)$input['zip']) : null;
$debug = !empty($input['debug']);

if (!is_array($itemIds) || !is_array($quantities) || count($itemIds) !== count($quantities)) {
    echo json_encode(['success' => false, 'error' => 'Invalid items or quantities']);
    exit(0);
}

// Subtotal with SKU normalization logic
$subtotal = 0.0;
$itemsDebug = [];
for ($i = 0; $i < count($itemIds); $i++) {
    $sku = (string)$itemIds[$i];
    $qty = (int)($quantities[$i] ?? 0);
    if (!$sku || $qty <= 0) continue;

    $effectiveSku = $sku;
    $row = Database::queryOne('SELECT retailPrice FROM items WHERE sku = ?', [$effectiveSku]);
    $price = $row ? $row['retailPrice'] : null;

    if ($price === false || $price === null || (float)$price <= 0.0) {
        $candidates = [];
        $skuStr = (string)$sku;
        $bases = [$skuStr];
        $lettersStripped = preg_replace('/[A-Za-z]+$/', '', $skuStr);
        if ($lettersStripped !== $skuStr) { $bases[] = $lettersStripped; }
        foreach (array_unique(array_filter($bases)) as $base) {
            $current = $base;
            while (true) {
                $ls = preg_replace('/[A-Za-z]+$/', '', $current);
                if ($ls !== $current) { $candidates[] = $ls; }
                if (strpos($current, '-') === false) { break; }
                $current = preg_replace('/-[^-]*$/', '', $current);
                if ($current) { $candidates[] = $current; }
            }
        }
        foreach ($candidates as $cand) {
            $r2 = Database::queryOne('SELECT retailPrice FROM items WHERE sku = ?', [$cand]);
            $candPrice = $r2 ? $r2['retailPrice'] : null;
            if ($candPrice !== false && $candPrice !== null && (float)$candPrice > 0.0) {
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

// Shipping settings (required)
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
    if ($v === null || $v === '') { throw new RuntimeException("Missing required setting: {$k}"); }
    if (!is_numeric($v)) { throw new RuntimeException("Invalid numeric setting: {$k}"); }
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
    $shipping = $rateUSPS; // default flat USPS
}

// Tax
$taxShippingVal = BusinessSettings::get('tax_shipping');
if ($taxShippingVal === null || $taxShippingVal === '') { throw new RuntimeException('Missing required setting: tax_shipping'); }
$taxShipping = in_array(strtolower((string)$taxShippingVal), ['1','true','yes'], true);

$settingsEnabled = (bool) BusinessSettings::isTaxEnabled();
$settingsRateRaw = BusinessSettings::getTaxRate();
if ($settingsEnabled) {
    if ($settingsRateRaw === null || $settingsRateRaw === '') { throw new RuntimeException('Missing required setting: tax_rate'); }
    if (!is_numeric($settingsRateRaw)) { throw new RuntimeException('Invalid numeric setting: tax_rate'); }
}
$settingsRate = (float)$settingsRateRaw;
$zipForTax = $zip ?: (string) BusinessSettings::get('business_zip', '');
$zipState = null;
$zipRate = null;
$taxSource = 'settings';
if (!empty($zipForTax)) {
    $zipRate = (float) TaxService::getTaxRateForZip($zipForTax);
    $zipState = TaxService::lookupStateByZip($zipForTax);
    if ($zipRate > 0) { $taxSource = 'zip'; }
}
$rateToUse = ($zipRate !== null && $zipRate > 0) ? $zipRate : (($settingsEnabled && $settingsRate > 0) ? $settingsRate : 0.0);
$taxEnabled = $rateToUse > 0;
$taxBase = $subtotal + ($taxShipping ? $shipping : 0.0);
$tax = $taxEnabled ? round($taxBase * $rateToUse, 2) : 0.0;

// Total
$total = round($subtotal + $shipping + $tax, 2);

$currency = BusinessSettings::get('currency_code');
if (!$currency) { throw new RuntimeException('Missing required setting: currency_code'); }

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
}

echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
exit(0);
