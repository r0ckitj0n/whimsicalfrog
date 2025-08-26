<?php
// Seed required SMTP BusinessSettings without introducing duplicate fields.
// Usage: php scripts/dev/seed-email-smtp.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

function pdo() {
    return Database::getInstance();
}

function upsert_setting($pdo, $category, $key, $value, $type = 'text') {
    $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description)
            VALUES (:category, :key, :value, :type, :display_name, :description)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = CURRENT_TIMESTAMP";
    $stmt = $pdo->prepare($sql);
    $displayName = ucwords(str_replace('_', ' ', (string)$key));
    $description = 'Business setting ' . (string)$key;
    return $stmt->execute([
        ':category' => $category,
        ':key' => (string)$key,
        ':value' => (string)$value,
        ':type' => (string)$type,
        ':display_name' => $displayName,
        ':description' => $description,
    ]);
}

try {
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $category = 'email';
    $changes = 0;

    $changes += upsert_setting($pdo, $category, 'smtp_host', 'smtp.ionos.com', 'text') ? 1 : 0;
    $changes += upsert_setting($pdo, $category, 'smtp_port', '587', 'number') ? 1 : 0;
    $changes += upsert_setting($pdo, $category, 'smtp_encryption', 'tls', 'text') ? 1 : 0;
    $changes += upsert_setting($pdo, $category, 'smtp_enabled', 'true', 'boolean') ? 1 : 0;

    echo "Seeded/updated SMTP settings ({$changes} changes).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error seeding SMTP settings: " . $e->getMessage() . "\n");
    exit(1);
}
