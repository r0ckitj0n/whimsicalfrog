<?php
// Seed required SMTP BusinessSettings without introducing duplicate fields.
// Usage: php scripts/dev/seed-email-smtp.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

function upsert_setting($category, $key, $value, $type = 'text') {
    $displayName = ucwords(str_replace('_', ' ', (string)$key));
    $description = 'Business setting ' . (string)$key;
    $affected = Database::execute(
        "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = CURRENT_TIMESTAMP",
        [
            $category,
            (string)$key,
            (string)$value,
            (string)$type,
            $displayName,
            $description,
        ]
    );
    return $affected >= 0;
}

try {
    Database::getInstance();

    $category = 'email';
    $changes = 0;

    $changes += upsert_setting($category, 'smtp_host', 'smtp.ionos.com', 'text') ? 1 : 0;
    $changes += upsert_setting($category, 'smtp_port', '587', 'number') ? 1 : 0;
    $changes += upsert_setting($category, 'smtp_encryption', 'tls', 'text') ? 1 : 0;
    $changes += upsert_setting($category, 'smtp_enabled', 'true', 'boolean') ? 1 : 0;

    echo "Seeded/updated SMTP settings ({$changes} changes).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error seeding SMTP settings: " . $e->getMessage() . "\n");
    exit(1);
}
