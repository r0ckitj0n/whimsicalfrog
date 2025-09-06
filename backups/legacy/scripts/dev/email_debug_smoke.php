<?php
// Force SMTP debug and send a smoke test email without persisting settings
// Usage: php scripts/dev/email_debug_smoke.php [recipient]

try {
    $recipient = $argv[1] ?? 'jon@netman.us';

    // Ensure error_log goes to STDERR so we can capture PHPMailer debug output in the terminal
    @ini_set('log_errors', '1');
    @ini_set('error_log', 'php://stderr');

    require __DIR__ . '/../../api/email_config.php';

    // Force debug level regardless of stored config, and set a shorter timeout
    EmailHelper::configure([
        'smtp_debug' => 2, // verbose
        'smtp_timeout' => 20,
    ]);

    echo "Configured runtime SMTP debug=2, timeout=20\n";
    echo 'SMTP_HOST=' . (defined('SMTP_HOST') ? SMTP_HOST : 'undef') . "\n";
    echo 'SMTP_PORT=' . (defined('SMTP_PORT') ? SMTP_PORT : 'undef') . "\n";
    echo 'SMTP_ENCRYPTION=' . (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'undef') . "\n";

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Invalid recipient email: {$recipient}\n");
        exit(2);
    }

    echo 'Sending to: ' . $recipient . "\n";

    $html = '<p>This is a WhimsicalFrog SMTP DEBUG smoke test sent at ' . date('c') . '</p>';
    $text = 'This is a WhimsicalFrog SMTP DEBUG smoke test sent at ' . date('c');

    $ok = sendEmail($recipient, 'WhimsicalFrog SMTP DEBUG Smoke Test', $html, $text);

    if ($ok) {
        echo "SUCCESS: Email sent.\n";
        exit(0);
    } else {
        fwrite(STDERR, "FAIL: sendEmail returned false\n");
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
