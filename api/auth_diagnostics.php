<?php
// Auth and environment diagnostics (READ-ONLY). Protected by WF_AUTH_PROBE_TOKEN.
// GET /api/auth_diagnostics.php?token=...

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

$token = $_GET['token'] ?? '';
$expected = getenv('WF_AUTH_PROBE_TOKEN') ?: 'wf_probe_2025_09';
if (!hash_equals($expected, (string)$token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

try { ensureSessionStarted(); } catch (Throwable $e) {}

try {
    $who = getCurrentUser();
    $cookieSessPresent = isset($_COOKIE[session_name()]);
    $cookieSessVal = $cookieSessPresent ? (string)$_COOKIE[session_name()] : null;
    $wfRaw = $_COOKIE[wf_auth_cookie_name()] ?? null;
    $wfParsed = wf_auth_parse_cookie($wfRaw ?? '');

    $dbInfo = [
        'users_total' => null,
        'admins_total' => null,
        'sample_admin' => null,
        'schema_ok' => null,
        'db_version' => null,
    ];
    try {
        $dbInfo['users_total'] = (int)Database::queryValue('SELECT COUNT(*) FROM users', []);
    } catch (Throwable $e) { $dbInfo['users_total'] = null; }
    try {
        $dbInfo['admins_total'] = (int)Database::queryValue("SELECT COUNT(*) FROM users WHERE LOWER(role)='admin'", []);
    } catch (Throwable $e) { $dbInfo['admins_total'] = null; }
    try {
        $dbInfo['sample_admin'] = Database::queryOne("SELECT id, username, role, LENGTH(password) AS pw_len, LEFT(password,4) AS pw_prefix FROM users WHERE LOWER(role)='admin' ORDER BY id ASC LIMIT 1", []);
    } catch (Throwable $e) { $dbInfo['sample_admin'] = null; }
    try {
        $dbInfo['schema_ok'] = Database::queryOne("SHOW COLUMNS FROM users LIKE 'password'", []) ? true : false;
    } catch (Throwable $e) { $dbInfo['schema_ok'] = null; }
    try {
        $dbInfo['db_version'] = Database::queryValue('SELECT VERSION()', []);
    } catch (Throwable $e) { $dbInfo['db_version'] = null; }

    $env = [
        'http_host' => $_SERVER['HTTP_HOST'] ?? null,
        'https_flag' => $_SERVER['HTTPS'] ?? null,
        'xfp' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
        'xfssl' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? null,
        'cookie_header_len' => isset($_SERVER['HTTP_COOKIE']) ? strlen((string)$_SERVER['HTTP_COOKIE']) : null,
        'session_save_path' => ini_get('session.save_path'),
        'session_id' => session_id(),
        'session_active' => session_status() === PHP_SESSION_ACTIVE,
    ];

    echo json_encode([
        'ok' => true,
        'env' => $env,
        'cookies' => [
            'phpSessPresent' => $cookieSessPresent,
            'phpSessIdShort' => $cookieSessVal ? substr($cookieSessVal, 0, 8) : null,
            'wfAuthPresent' => $wfRaw !== null,
            'wfAuthParsedUserId' => is_array($wfParsed) ? ($wfParsed['userId'] ?? null) : null,
        ],
        'session' => [
            'hasUser' => !empty($_SESSION['user']),
            'user' => $who,
        ],
        'db' => $dbInfo,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
