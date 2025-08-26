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
    $stmt = $pdo->prepare($sql);
    if (!$stmt->execute([$category, $key, $val, $display, $desc, $type])) {
        $info = $stmt->errorInfo();
        throw new Exception('DB execute failed: ' . implode(' | ', $info));
    }

    // Clear cache so reads reflect new value
    BusinessSettings::clearCache();

    $curr = BusinessSettings::getBusinessEmail();
    echo "business_email updated. Current value: {$curr}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    if (isset($stmt)) {
        $info = $stmt->errorInfo();
        fwrite(STDERR, 'PDO errorInfo: ' . implode(' | ', $info) . "\n");
    }
    exit(1);
}
