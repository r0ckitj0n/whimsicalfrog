<?php
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/business_settings_helper.php';

try {
    // Load from database (single source of truth)
    $settings = BusinessSettings::getByCategory('email');
    // Normalize and map keys expected by the admin UI
    // Normalize boolean for smtpEnabled reliably (handles boolean true/false and common string values)
    $smtpEnabledVal = isset($settings['smtp_enabled']) ? $settings['smtp_enabled'] : false;
    if (is_bool($smtpEnabledVal)) {
        $smtpEnabled = $smtpEnabledVal;
    } else {
        $smtpEnabled = in_array(strtolower((string)$smtpEnabledVal), ['true','1','yes'], true);
    }

    $config = [
        'fromEmail'      => isset($settings['from_email']) ? (string)$settings['from_email'] : '',
        'fromName'       => isset($settings['from_name']) ? (string)$settings['from_name'] : '',
        'adminEmail'     => isset($settings['admin_email']) ? (string)$settings['admin_email'] : '',
        'bccEmail'       => isset($settings['bcc_email']) ? (string)$settings['bcc_email'] : '',
        'smtpEnabled'    => $smtpEnabled,
        'smtpHost'       => isset($settings['smtp_host']) ? (string)$settings['smtp_host'] : '',
        'smtpPort'       => isset($settings['smtp_port']) ? (string)$settings['smtp_port'] : '',
        'smtpUsername'   => isset($settings['smtp_username']) ? (string)$settings['smtp_username'] : '',
        // Never return password
        'smtpPassword'   => '',
        'smtpEncryption' => isset($settings['smtp_encryption']) ? (string)$settings['smtp_encryption'] : ''
    ];

    // Display-only fallbacks: if email category is empty, show sensible legacy/global defaults
    if ($config['fromEmail'] === '') {
        $config['fromEmail'] = BusinessSettings::get('business_email', $config['fromEmail']);
    }
    if ($config['adminEmail'] === '') {
        $config['adminEmail'] = BusinessSettings::get('admin_email', $config['adminEmail']);
    }
    if ($config['fromName'] === '') {
        $config['fromName'] = BusinessSettings::get('business_name', $config['fromName']);
    }

    // Optional debug block: helps diagnose category/key mismatches without changing normal behavior
    $debug = null;
    if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
        $all = BusinessSettings::getAll();
        $debug = [
            'availableCategories' => array_keys($all),
            'rawEmailSettings' => isset($all['email']) ? $all['email'] : [],
        ];
    }

    if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_clean(); }
    echo json_encode([
        'success' => true,
        'config' => $config,
        'debug' => $debug
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load email configuration: ' . $e->getMessage()
    ]);
}
?> 