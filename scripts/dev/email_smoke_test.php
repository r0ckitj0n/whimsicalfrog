<?php
// Email smoke test script
// Sends a test email using the central configuration to a specified recipient
// Usage: php scripts/dev/email_smoke_test.php [recipient]

try {
    $recipient = $argv[1] ?? 'jon@netman.us';

    require __DIR__ . '/../../api/email_config.php';

    echo "Loaded email_config.php\n";
    echo 'SMTP_ENABLED=' . (defined('SMTP_ENABLED') ? (SMTP_ENABLED ? '1' : '0') : 'undef') . "\n";
    echo 'SMTP_HOST=' . (defined('SMTP_HOST') ? SMTP_HOST : 'undef') . "\n";
    echo 'SMTP_PORT=' . (defined('SMTP_PORT') ? SMTP_PORT : 'undef') . "\n";
    echo 'SMTP_ENCRYPTION=' . (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'undef') . "\n";
    echo 'SMTP_AUTH=' . (defined('SMTP_AUTH') ? (SMTP_AUTH ? '1' : '0') : 'undef') . "\n";
    echo 'SMTP_TIMEOUT=' . (defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 'undef') . "\n";
    echo 'SMTP_DEBUG=' . (defined('SMTP_DEBUG') ? SMTP_DEBUG : 'undef') . "\n";
    echo 'FROM_EMAIL=' . (defined('FROM_EMAIL') ? FROM_EMAIL : 'undef') . "\n";
    echo 'REPLY_TO_EMAIL=' . (defined('REPLY_TO_EMAIL') ? REPLY_TO_EMAIL : 'undef') . "\n";

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Invalid recipient email: {$recipient}\n");
        exit(2);
    }

    echo 'Sending to: ' . $recipient . "\n";

    $html = '<p>This is a WhimsicalFrog SMTP smoke test sent at ' . date('c') . '</p>';
    $text = 'This is a WhimsicalFrog SMTP smoke test sent at ' . date('c');

    $ok = sendEmail($recipient, 'WhimsicalFrog SMTP Smoke Test', $html, $text);

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
