<?php

// Update canonical business email to orders@whimsicalfrog.us
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

try {
    $pdo = Database::getInstance();

    $key = 'business_email';
    $val = 'orders@whimsicalfrog.us';
    $category = 'business_info';
    $display = 'Business Email';
    $desc = 'Primary business email used for outgoing mail and contact.';
    $type = 'text';

    $sql = "INSERT INTO business_settings (category, setting_key, setting_value, display_name, description, setting_type, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), setting_type = VALUES(setting_type), updated_at = NOW()";
    $affected = Database::execute($sql, [$category, $key, $val, $display, $desc, $type]);
    if ($affected === false) {
        throw new Exception('DB execute failed');
    }

    // Clear cache so reads reflect new value
    BusinessSettings::clearCache();

    $curr = BusinessSettings::getBusinessEmail();
    echo "business_email updated. Current value: {$curr}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    // No PDO stmt available when using Database helpers
    exit(1);
}
