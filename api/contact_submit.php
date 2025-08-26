<?php
// Contact form submission API
// Early guard: ensure JSON and CORS headers + buffering + fatal handler BEFORE any includes
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if (function_exists('ini_set')) @ini_set('display_errors', '0');
}
ob_start();

// Ensure a JSON error is returned even on fatal errors (empty body previously)
register_shutdown_function(function () {
    if (!empty($GLOBALS['__wf_contact_json_done'])) {
        return; // normal path already responded
    }
    $err = error_get_last();
    // Clean any buffers
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    if ($err && isset($err['message'])) {
        error_log('[contact_submit][shutdown] ' . $err['message'] . ' in ' . ($err['file'] ?? '?') . ':' . ($err['line'] ?? '?'));
    } else {
        error_log('[contact_submit][shutdown] No fatal error captured, but script terminated without JSON response (possible die/exit).');
    }
    echo json_encode(['success' => false, 'error' => 'Server error handling your request. Please try again.']);
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/email_config.php'; // Provides SMTP/FROM constants if configured
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/business_settings_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_response($statusCode, $data) {
    // Mark request as completed so shutdown handler won't emit a second response
    $GLOBALS['__wf_contact_json_done'] = true;
    http_response_code($statusCode);
    // Discard any prior output that could corrupt JSON
    $noise = ob_get_clean();
    if ($noise !== false && trim($noise) !== '') {
        error_log('[contact_submit] Stray output before JSON: ' . substr(trim($noise), 0, 1000));
    }
    echo json_encode($data);
    exit;
}

// Determine localhost and diagnostic mode early (supports GET)
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$isDiag = $isLocal && (isset($_GET['diag']) && $_GET['diag'] === '1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$isDiag) {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

// Accept JSON or form-encoded
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

// Localhost-only diagnostics to validate configuration safely
// Expand diag detection to include JSON body flag when POSTing
$isDiag = $isDiag || ($isLocal && !empty($input['diag']));
if ($isDiag) {
    $diag = [
        'success' => true,
        'mode' => 'diagnostic',
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'has_session_csrf' => !empty($_SESSION['contact_csrf']),
        'received_csrf' => isset($input['csrf']) && $input['csrf'] !== '',
        'smtp_enabled' => defined('SMTP_ENABLED') ? (bool)SMTP_ENABLED : false,
        'from_email_defined' => defined('FROM_EMAIL'),
        'from_name_defined' => defined('FROM_NAME'),
    ];
    // Try reading a couple of BusinessSettings keys safely
    try {
        $diag['business_email'] = BusinessSettings::getBusinessEmail();
        $diag['admin_email'] = BusinessSettings::getAdminEmail();
        $diag['brand_primary'] = BusinessSettings::getPrimaryColor();
    } catch (Exception $e) {
        $diag['business_settings_error'] = true;
    }
    json_response(200, $diag);
}

// Basic honeypot check
if (!empty($input['website'])) {
    // Silently succeed to confuse bots
    json_response(200, ['success' => true, 'message' => 'Thanks!']);
}

// CSRF check
$csrf = $input['csrf'] ?? '';
if (empty($_SESSION['contact_csrf']) || !hash_equals($_SESSION['contact_csrf'], (string)$csrf)) {
    if ($isLocal) {
        // Relax CSRF during localhost development to simplify testing through Vite proxy
        error_log('[contact_submit][dev] CSRF missing/invalid in localhost; proceeding for development.');
    } else {
        json_response(400, ['success' => false, 'error' => 'Invalid form token. Please refresh and try again.']);
    }
}

// Validation
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

if ($name === '' || strlen($name) > 100) {
    json_response(400, ['success' => false, 'error' => 'Please enter your name (max 100 characters).']);
}
if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['success' => false, 'error' => 'Please enter a valid email address.']);
}
if ($subject !== '' && strlen($subject) > 150) {
    json_response(400, ['success' => false, 'error' => 'Subject is too long (max 150 characters).']);
}
if ($message === '' || strlen($message) > 5000) {
    json_response(400, ['success' => false, 'error' => 'Please enter a message (max 5000 characters).']);
}

// Prepare EmailHelper configuration using constants with secret store fallbacks (then BusinessSettings)
try {
    $secUser = secret_get('smtp_username');
    $secPass = secret_get('smtp_password');
    EmailHelper::configure([
        'smtp_enabled'    => defined('SMTP_ENABLED') ? (bool)SMTP_ENABLED : false,
        'smtp_host'       => defined('SMTP_HOST') ? SMTP_HOST : '',
        'smtp_port'       => defined('SMTP_PORT') ? (int)SMTP_PORT : 587,
        'smtp_username'   => (!empty($secUser)) ? $secUser : (defined('SMTP_USERNAME') ? SMTP_USERNAME : ''),
        'smtp_password'   => (!empty($secPass)) ? $secPass : (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''),
        'smtp_encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls',
        'from_email'      => defined('FROM_EMAIL') ? FROM_EMAIL : BusinessSettings::getBusinessEmail(),
        'from_name'       => defined('FROM_NAME') ? FROM_NAME : BusinessSettings::getBusinessName(),
        'reply_to'        => defined('FROM_EMAIL') ? FROM_EMAIL : BusinessSettings::getBusinessEmail(),
    ]);
} catch (Exception $e) {
    // Continue; EmailHelper has defaults
}

$adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : BusinessSettings::getAdminEmail();
if (empty($adminEmail)) {
    $adminEmail = BusinessSettings::getBusinessEmail();
}

$brandPrimary = BusinessSettings::getPrimaryColor();
$brandSecondary = BusinessSettings::getSecondaryColor();
$businessName = BusinessSettings::getBusinessName();

$cleanSubject = $subject !== '' ? $subject : 'New contact form submission';
$adminSubject = '[' . $businessName . '] ' . $cleanSubject;

// Build admin HTML body (class-based styles, no inline style attributes)
$bodyAdmin = "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Contact Message - " . htmlspecialchars($businessName) . "</title>
  <style>
    body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .email-header { background: " . $brandPrimary . "; color:#fff; padding:16px; text-align:center; }
    .email-title { margin:0; font-size:20px; }
    .email-section { margin:16px 0; }
    blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
  </style>
  </head>
  <body class='email-body'>
    <div class='email-header'>
      <h1 class='email-title'>" . htmlspecialchars($businessName) . " — New Contact Message</h1>
    </div>
    <div class='email-wrapper'>
      <div class='email-section'>
        <p><strong>From:</strong> " . htmlspecialchars($name) . " &lt;" . htmlspecialchars($email) . "&gt;</p>
        <p><strong>Subject:</strong> " . htmlspecialchars($cleanSubject) . "</p>
        <p><strong>Message:</strong></p>
        <blockquote>" . nl2br(htmlspecialchars($message)) . "</blockquote>
      </div>
    </div>
  </body>
</html>";

// Allow skipping email sending to isolate issues; default auto-skip on localhost unless explicitly enabled
// Enable local send with any of:
//   - env WF_CONTACT_SEND_LOCAL=1
//   - query param ?send_email=1
//   - request body field send_email truthy
$allowLocalSend = $isLocal && (
    getenv('WF_CONTACT_SEND_LOCAL') === '1'
    || (isset($_GET['send_email']) && $_GET['send_email'] === '1')
    || (!empty($input['send_email']))
);
$skipEmail = $isLocal ? !$allowLocalSend : ((isset($_GET['skip_email']) && $_GET['skip_email'] === '1') || !empty($input['skip_email']));

// Send to admin, set reply-to to the user
try {
    $sentAdmin = $skipEmail ? true : EmailHelper::send($adminEmail, $adminSubject, $bodyAdmin, [
        'is_html' => true,
        'reply_to' => $email,
    ]);
} catch (Exception $e) {
    json_response(500, ['success' => false, 'error' => 'Failed to send your message. Please try again later.']);
}

// Optional: send acknowledgement to user (best-effort; do not fail if this fails)
$userSubject = 'We received your message — ' . $businessName;
$bodyUser = "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Thank you for contacting " . htmlspecialchars($businessName) . "</title>
  <style>
    body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .preheader { display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all; }
    .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .email-header { background: " . $brandPrimary . "; color:#fff; padding:16px; text-align:center; }
    .email-title { margin:0; font-size:20px; }
    .email-section { margin:16px 0; }
    .email-footer { margin-top:24px; font-size:12px; color:#666; text-align:center; }
    blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
    a { color: " . $brandSecondary . "; }
  </style>
</head>
<body class='email-body'>
  <div class='preheader'>Thanks for reaching out. We appreciate your interest and will be in touch as soon as possible.</div>
  <div class='email-header'>
    <h1 class='email-title'>" . htmlspecialchars($businessName) . "</h1>
  </div>
  <div class='email-wrapper'>
    <div class='email-section'>
      <p>Hi " . htmlspecialchars($name) . ",</p>
      <p>Thank you for contacting <strong>" . htmlspecialchars($businessName) . "</strong>. We appreciate your interest and our team will review your message shortly. We aim to respond as soon as possible.</p>
      <p><strong>Your message</strong></p>
      <blockquote>" . nl2br(htmlspecialchars($message)) . "</blockquote>
      <p>If you need to share additional details, simply reply to this email.</p>
    </div>
    <div class='email-footer'>
      <p class='m-0'>This acknowledgment was sent from <a href='https://whimsicalfrog.us'>whimsicalfrog.us</a></p>
      <p class='m-0'>Contact: <a href='mailto:" . htmlspecialchars(BusinessSettings::getBusinessEmail()) . "'>" . htmlspecialchars(BusinessSettings::getBusinessEmail()) . "</a></p>
    </div>
  </div>
</body>
</html>";
try {
    if (!$skipEmail) {
        EmailHelper::send($email, $userSubject, $bodyUser, [
            'is_html' => true,
            // ensure replies go to the business inbox
            'reply_to' => defined('FROM_EMAIL') ? FROM_EMAIL : BusinessSettings::getBusinessEmail(),
        ]);
    }
} catch (Exception $e) {
    // ignore
}

// Log email attempt(s)
try {
    EmailHelper::logEmail($adminEmail, $adminSubject, $sentAdmin ? 'sent' : 'failed', null, null);
} catch (Exception $e) {
    // ignore log errors
}

json_response(200, [
    'success' => true,
    'message' => 'Thanks! Your message has been sent.',
    'email_sent' => isset($sentAdmin) ? (bool)$sentAdmin : false,
    'email_skipped' => (bool)$skipEmail,
]);
