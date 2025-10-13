<?php
// Create a quick test order in dev and render email/receipt info to stdout
// Usage: php scripts/dev/create-test-order.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

function pickSku() {
    // Prefer any item with positive retailPrice; avoid relying on updated_at column
    $row = Database::queryOne("SELECT sku, retailPrice FROM items WHERE retailPrice > 0 LIMIT 1");
    if ($row && !empty($row['sku'])) return $row['sku'];
    $row = Database::queryOne("SELECT sku FROM items LIMIT 1");
    return $row ? $row['sku'] : 'WF-AR-001A';
}

try {
    Database::getInstance();
    $sku = pickSku();
    if (!$sku) { throw new Exception('No SKU found in items table'); }

    // Minimal inputs
    $customerId = 'U999_TEST';
    $paymentMethod = 'Cash';
    $shippingMethod = 'USPS';
    $shippingAddress = [
        'address_line1' => '91 Singletree Ln',
        'address_line2' => '',
        'city' => 'Dawsonville',
        'state' => 'GA',
        'zip_code' => '30534',
        'country' => 'US',
    ];

    // Pricing lookups
    $row = Database::queryOne('SELECT retailPrice, name FROM items WHERE sku = ?', [$sku]);
    $price = $row && isset($row['retailPrice']) ? (float)$row['retailPrice'] : 10.00;
    $subtotal = $price;

    $shipCfg = BusinessSettings::getShippingConfig(false);
    $freeThreshold = (float)($shipCfg['free_shipping_threshold'] ?? 0);
    $rateUSPS = (float)($shipCfg['shipping_rate_usps'] ?? 8.99);
    $shipping = ($shippingMethod === 'USPS' && $subtotal >= $freeThreshold && $freeThreshold > 0) ? 0.0 : $rateUSPS;

    $taxCfg = BusinessSettings::getTaxConfig(false);
    $taxShipping = (bool)($taxCfg['taxShipping'] ?? false);
    $zip = $shippingAddress['zip_code'] ?? BusinessSettings::getBusinessPostal();
    $rateToUse = 0.0;
    if (class_exists('TaxService')) {
        require_once __DIR__ . '/../../includes/tax_service.php';
        $zipRate = (float) TaxService::getTaxRateForZip($zip);
        if ($zipRate > 0) $rateToUse = $zipRate;
    }
    if ($rateToUse <= 0 && (bool)($taxCfg['enabled'] ?? false) && (float)($taxCfg['rate'] ?? 0) > 0) {
        $rateToUse = (float)$taxCfg['rate'];
    }
    $taxBase = $subtotal + ($taxShipping ? $shipping : 0.0);
    $tax = $rateToUse > 0 ? round($taxBase * $rateToUse, 2) : 0.0;

    $total = round($subtotal + $shipping + $tax, 2);

    // Build compact orderId similar to api/add_order.php
    $monthLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
    $customerNum = '99';
    $monthLetter = $monthLetters[date('n') - 1];
    $day = date('d');
    $shippingCode = 'U';
    $suffix = str_pad((string)rand(1, 99), 2, '0', STR_PAD_LEFT);
    $orderId = $customerNum . $monthLetter . $day . $shippingCode . $suffix;

    Database::beginTransaction();
    // Insert order
    $sql = "INSERT INTO orders (id, userId, total, paymentMethod, order_status, date, paymentStatus, shippingMethod, shippingAddress) VALUES (?,?,?,?,?,?,?,?,?)";
    $ok = Database::execute($sql, [
        $orderId, $customerId, $total, $paymentMethod, 'Processing', date('Y-m-d H:i:s'), 'Pending', $shippingMethod, json_encode($shippingAddress)
    ]);
    // Insert order item
    $oiSql = "INSERT INTO order_items (id, orderId, sku, quantity, price, color) VALUES (?,?,?,?,?,?)";
    $oiId = 'OI' . (int)(microtime(true) * 1000);
    Database::execute($oiSql, [$oiId, $orderId, $sku, 1, $price, null]);
    Database::commit();

    // Render email template to stdout (order confirmation)
    $order = [ 'id' => $orderId, 'total' => $total ];
    $customer = [ 'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'test@example.com' ];
    $items = [ [ 'sku' => $sku, 'name' => ($row['name'] ?? $sku), 'price' => $price, 'quantity' => 1 ] ];

    ob_start();
    include __DIR__ . '/../../templates/email/order_confirmation.php';
    $emailHtml = ob_get_clean();

    echo "Created test order: {$orderId}\n";
    echo "Receipt URL: /receipt?orderId={$orderId}\n";
    echo "--- Email (order_confirmation) HTML preview ---\n";
    echo $emailHtml, "\n";
    exit(0);
} catch (Throwable $e) {
    // Avoid strict logger signature issues in dev scripts
    fwrite(STDERR, '[ERROR] create-test-order: ' . $e->getMessage() . "\n");
    exit(1);
}
