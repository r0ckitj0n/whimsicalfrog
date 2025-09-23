<?php

// Dev script: send a test email using strict DB/secret-backed config
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../api/email_config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

$to = $argv[1] ?? BusinessSettings::getBusinessEmail();
$subject = 'WhimsicalFrog SMTP Test';
$bodyHtml = '<p>This is a test email sent via SMTP at ' . date('c') . '.</p><p>From: ' . FROM_NAME . ' &lt;' . FROM_EMAIL . '&gt;</p>';

try {
    $ok = sendEmail($to, $subject, $bodyHtml);
    if ($ok) {
        echo "Test email sent to {$to} using host " . SMTP_HOST . ":" . SMTP_PORT . " (" . SMTP_ENCRYPTION . ")\n";
        exit(0);
    }
    fwrite(STDERR, "sendEmail returned false\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error sending test email: ' . $e->getMessage() . "\n");
    exit(1);
}
