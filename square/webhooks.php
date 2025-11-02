<?php
// Square Webhooks endpoint (sandbox)
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

// Optional: best-effort signature presence check (do not hard-fail without configured secret)
$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
$hasSig = isset($headers['x-square-hmacsha256-signature']) || isset($headers['x-square-signature']);
$secretPresent = function_exists('secret_has') ? (secret_has('square_webhook_signature_key') === true) : false;

// Log minimal receipt (avoid storing secrets or full payload in production logs)
try {
    error_log('[Square Webhook] sandbox delivery received; hasSig=' . ($hasSig ? '1' : '0') . '; bytes=' . strlen($raw));
} catch (Throwable $e) {}

// Respond success; actual business logic can be added later (e.g., order updates)
echo json_encode(['success' => true, 'environment' => 'sandbox']);
