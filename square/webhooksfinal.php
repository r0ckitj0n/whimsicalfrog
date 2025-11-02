<?php
// Square Webhooks endpoint (production)
require_once __DIR__ . '/../api/config.php';
@require_once __DIR__ . '/../includes/secret_store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
$signature = $headers['x-square-hmacsha256-signature'] ?? ($headers['x-square-signature'] ?? '');
$secretPresent = function_exists('secret_has') ? (secret_has('square_webhook_signature_key') === true) : false;

// Optional signature verification when secret is configured
$verified = false;
if ($secretPresent && function_exists('secret_get') && is_string($signature) && $signature !== '') {
    try {
        $secret = secret_get('square_webhook_signature_key');
        if (is_string($secret) && $secret !== '') {
            $calc = base64_encode(hash_hmac('sha256', $raw, $secret, true));
            $verified = hash_equals($calc, $signature);
        }
    } catch (Throwable $e) { $verified = false; }
}

try {
    error_log('[Square Webhook PROD] delivery received; verified=' . ($verified ? '1' : '0') . '; bytes=' . strlen($raw));
} catch (Throwable $e) {}

// TODO: add domain-specific handling (order updates, refunds) as needed

echo json_encode(['success' => true, 'environment' => 'production', 'verified' => $verified]);
