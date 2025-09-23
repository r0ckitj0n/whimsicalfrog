<?php

// Test script to check shipping configuration
require_once __DIR__ . '/../api/config.php';

try {
    $pdo = Database::getInstance();

    echo "Current shipping configuration:\n";
    echo "================================\n";

    $shippingKeys = [
        'free_shipping_threshold',
        'local_delivery_fee',
        'shipping_rate_usps',
        'shipping_rate_fedex',
        'shipping_rate_ups'
    ];

    foreach ($shippingKeys as $key) {
        $result = Database::queryOne("SELECT setting_value, category FROM business_settings WHERE setting_key = ?", [$key]);
        if ($result) {
            echo "$key: {$result['setting_value']} ({$result['category']})\n";
        } else {
            echo "$key: NOT FOUND\n";
        }
    }

    echo "\n";

    // Test the BusinessSettings helper
    echo "BusinessSettings helper values:\n";
    echo "===============================\n";

    $shipCfg = BusinessSettings::getShippingConfig(false);
    echo "free_shipping_threshold: " . $shipCfg['free_shipping_threshold'] . "\n";
    echo "local_delivery_fee: " . $shipCfg['local_delivery_fee'] . "\n";
    echo "shipping_rate_usps: " . $shipCfg['shipping_rate_usps'] . "\n";
    echo "shipping_rate_fedex: " . $shipCfg['shipping_rate_fedex'] . "\n";
    echo "shipping_rate_ups: " . $shipCfg['shipping_rate_ups'] . "\n";

    if (!empty($shipCfg['usedDefaults'])) {
        echo "Used defaults: " . implode(', ', $shipCfg['usedDefaults']) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
