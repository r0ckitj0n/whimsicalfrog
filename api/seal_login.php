<?php
// Seal Login: set canonical cookies from a full-page response and redirect to target
// Usage: /api/seal_login.php?to=/desired/path

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

// Initialize session (proxy-aware secure)
if (session_status() !== PHP_SESSION_ACTIVE) {
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) { $baseDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1]; }
    $dom = '.' . $baseDomain;
    $sec = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );
    session_init([
        'name' => 'PHPSESSID',
        'lifetime' => 0,
        'path' => '/',
        'domain' => $dom,
        'secure' => $sec,
        'httponly' => true,
        'samesite' => 'None',
    ]);
}

$target = $_GET['to'] ?? '/';
$target = is_string($target) && strlen($target) > 0 ? $target : '/';
// Only allow relative redirects
if (strpos($target, '://') !== false) { $target = '/'; }

try {
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
    $p = explode('.', $host); $bd = $host; if (count($p) >= 2) { $bd = $p[count($p)-2] . '.' . $p[count($p)-1]; }
    $dom = '.' . $bd;
    $sec = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );

    // Determine current user from session or WF_AUTH
    $user = getCurrentUser();
    if (!$user) {
        $parsed = wf_auth_parse_cookie($_COOKIE[wf_auth_cookie_name()] ?? '');
        if (is_array($parsed) && !empty($parsed['userId'])) {
            try {
                $row = Database::queryOne('SELECT id, username, email, role FROM users WHERE id = ?', [$parsed['userId']]);
                if ($row) {
                    $user = [ 'userId' => $row['id'], 'username' => $row['username'], 'email' => $row['email'], 'role' => $row['role'] ];
                    $_SESSION['user'] = $user;
                } else {
                    $user = [ 'userId' => $parsed['userId'] ];
                    $_SESSION['user'] = $user;
                }
            } catch (Throwable $e) {
                $user = [ 'userId' => $parsed['userId'] ];
                $_SESSION['user'] = $user;
            }
        }
    }

    if ($user && !empty($user['userId'])) {
        // Refresh both cookies (domain-scoped and host-only)
        try {
            wf_auth_set_cookie($user['userId'], $dom, $sec);
            [$valTmp, $expTmp] = wf_auth_make_cookie($user['userId']);
            @setcookie(wf_auth_cookie_name(), $valTmp, [ 'expires' => $expTmp, 'path' => '/', 'secure' => $sec, 'httponly' => true, 'samesite' => 'None' ]);
            wf_auth_set_client_hint($user['userId'], $user['role'] ?? null, $dom, $sec);
        } catch (Throwable $e) {}
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
            @setcookie(session_name(), session_id(), [ 'expires' => 0, 'path' => '/', 'domain' => $dom, 'secure' => $sec, 'httponly' => true, 'samesite' => 'None' ]);
            @setcookie(session_name(), session_id(), [ 'expires' => 0, 'path' => '/', 'secure' => $sec, 'httponly' => true, 'samesite' => 'None' ]);
        } catch (Throwable $e) {}
    }

    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'seal_exception', 'message' => $e->getMessage()]);
}
