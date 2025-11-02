<?php
// Server-Sent Events stream for live email test logs
// Usage: GET /api/email_test_stream.php?to=email@example.com

// Absolutely no prior output
ob_end_clean();
while (ob_get_level() > 0) { @ob_end_clean(); }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Accel-Buffering: no'); // Disable proxy buffering (nginx)

// CORS for same-origin dev; adjust if needed
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');

$flush = function() {
    @ob_flush();
    @flush();
};

$send = function(string $event, $data = null) use ($flush) {
    if ($event !== '') {
        echo "event: {$event}\n";
    }
    if (is_array($data) || is_object($data)) {
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
    } elseif ($data !== null) {
        $lines = explode("\n", (string)$data);
        foreach ($lines as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
    } else {
        echo "data: {}\n\n";
    }
    $flush();
};

$log = function($msg) use ($send) {
    $send('log', [ 't' => date('H:i:s'), 'msg' => (string)$msg ]);
};

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/business_settings_helper.php';
    require_once __DIR__ . '/../includes/email_helper.php';
    require_once __DIR__ . '/../includes/secret_store.php';
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) { require_once $autoload; }

    $mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
    if ($mode !== 'preflight') {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $send('error', [ 'message' => 'Invalid or missing ?to=email@example.com' ]);
            exit;
        }
    }

    $send('start', [ 'message' => ($mode === 'preflight' ? 'Starting SMTP preflight (no email will be sent)' : 'Starting email configuration test'), 'to' => $to ]);

    // Load configuration from DB and secrets
    $settings = BusinessSettings::getByCategory('email') ?: [];
    $usingSmtp = false;
    $smtpEnabledVal = $settings['smtp_enabled'] ?? false;
    if (is_bool($smtpEnabledVal)) { $usingSmtp = $smtpEnabledVal; }
    else { $usingSmtp = in_array(strtolower((string)$smtpEnabledVal), ['1','true','yes','on'], true); }

    $secUser = null; $secPass = null;
    try { $secUser = secret_get('smtp_username'); } catch (Throwable $__) {}
    try { $secPass = secret_get('smtp_password'); } catch (Throwable $__) {}

    $summary = [
        'provider' => $usingSmtp ? 'smtp' : 'mail',
        'fromEmail' => (string)($settings['from_email'] ?? BusinessSettings::get('business_email', '')),
        'fromName' => (string)($settings['from_name'] ?? BusinessSettings::get('business_name', '')),
        'smtp' => [
            'host' => (string)($settings['smtp_host'] ?? ''),
            'port' => (string)($settings['smtp_port'] ?? ''),
            'encryption' => (string)($settings['smtp_encryption'] ?? ''),
            'auth' => (bool)(function($v){ if ($v===null) return true; if (is_bool($v)) return $v; $s=strtolower((string)$v); return in_array($s,['1','true','yes','on'], true); })( $settings['smtp_auth'] ?? null ),
            'timeout' => (string)($settings['smtp_timeout'] ?? '30'),
            'username_present' => is_string($secUser) && trim($secUser) !== '',
            'password_present' => is_string($secPass) && trim($secPass) !== '',
        ]
    ];
    $send('config', $summary);

    // Configure EmailHelper, enabling debug sink to stream PHPMailer logs
    EmailHelper::createFromBusinessSettings(Database::getInstance());
    EmailHelper::configure([
        'smtp_debug' => 2,
        'smtp_debug_sink' => function($str, $level) use ($send) {
            $send('smtp', [ 'level' => (int)$level, 'msg' => (string)$str ]);
        }
    ]);

    if ($mode === 'preflight') {
        $send('progress', [ 'message' => 'Connecting to SMTP…' ]);
        $ok = false; $err = null;
        try {
            // Ensure EmailHelper uses debug sink to stream logs
            EmailHelper::configure([
                'smtp_debug' => 2,
                'smtp_debug_sink' => function($str, $level) use ($send) { $send('smtp', [ 'level' => (int)$level, 'msg' => (string)$str ]); }
            ]);
            $ok = EmailHelper::preflightSMTP();
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
        if ($ok) {
            $send('done', [ 'success' => true, 'message' => '✅ SMTP connection successful.' ]);
        } else {
            $send('done', [ 'success' => false, 'message' => '❌ SMTP connection failed', 'error' => $err ?: 'Unknown error' ]);
        }
    } else {
        $subject = 'Live Email Test - WhimsicalFrog';
        $body = '<html><body><p>This is a live test email from WhimsicalFrog.</p><p>Sent at ' . date('c') . '</p></body></html>';

        $send('progress', [ 'message' => 'Sending test email…' ]);

        $ok = false; $err = null;
        try {
            $ok = EmailHelper::send($to, $subject, $body, [ 'is_html' => true ]);
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }

        if ($ok) {
            $send('done', [ 'success' => true, 'message' => '✅ Test email sent successfully.' ]);
        } else {
            $send('done', [ 'success' => false, 'message' => '❌ Failed to send test email', 'error' => $err ?: 'Unknown error' ]);
        }
    }

} catch (Throwable $e) {
    $send('error', [ 'message' => $e->getMessage() ]);
}

// Keep the connection open briefly to ensure delivery of final event
usleep(200000);
