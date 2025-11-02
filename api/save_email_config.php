<?php

// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Load global API config first so CORS/OPTIONS handling applies here
require_once __DIR__ . '/config.php';

// Allow CORS preflight requests to succeed
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    if (function_exists('ob_get_level') && ob_get_level() > 0) {
        @ob_clean();
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if (function_exists('ob_get_level') && ob_get_level() > 0) {
        @ob_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Determine action from POST
$action = $_POST['action'] ?? '';
// Basic request logging for debugging
error_log('[save_email_config] method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . ' action=' . $action . ' POST=' . json_encode($_POST));

require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/business_settings_helper.php';

if ($action === 'test') {
    handleTestEmail();
} elseif ($action === 'save') {
    handleSaveConfig();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function handleTestEmail()
{
    $testEmail = $_POST['testEmail'] ?? '';
    if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid test email address']);
        return;
    }

    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        return;
    }

    // Configure EmailHelper strictly from database-backed settings
    EmailHelper::createFromBusinessSettings($pdo);

    // Pull display fields (From/Name) from Business Information single source of truth
    $settings = BusinessSettings::getByCategory('email');
    $fromEmail = (string) BusinessSettings::get('business_email', '');
    $fromName  = (string) BusinessSettings::get('business_name', '');
    // SMTP enabled flag from email settings category with robust parsing
    $smtpEnabledVal = isset($settings['smtp_enabled']) ? $settings['smtp_enabled'] : false;
    if (is_bool($smtpEnabledVal)) { $smtpEnabled = $smtpEnabledVal; }
    else { $smtpEnabled = in_array(strtolower((string)$smtpEnabledVal), ['1','true','yes','on'], true); }
    $bcc = isset($settings['bcc_email']) ? (string)$settings['bcc_email'] : '';

    $subject = 'Test Email from WhimsicalFrog';
    $html = createTestEmailHtml($fromEmail, $fromName, $smtpEnabled);

    $options = [
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'reply_to' => $fromEmail,
        'is_html' => true,
        'bcc' => $bcc ?: []
    ];

    $success = false;
    $errorMessage = '';
    try {
        $success = EmailHelper::send($testEmail, $subject, $html, $options);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log('Test email error: ' . $errorMessage);
    }

    // Log the test email
    try {
        require_once 'email_logger.php';
        $createdBy = $_SESSION['user']['userId'] ?? $_SESSION['user']['username'] ?? 'admin';
        if ($success) {
            logTestEmail($testEmail, $fromEmail, $subject, $html, 'sent', null, $createdBy);
        } else {
            $logError = $errorMessage ?: 'Email sending failed';
            logTestEmail($testEmail, $fromEmail, $subject, $html, 'failed', $logError, $createdBy);
        }
    } catch (Exception $e) {
        error_log('Email logging error: ' . $e->getMessage());
    }

    ob_clean();
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Test email sent successfully!']);
    } else {
        $logError = $errorMessage ?: 'Email sending failed';
        echo json_encode(['success' => false, 'error' => 'Failed to send test email: ' . $logError]);
    }
}

function handleSaveConfig()
{
    try {
        // Expose DB target for debugging (from config.php globals)
        global $host, $db, $isLocalhost;
        error_log('[save_email_config] handleSaveConfig start');
        // Optional validation: only validate email fields managed in this modal (allow blanks)
        // fromEmail/fromName/adminEmail are managed in Business Information and are not saved here
        $emailFields = ['bccEmail', 'replyTo'];
        foreach ($emailFields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                if (!filter_var($_POST[$field], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email address for $field");
                }
            }
        }

        // Persist sensitive SMTP credentials to secret store (if provided) or clear when requested
        $smtpUsername = trim($_POST['smtpUsername'] ?? '');
        $smtpPassword = trim($_POST['smtpPassword'] ?? '');
        $clearSecretUser = isset($_POST['clear_smtpUsername']) && (string)$_POST['clear_smtpUsername'] === '1';
        $clearSecretPass = isset($_POST['clear_smtpPassword']) && (string)$_POST['clear_smtpPassword'] === '1';
        if ($clearSecretUser) {
            @secret_delete('smtp_username');
        }
        if ($clearSecretPass) {
            @secret_delete('smtp_password');
        }
        if ($smtpUsername !== '') {
            secret_set('smtp_username', $smtpUsername);
        }
        if ($smtpPassword !== '') {
            secret_set('smtp_password', $smtpPassword);
        }
        // DKIM private key secret management
        $dkimPrivateKey = trim($_POST['dkimPrivateKey'] ?? '');
        $clearDkimKey = isset($_POST['clear_dkimPrivateKey']) && (string)$_POST['clear_dkimPrivateKey'] === '1';
        if ($clearDkimKey) {
            @secret_delete('dkim_private_key');
        }
        if ($dkimPrivateKey !== '') {
            secret_set('dkim_private_key', $dkimPrivateKey);
        }
        error_log('[save_email_config] secrets updated: user=' . ($clearSecretUser ? 'cleared' : ($smtpUsername !== '' ? 'set' : 'unchanged')) . ' pass=' . ($clearSecretPass ? 'cleared' : ($smtpPassword !== '' ? 'set' : 'unchanged')));

        // Load existing settings to preserve values when POST fields are blank
        $existing = BusinessSettings::getByCategory('email');

        // Helper to prefer POST non-empty, otherwise fall back to existing value
        $prefer = function ($postKey, $existingKey) use ($existing) {
            $val = isset($_POST[$postKey]) ? (string)$_POST[$postKey] : '';
            if ($val !== '') {
                return $val;
            }
            return isset($existing[$existingKey]) ? (string)$existing[$existingKey] : '';
        };

        // Normalize values for DB storage, preserving existing when incoming is blank
        $isFlagOn = function (string $key): bool { return isset($_POST[$key]) && (string)$_POST[$key] === '1'; };
        $map = [
            // from_email/from_name/admin_email are sourced from Business Information; do not store duplicates here
            // If explicit clear flag set from UI, force blank; else preserve behavior
            'bcc_email'       => $isFlagOn('clear_bccEmail') ? '' : $prefer('bccEmail', 'bcc_email'),
            'reply_to'        => $isFlagOn('clear_replyTo') ? '' : $prefer('replyTo', 'reply_to'),
            'smtp_enabled'    => isset($_POST['smtpEnabled']) ? 'true' : 'false',
            'smtp_host'       => $isFlagOn('clear_smtpHost') ? '' : $prefer('smtpHost', 'smtp_host'),
            'smtp_port'       => $isFlagOn('clear_smtpPort') ? '' : $prefer('smtpPort', 'smtp_port'),
            // Username is stored in DB for visibility; secret store is the authority for sending.
            'smtp_username'   => $isFlagOn('clear_smtpUsername') ? '' : $prefer('smtpUsername', 'smtp_username'),
            // Password stays in secret store only
            'smtp_encryption' => $isFlagOn('clear_smtpEncryption') ? '' : $prefer('smtpEncryption', 'smtp_encryption'),
            // Additional SMTP controls
            'smtp_auth'       => isset($_POST['smtpAuth']) ? 'true' : (isset($existing['smtp_auth']) ? ((in_array(strtolower((string)$existing['smtp_auth']), ['true','1','yes'], true)) ? 'true' : 'false') : 'true'),
            'smtp_timeout'    => ($isFlagOn('clear_smtpTimeout') ? '' : ($prefer('smtpTimeout', 'smtp_timeout') !== '' ? (string)$prefer('smtpTimeout', 'smtp_timeout') : '')),
            'smtp_debug'      => isset($_POST['smtpDebug']) ? 'true' : (isset($existing['smtp_debug']) ? ((in_array(strtolower((string)$existing['smtp_debug']), ['true','1','yes'], true)) ? 'true' : 'false') : 'false'),
            // Advanced
            'return_path'     => $isFlagOn('clear_returnPath') ? '' : $prefer('returnPath', 'return_path'),
            'dkim_domain'     => $isFlagOn('clear_dkimDomain') ? '' : $prefer('dkimDomain', 'dkim_domain'),
            'dkim_selector'   => $isFlagOn('clear_dkimSelector') ? '' : $prefer('dkimSelector', 'dkim_selector'),
            'dkim_identity'   => $isFlagOn('clear_dkimIdentity') ? '' : $prefer('dkimIdentity', 'dkim_identity'),
            'smtp_allow_self_signed' => isset($_POST['smtpAllowSelfSigned']) ? 'true' : (isset($existing['smtp_allow_self_signed']) ? ((in_array(strtolower((string)$existing['smtp_allow_self_signed']), ['true','1','yes'], true)) ? 'true' : 'false') : 'false'),
        ];

        // Validation: if SMTP is enabled, require essential fields to be present (from POST or existing/secret)
        if ($map['smtp_enabled'] === 'true') {
            $hasHost = trim($map['smtp_host']) !== '';
            $hasPort = trim($map['smtp_port']) !== '';
            // Username can come from POST, existing DB, or secret store
            $effectiveUsername = $smtpUsername !== '' ? $smtpUsername : ($map['smtp_username'] ?? '');
            if ($effectiveUsername === '') {
                try {
                    $secretUser = secret_get('smtp_username');
                    if (is_string($secretUser) && trim($secretUser) !== '') {
                        $effectiveUsername = $secretUser;
                    }
                } catch (Exception $e) {
                    // ignore secret read errors for validation; will fail below if still empty
                }
            }

            if (!$hasHost || !$hasPort || trim($effectiveUsername) === '') {
                throw new Exception('SMTP is enabled but host, port, or username is missing. Please provide all required SMTP fields.');
            }
        }

        // Upsert to business_settings without relying on a unique index
        $pdo = Database::getInstance();
        error_log('[save_email_config] obtained PDO instance');

        $ops = ['updated' => 0, 'inserted' => 0];
        foreach ($map as $key => $val) {
            $type = 'text';
            $stored = (string)$val;
            if ($key === 'smtp_enabled') {
                $type = 'boolean';
                $stored = ($val === 'true') ? 'true' : 'false';
            }
            if ($key === 'smtp_port' && $stored !== '') {
                $type = 'number';
            }

            $params = [
                ':category' => 'email',
                ':key' => $key,
                ':value' => $stored,
                ':type' => $type,
                ':display_name' => ucwords(str_replace('_', ' ', $key)),
                ':description' => 'Email setting ' . $key,
            ];

            $affected = Database::execute("UPDATE business_settings
                SET setting_value = :value, setting_type = :type, display_name = :display_name, description = :description, updated_at = CURRENT_TIMESTAMP
                WHERE category = :category AND setting_key = :key", $params);
            if ($affected > 0) {
                $ops['updated']++;
            } else {
                Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description, updated_at)
                    VALUES (:category, :key, :value, :type, :display_name, :description, CURRENT_TIMESTAMP)", $params);
                $ops['inserted']++;
            }
        }

        // Clear settings cache so reads reflect the latest values
        if (class_exists('BusinessSettings')) {
            BusinessSettings::clearCache();
        }

        // Purge legacy duplicate fields from 'email' category that should be sourced from Business Information
        $purgeInfo = ['attempted' => [], 'deleted' => 0];
        try {
            $bizEmail = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_email', '') : '';
            $bizName  = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_name', '') : '';
            // Only purge if canonical business info is present to avoid accidental data loss
            if ($bizEmail !== '' && $bizName !== '') {
                $keysToPurge = ['from_email','from_name','admin_email','smtp_password'];
                $purgeInfo['attempted'] = $keysToPurge;
                $placeholders = implode(',', array_fill(0, count($keysToPurge), '?'));
                $purgeInfo['deleted'] = (int) Database::execute(
                    "DELETE FROM business_settings WHERE category = 'email' AND setting_key IN ($placeholders)",
                    $keysToPurge
                );
            }
        } catch (Exception $e) {
            error_log('[save_email_config] purge duplicates warning: ' . $e->getMessage());
        }

        // Verification: re-query latest value per key and compare with what we attempted to write
        // Verification via Database helper
        $mismatches = [];
        foreach ($map as $key => $expectedVal) {
            $row = Database::queryOne("SELECT setting_value, setting_type FROM business_settings WHERE category = :category AND setting_key = :key ORDER BY updated_at DESC, id DESC LIMIT 1", [':category' => 'email', ':key' => $key]);
            if ($row === false) {
                $mismatches[$key] = ['expected' => (string)$expectedVal, 'actual' => null, 'note' => 'no row found'];
                continue;
            }
            $actual = (string)$row['setting_value'];
            if ((string)$expectedVal !== $actual) {
                $mismatches[$key] = ['expected' => (string)$expectedVal, 'actual' => $actual, 'type' => $row['setting_type']];
            }
        }

        // Load fresh settings and normalize like get_email_config.php
        $settings = BusinessSettings::getByCategory('email');
        $smtpEnabledVal = isset($settings['smtp_enabled']) ? $settings['smtp_enabled'] : false;
        if (is_bool($smtpEnabledVal)) {
            $smtpEnabled = $smtpEnabledVal;
        } else {
            $smtpEnabled = in_array(strtolower((string)$smtpEnabledVal), ['true','1','yes'], true);
        }
        $config = [
            // Always reflect Business Information (single source of truth)
            'fromEmail'      => (string) BusinessSettings::get('business_email', ''),
            'fromName'       => (string) BusinessSettings::get('business_name', ''),
            'adminEmail'     => (function(){ $v = (string) BusinessSettings::get('admin_email', ''); return $v !== '' ? $v : (string) BusinessSettings::get('business_email', ''); })(),
            'bccEmail'       => isset($settings['bcc_email']) ? (string)$settings['bcc_email'] : '',
            'replyTo'        => isset($settings['reply_to']) ? (string)$settings['reply_to'] : '',
            'smtpEnabled'    => $smtpEnabled,
            'smtpHost'       => isset($settings['smtp_host']) ? (string)$settings['smtp_host'] : '',
            'smtpPort'       => isset($settings['smtp_port']) ? (string)$settings['smtp_port'] : '',
            'smtpUsername'   => isset($settings['smtp_username']) ? (string)$settings['smtp_username'] : '',
            'smtpPassword'   => '',
            'smtpEncryption' => isset($settings['smtp_encryption']) ? (string)$settings['smtp_encryption'] : '',
            'smtpAuth'       => (function($val){ if ($val === null) return true; if (is_bool($val)) return $val; $s=strtolower((string)$val); return in_array($s,['1','true','yes','on'],true);} )(isset($settings['smtp_auth']) ? $settings['smtp_auth'] : null),
            'smtpTimeout'    => isset($settings['smtp_timeout']) ? (string)$settings['smtp_timeout'] : '30',
            'smtpDebug'      => (function($val){ if ($val === null) return false; if (is_bool($val)) return $val; $s=strtolower((string)$val); return in_array($s,['1','true','yes','on'],true);} )(isset($settings['smtp_debug']) ? $settings['smtp_debug'] : null),
            // Advanced
            'returnPath'     => isset($settings['return_path']) ? (string)$settings['return_path'] : '',
            'dkimDomain'     => isset($settings['dkim_domain']) ? (string)$settings['dkim_domain'] : '',
            'dkimSelector'   => isset($settings['dkim_selector']) ? (string)$settings['dkim_selector'] : '',
            'dkimIdentity'   => isset($settings['dkim_identity']) ? (string)$settings['dkim_identity'] : '',
            'smtpAllowSelfSigned' => (function($val){ if ($val === null) return false; if (is_bool($val)) return $val; $s=strtolower((string)$val); return in_array($s,['1','true','yes','on'],true);} )(isset($settings['smtp_allow_self_signed']) ? $settings['smtp_allow_self_signed'] : null),
        ];

        // Ensure no stray output corrupts JSON
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_clean();
        }
        echo json_encode([
            'success' => true,
            'message' => 'Email configuration saved successfully!',
            'config' => $config,
            'debug' => [
                'ops' => $ops,
                'written' => $map,
                'verify_mismatches' => $mismatches,
                'db' => [ 'host' => $host ?? null, 'db' => $db ?? null, 'isLocalhost' => $isLocalhost ?? null ],
                'purge' => $purgeInfo
            ]
        ]);
    } catch (Exception $e) {
        error_log('[save_email_config] error: ' . $e->getMessage());
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_clean();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createTestEmailHtml($fromEmail, $fromName, $smtpEnabled)
{
    // BusinessSettings helper is already loaded above
    $brandPrimary = BusinessSettings::getPrimaryColor();
    $brandSecondary = BusinessSettings::getSecondaryColor();
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Test Email - WhimsicalFrog</title>
        <style>
        body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
        .m-0 { margin:0; }
        .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
        .email-header { background: {$brandPrimary}; color:#fff; padding:16px; text-align:center; }
        .email-title { margin:0; font-size:20px; }
        .u-margin-top-10px { margin-top:10px; }
        .u-color-333 { color:#333; }
        .u-color-666 { color:#666; }
        .u-line-height-1-6 { line-height:1.6; }
        .u-font-size-14px { font-size:14px; }
        .u-margin-top-20px { margin-top:20px; }
        .email-section { margin:16px 0; }
        .email-section h3 { color: {$brandSecondary}; margin:0 0 8px; font-size:16px; }
        </style>
    </head>
    <body class='email-body'>
        <div class='email-header'>
            <h1 class='email-title'>WhimsicalFrog</h1>
            <p class='u-margin-top-10px'>Email Configuration Test</p>
        </div>
        
        <div class='email-wrapper'>
            <h2 class='u-color-333 m-0'>Configuration Test</h2>
            
            <p>If you're reading this email, your email configuration is working.</p>
            
            <div class='email-section'>
                <h3>Configuration Details:</h3>
                <ul class='u-color-666 u-line-height-1-6'>
                    <li><strong>From Email:</strong> " . htmlspecialchars($fromEmail) . "</li>
                    <li><strong>From Name:</strong> " . htmlspecialchars($fromName) . "</li>
                    <li><strong>SMTP Enabled:</strong> " . ($smtpEnabled ? 'Yes' : 'No') . "</li>
                </ul>
            </div>
            
            <p class='u-color-666 u-font-size-14px u-margin-top-20px'>
                Sent on " . date('F j, Y \a\t g:i A T') . "
            </p>
        </div>
    </body>
    </html>
    ";
}
