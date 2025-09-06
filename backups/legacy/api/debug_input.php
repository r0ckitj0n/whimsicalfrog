<?php
// Lightweight diagnostics endpoint to inspect request metadata and body characteristics
// Does NOT require authentication and does NOT echo the request body.

// CORS (dev-friendly)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight early
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error handling and logging (keep lightweight and independent of DB)
ini_set('display_errors', 0);
ini_set('html_errors', 0);
ini_set('log_errors', 1);
// Keep logs consistent with the rest of the API stack
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Helper: fetch headers in a server-agnostic way
function dbg_getallheaders_sanitized(): array {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (!is_array($headers)) {
            $headers = [];
        }
    } else {
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
    }

    // Redact sensitive headers
    $redacted = [];
    foreach ($headers as $k => $v) {
        $lk = strtolower((string)$k);
        if ($lk === 'authorization' || $lk === 'cookie' || $lk === 'set-cookie') {
            $redacted[$k] = '[REDACTED]';
        } else {
            // Normalize to string
            $redacted[$k] = is_array($v) ? implode(', ', $v) : (string)$v;
        }
    }
    return $redacted;
}

// Helper: determine HTTPS
function dbg_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

// Read raw input (do not output it)
$raw = file_get_contents('php://input');
$rawLen = strlen($raw ?? '');
$decoded = null;
$jsonErrorCode = null;
$jsonErrorMsg = null;
if ($rawLen > 0) {
    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        $jsonErrorCode = json_last_error();
        $jsonErrorMsg = function_exists('json_last_error_msg') ? json_last_error_msg() : 'unknown';
    }
}

$headers = dbg_getallheaders_sanitized();
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;

// Prepare response
$response = [
    'success' => true,
    'info' => [
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'https' => dbg_is_https(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
        'sapi' => php_sapi_name(),
    ],
    'headers' => $headers,
    'body_diagnostics' => [
        'received' => $rawLen > 0,
        'length' => $rawLen,
        // Hash allows correlating bodies without exposing content
        'sha256' => $rawLen > 0 ? hash('sha256', $raw) : null,
        'content_type' => $contentType,
        'content_length_header' => $contentLength,
        'is_json' => is_array($decoded),
        'json_error_code' => $jsonErrorCode,
        'json_error_msg' => $jsonErrorMsg,
        // Do not include body sample or full body for safety
    ],
    'superglobals' => [
        'get_count' => is_array($_GET) ? count($_GET) : 0,
        'post_count' => is_array($_POST) ? count($_POST) : 0,
    ],
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
