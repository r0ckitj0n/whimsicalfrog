<?php
// Contact form submission API
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/email_config.php'; // Provides SMTP/FROM constants if configured
require_once __DIR__ . '/business_settings_helper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_response($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

// Accept JSON or form-encoded
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

// Basic honeypot check
if (!empty($input['website'])) {
    // Silently succeed to confuse bots
    json_response(200, ['success' => true, 'message' => 'Thanks!']);
}

// CSRF check
$csrf = $input['csrf'] ?? '';
if (empty($_SESSION['contact_csrf']) || !hash_equals($_SESSION['contact_csrf'], (string)$csrf)) {
    json_response(400, ['success' => false, 'error' => 'Invalid form token. Please refresh and try again.']);
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

// Prepare EmailHelper configuration using constants (fallback to BusinessSettings)
try {
    EmailHelper::configure([
        'smtp_enabled' => defined('SMTP_ENABLED') ? SMTP_ENABLED : false,
        'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : '',
        'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
        'smtp_username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
        'smtp_password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        'smtp_encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls',
        'from_email' => defined('FROM_EMAIL') ? FROM_EMAIL : BusinessSettings::getBusinessEmail(),
        'from_name' => defined('FROM_NAME') ? FROM_NAME : BusinessSettings::getBusinessName(),
        'reply_to' => defined('FROM_EMAIL') ? FROM_EMAIL : BusinessSettings::getBusinessEmail(),
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

// Build admin HTML body
$bodyAdmin = "<!DOCTYPE html>
<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<style>
  .hdr{background:$brandPrimary;color:#fff;padding:12px 16px;font-family:Arial,sans-serif}
  .wrap{font-family:Arial,sans-serif;max-width:640px;margin:0 auto;border:1px solid #eee}
  .sec{padding:16px}
  .label{color:#555;font-size:12px;text-transform:uppercase;letter-spacing:.06em}
  .val{margin:4px 0 12px 0}
  .msg{white-space:pre-wrap;line-height:1.45}
</style>
</head><body>
  <div class='wrap'>
    <div class='hdr'><strong>$businessName</strong> — New Contact Message</div>
    <div class='sec'>
      <div class='label'>From</div>
      <div class='val'>" . htmlspecialchars($name) . " &lt;" . htmlspecialchars($email) . "&gt;</div>
      <div class='label'>Subject</div>
      <div class='val'>" . htmlspecialchars($cleanSubject) . "</div>
      <div class='label'>Message</div>
      <div class='msg'>" . nl2br(htmlspecialchars($message)) . "</div>
    </div>
  </div>
</body></html>";

// Send to admin, set reply-to to the user
try {
    $sentAdmin = EmailHelper::send($adminEmail, $adminSubject, $bodyAdmin, [
        'is_html' => true,
        'reply_to' => $email,
    ]);
} catch (Exception $e) {
    json_response(500, ['success' => false, 'error' => 'Failed to send your message. Please try again later.']);
}

// Optional: send acknowledgement to user (best-effort; do not fail if this fails)
$userSubject = 'Thanks for contacting ' . $businessName;
$bodyUser = "<p>Hi " . htmlspecialchars($name) . ",</p>
<p>Thanks for reaching out! We've received your message and will get back to you soon.</p>
<p><strong>Your message:</strong></p>
<blockquote style='border-left:3px solid $brandSecondary;padding-left:10px;margin:10px 0'>" . nl2br(htmlspecialchars($message)) . "</blockquote>
<p>— $businessName</p>";
try {
    EmailHelper::send($email, $userSubject, $bodyUser, [
        'is_html' => true,
    ]);
} catch (Exception $e) {
    // ignore
}

// Log email attempt(s)
try {
    EmailHelper::logEmail($adminEmail, $adminSubject, $sentAdmin ? 'sent' : 'failed', null, null);
} catch (Exception $e) {
    // ignore log errors
}

json_response(200, ['success' => true, 'message' => 'Thanks! Your message has been sent.']);
