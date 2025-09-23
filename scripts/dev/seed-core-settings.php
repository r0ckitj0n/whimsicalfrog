<?php

// Seed core BusinessSettings: site URLs, shipping, tax, currency
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

try {
    Database::getInstance();

    $rows = [
        // Site URLs
        ['site', 'site_base_url', 'https://whimsicalfrog.us', 'text', 'Site Base URL', 'Canonical base URL for building links in emails and templates.'],
        ['site', 'admin_base_url', 'https://whimsicalfrog.us/?page=admin', 'text', 'Admin Base URL', 'Admin base URL (should include ? or & for query parameters).'],

        // Shipping (ecommerce)
        ['ecommerce', 'free_shipping_threshold', '50.00', 'number', 'Free Shipping Threshold', 'Subtotal threshold for free shipping.'],
        ['ecommerce', 'local_delivery_fee', '5.00', 'number', 'Local Delivery Fee', 'Flat fee for local delivery.'],
        ['ecommerce', 'shipping_rate_usps', '7.50', 'number', 'USPS Flat Rate', 'Flat USPS shipping rate.'],
        ['ecommerce', 'shipping_rate_fedex', '12.00', 'number', 'FedEx Flat Rate', 'Flat FedEx shipping rate.'],
        ['ecommerce', 'shipping_rate_ups', '14.00', 'number', 'UPS Flat Rate', 'Flat UPS shipping rate.'],

        // Tax (ecommerce)
        ['ecommerce', 'tax_enabled', 'true', 'boolean', 'Tax Enabled', 'Enable applying sales tax.'],
        ['ecommerce', 'tax_rate', '0.065', 'number', 'Default Tax Rate', 'Default sales tax rate when ZIP lookup not available.'],
        ['ecommerce', 'tax_shipping', 'true', 'boolean', 'Tax Shipping', 'Whether shipping is taxable.'],
        ['ecommerce', 'business_zip', '15301', 'text', 'Business ZIP', 'Business ZIP used for default tax lookup.'],

        // Currency (ecommerce)
        ['ecommerce', 'currency_code', 'USD', 'text', 'Currency Code', 'ISO currency code for pricing.'],
    ];

    $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), display_name = VALUES(display_name), description = VALUES(description), updated_at = NOW()";

    $saved = 0;
    foreach ($rows as $r) {
        $affected = Database::execute($sql, $r);
        if ($affected >= 0) {
            $saved++;
        }
    }

    // Clear cache
    if (class_exists('BusinessSettings')) {
        BusinessSettings::clearCache();
    }

    echo "Seeded {$saved} settings.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error seeding settings: ' . $e->getMessage() . "\n");
    exit(1);
}
