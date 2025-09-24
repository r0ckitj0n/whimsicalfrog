<?php

// Minimal whoami endpoint: returns current authenticated user info from session
// Response shape: { success: true, userId: <int|null>, userIdRaw?: string, username?: string, role?: string }

// Standardize session initialization to prevent host-only cookie conflicts
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth_cookie.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) {
        $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    }
    $cookieDomain = '.' . $baseDomain;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_init([
        'name' => 'PHPSESSID',
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'None',
    ]);
}

// Important: reconstruct session from WF_AUTH if needed
try {
    ensureSessionStarted();
} catch (\Throwable $e) { /* non-fatal */
}

// CORS: reflect origin and allow credentials so cookies are included cross-origin in dev
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$userId = null; // normalized (string) id for client consumption
$userIdRaw = null;
$username = null;
$role = null;

if (!empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
    // Prefer explicit userId, fallback to id
    if (isset($user['userId'])) {
        $userIdRaw = is_scalar($user['userId']) ? (string)$user['userId'] : null;
    } elseif (isset($user['id'])) {
        $userIdRaw = is_scalar($user['id']) ? (string)$user['id'] : null;
    }
    $username = $user['username'] ?? null;
    $role = $user['role'] ?? null;
} elseif (isset($_SESSION['user_id'])) {
    $userIdRaw = is_scalar($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
}

// Normalize: preserve string IDs (do not coerce to int)
if ($userIdRaw !== null && $userIdRaw !== '') {
    $userId = $userIdRaw;
}

// Bump a heartbeat counter to verify session persistence across requests
try {
    $_SESSION['__wf_heartbeat'] = (int)($_SESSION['__wf_heartbeat'] ?? 0) + 1;
} catch (\Throwable $e) { /* noop */
}

// Check WF_AUTH fallback cookie
$wfAuthRaw = $_COOKIE[wf_auth_cookie_name()] ?? null;
$wfAuthParsed = wf_auth_parse_cookie($wfAuthRaw ?? '');
// Presence of PHPSESSID cookie
$sessCookiePresent = isset($_COOKIE[session_name()]);
// Env hints
$envHost = $_SERVER['HTTP_HOST'] ?? null;
$envXfp  = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
$envHttps = $_SERVER['HTTPS'] ?? null;
$cookieHdrLen = isset($_SERVER['HTTP_COOKIE']) ? strlen((string)$_SERVER['HTTP_COOKIE']) : null;

// Standard success payload with temporary diagnostics (safe)
$payload = [
    'success' => true,
    'userId' => $userId,
    'sid' => session_id(),
    'sessionActive' => session_status() === PHP_SESSION_ACTIVE,
    'hasUserSession' => !empty($_SESSION['user']),
    'heartbeat' => $_SESSION['__wf_heartbeat'] ?? null,
    'savePath' => ini_get('session.save_path'),
    'wfAuthPresent' => $wfAuthRaw !== null,
    'wfAuthParsedUserId' => is_array($wfAuthParsed) ? ($wfAuthParsed['userId'] ?? null) : null,
    'phpSessCookiePresent' => $sessCookiePresent,
    'httpHost' => $envHost,
    'xForwardedProto' => $envXfp,
    'httpsFlag' => $envHttps,
    'cookieHeaderLen' => $cookieHdrLen,
];
if ($userIdRaw !== null && $userIdRaw !== '') {
    $payload['userIdRaw'] = $userIdRaw;
}
if ($username !== null) {
    $payload['username'] = (string)$username;
}
if ($role !== null) {
    $payload['role'] = (string)$role;
}

echo json_encode($payload);

// Optional: verbose diagnostics when explicitly requested
try {
    if (isset($_GET['wf_auth_debug']) && $_GET['wf_auth_debug'] === '1') {
        $log = '[WHOAMI-DEBUG] ' . json_encode([
            'time' => date('c'),
            'sid' => $payload['sid'],
            'userId' => $payload['userId'],
            'sessionActive' => $payload['sessionActive'],
            'hasUserSession' => $payload['hasUserSession'],
            'wfAuthPresent' => $payload['wfAuthPresent'],
            'wfAuthParsedUserId' => $payload['wfAuthParsedUserId'],
            'phpSessCookiePresent' => $payload['phpSessCookiePresent'],
            'httpHost' => $payload['httpHost'],
            'xForwardedProto' => $payload['xForwardedProto'],
            'httpsFlag' => $payload['httpsFlag'],
            'cookieHeaderLen' => $payload['cookieHeaderLen'],
            'cookieHeader' => isset($_SERVER['HTTP_COOKIE']) ? substr($_SERVER['HTTP_COOKIE'], 0, 500) : null,
            'savePath' => $payload['savePath'],
        ]);
        error_log($log);
    }
} catch (\Throwable $e) { /* noop */
}
