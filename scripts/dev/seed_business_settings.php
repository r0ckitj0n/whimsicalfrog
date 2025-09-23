<?php

// Seed or fix essential business settings strictly in the database
// Usage: php scripts/dev/seed_business_settings.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

function setSetting(string $key, $value, string $type = 'number', string $category = 'ecommerce', bool $forceUpdate = false, ?string $displayName = null): void
{
    // Check if a row exists for this key
    $row = Database::queryOne("SELECT id FROM business_settings WHERE setting_key = ? LIMIT 1", [$key]);
    $existingId = $row['id'] ?? null;

    if ($displayName === null) {
        // Generate a human-friendly display name from key, e.g., 'tax_enabled' -> 'Tax Enabled'
        $displayName = ucwords(str_replace('_', ' ', $key));
    }

    $val = is_array($value) ? json_encode($value) : (string)$value;

    if ($existingId) {
        if ($forceUpdate) {
            Database::execute(
                "UPDATE business_settings SET display_name = ?, setting_value = ?, setting_type = ?, category = ?, updated_at = NOW() WHERE setting_key = ?",
                [$displayName, $val, $type, $category, $key]
            );
            echo "Updated: {$key} [{$displayName}]={$val} ({$type}) in {$category}\n";
        } else {
            echo "Skip (exists): {$key}\n";
        }
    } else {
        Database::execute(
            "INSERT INTO business_settings (setting_key, display_name, setting_value, setting_type, category, updated_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$key, $displayName, $val, $type, $category]
        );
        echo "Inserted: {$key} [{$displayName}]={$val} ({$type}) in {$category}\n";
    }
}

try {
    // 1) Always have tax enabled (in high-priority category 'business_info')
    setSetting('tax_enabled', 'true', 'boolean', 'business_info', true /*force update*/);

    // 2) Ensure a non-zero tax rate exists (use 0.08 if missing)
    // Do not overwrite an existing number unless missing
    $hasTaxRate = false;
    $chk = Database::queryOne("SELECT 1 AS present FROM business_settings WHERE setting_key = 'tax_rate' LIMIT 1");
    $hasTaxRate = isset($chk['present']);
    if (!$hasTaxRate) {
        setSetting('tax_rate', 0.08, 'number', 'business_info');
    } else {
        echo "Skip (exists): tax_rate\n";
    }

    // 2b) Set an explicit fallback rate used by TaxService when state/zip not resolved
    setSetting('tax_default_fallback_rate', 0.08, 'number', 'business_info', true);

    // 3) Default tax_shipping to false unless it already exists (keep current behavior)
    $chk2 = Database::queryOne("SELECT 1 AS present FROM business_settings WHERE setting_key = 'tax_shipping' LIMIT 1");
    $hasTaxShipping = isset($chk2['present']);
    if (!$hasTaxShipping) {
        setSetting('tax_shipping', 'false', 'boolean', 'business_info');
    } else {
        echo "Skip (exists): tax_shipping\n";
    }

    // 4) Shipping rates must exist in DB; only set if missing (do not override business choices)
    $shippingDefaults = [
        'free_shipping_threshold' => 0.00, // No free shipping unless explicitly configured
        'local_delivery_fee' => 35.00,     // As requested
        'shipping_rate_usps' => 8.99,
        'shipping_rate_fedex' => 12.99,
        'shipping_rate_ups' => 12.99,
    ];
    foreach ($shippingDefaults as $k => $v) {
        $row = Database::queryOne("SELECT 1 AS present FROM business_settings WHERE setting_key = ? LIMIT 1", [$k]);
        $exists = isset($row['present']);
        if ($k === 'local_delivery_fee') {
            // Force-update local_delivery_fee to 35.00 as requested
            setSetting($k, $v, 'number', 'ecommerce', true);
        } elseif (!$exists) {
            setSetting($k, $v, 'number', 'ecommerce');
        } else {
            echo "Skip (exists): {$k}\n";
        }
    }

    // 5) Optionally set a default shipping method key (not used by code, informational)
    $stmt = Database::queryOne("SELECT 1 AS present FROM business_settings WHERE setting_key = 'default_shipping_method' LIMIT 1");
    if (!isset($stmt['present'])) {
        setSetting('default_shipping_method', 'USPS', 'text', 'ecommerce');
    } else {
        echo "Skip (exists): default_shipping_method\n";
    }

    echo "\nSeeding complete. Review messages above.\n";
    echo "If orders still fail, check the Network response body for /api/add-order.php to see the specific missing/invalid key.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error seeding settings: " . $e->getMessage() . "\n");
    exit(1);
}
