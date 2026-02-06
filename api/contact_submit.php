<?php
/**
 * api/contact_submit.php
 * API for contact form submission
 */

if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if (function_exists('ini_set'))
        @ini_set('display_errors', '0');
}
ob_start();

register_shutdown_function(function () {
    if (!empty($GLOBALS['__wf_contact_json_done']))
        return;
    $err = error_get_last();
    while (ob_get_level() > 0)
        @ob_end_clean();
    if (!headers_sent())
        header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error handling your request.']);
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/helpers/ContactSubmitHelper.php';
require_once __DIR__ . '/../includes/Constants.php';

if (session_status() !== PHP_SESSION_ACTIVE)
    session_start();

function json_response($statusCode, $data)
{
    $GLOBALS['__wf_contact_json_done'] = true;
    http_response_code($statusCode);
    ob_get_clean();
    echo json_encode($data);
    exit;
}

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$isDiag = $isLocal && (($_GET['diag'] ?? '') === '1');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;

if ($isDiag || ($isLocal && !empty($input['diag']))) {
    Response::json([
        'success' => true,
        'mode' => 'diagnostic',
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
        'has_session_csrf' => !empty($_SESSION['contact_csrf']),
        'smtp_enabled' => defined('SMTP_ENABLED') ? (bool) SMTP_ENABLED : false
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    json_response(405, ['success' => false, 'error' => 'Method not allowed']);
if (!empty($input['website']))
    json_response(200, ['success' => true, 'message' => 'Thanks!']);

$csrf = $input['csrf'] ?? '';
if (!$isLocal && (empty($_SESSION['contact_csrf']) || !hash_equals($_SESSION['contact_csrf'], (string) $csrf))) {
    json_response(400, ['success' => false, 'error' => 'Invalid form token.']);
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    json_response(400, ['success' => false, 'error' => 'Missing or invalid fields.']);
}

// Configure EmailHelper using BusinessSettings
$settings = BusinessSettings::getByCategory('email');
$smtpEnabledVal = $settings['smtp_enabled'] ?? false;
$smtpEnabled = is_bool($smtpEnabledVal) ? $smtpEnabledVal : in_array(strtolower((string) $smtpEnabledVal), ['1', 'true', 'yes', 'on'], true);

EmailHelper::configure([
    'smtp_enabled' => $smtpEnabled,
    'from_email' => BusinessSettings::getBusinessEmail(),
    'from_name' => BusinessSettings::getBusinessName(),
]);

$adminEmail = BusinessSettings::getAdminEmail() ?: BusinessSettings::getBusinessEmail();
$brandPrimary = BusinessSettings::getPrimaryColor();
$brandSecondary = BusinessSettings::getSecondaryColor();
$business_name = BusinessSettings::getBusinessName();
$cleanSubject = $subject ?: 'New contact form submission';

$bodyAdmin = ContactSubmitHelper::getAdminEmailBody($business_name, $brandPrimary, $name, $email, $cleanSubject, $message);
$sentAdmin = EmailHelper::send($adminEmail, "[$business_name] $cleanSubject", $bodyAdmin, ['is_html' => true, 'reply_to' => $email]);

$bodyUser = ContactSubmitHelper::getUserAckEmailBody($business_name, $brandPrimary, $brandSecondary, $name, $message);
EmailHelper::send($email, "We received your message — $business_name", $bodyUser, ['is_html' => true, 'reply_to' => $adminEmail]);

EmailHelper::logEmail($adminEmail, "[$business_name] $cleanSubject", $sentAdmin ? WF_Constants::EMAIL_STATUS_SENT : WF_Constants::EMAIL_STATUS_FAILED, null, null);

json_response(200, ['success' => true, 'message' => 'Thanks! Your message has been sent.']);
