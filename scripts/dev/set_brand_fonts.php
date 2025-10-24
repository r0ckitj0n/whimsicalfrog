<?php
// Persist brand font stacks in the database (business_info category)
// Usage: php scripts/dev/set_brand_fonts.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

$primary = "'Merienda', cursive";
$secondary = "Nunito, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

function upsertSetting(string $key, string $value, string $type = 'text', string $category = 'business_info'): void {
    $row = Database::queryOne("SELECT id FROM business_settings WHERE setting_key = ? LIMIT 1", [$key]);
    $val = (string)$value;
    $displayName = ucwords(str_replace('_', ' ', $key));
    if (!empty($row['id'])) {
        Database::execute(
            "UPDATE business_settings SET display_name = ?, setting_value = ?, setting_type = ?, category = ?, updated_at = NOW() WHERE id = ?",
            [$displayName, $val, $type, $category, $row['id']]
        );
        echo "Updated: {$key} => {$val}\n";
    } else {
        Database::execute(
            "INSERT INTO business_settings (setting_key, display_name, setting_value, setting_type, category, updated_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$key, $displayName, $val, $type, $category]
        );
        echo "Inserted: {$key} => {$val}\n";
    }
}

try {
    upsertSetting('business_brand_font_primary', $primary, 'text', 'business_info');
    upsertSetting('business_brand_font_secondary', $secondary, 'text', 'business_info');

    // Clear helper cache so subsequent requests see updated values immediately
    if (class_exists('BusinessSettings')) {
        BusinessSettings::clearCache();
    }

    echo "\nBrand fonts persisted. Primary: {$primary}\nSecondary: {$secondary}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error persisting brand fonts: " . $e->getMessage() . "\n");
    exit(1);
}
