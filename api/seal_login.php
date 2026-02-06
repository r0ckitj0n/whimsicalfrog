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
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) {
        $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    }
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
if (strpos($target, '://') !== false) {
    $target = '/';
}

try {
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    $p = explode('.', $host);
    $bd = $host;
    if (count($p) >= 2) {
        $bd = $p[count($p) - 2] . '.' . $p[count($p) - 1];
    }
    $dom = '.' . $bd;
    $sec = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );

    // Determine current user from session - manual cookie reconstruction removed
    // to prevent auto-login after logout and ensure session path parity.
    $user = getCurrentUser();

    if ($user && !empty($user['user_id'])) {
        try {
            error_log('[AUTH-SEAL] ' . json_encode([
                'time' => date('c'),
                'event' => 'seal_start',
                'user_id' => $user['user_id'],
                'domain' => $dom,
                'secure' => $sec,
                'cookie_header_in' => isset($_SERVER['HTTP_COOKIE']) ? substr((string) $_SERVER['HTTP_COOKIE'], 0, 300) : null,
            ]));
        } catch (Throwable $e) {
        }
        // Refresh cookies (domain-scoped only); set WF_AUTH first, then PHPSESSID
        try {
            // WF_AUTH (HttpOnly) + visible client hint
            wf_auth_set_cookie($user['user_id'], $dom, $sec);
            wf_auth_set_client_hint($user['user_id'], $user['role'] ?? null, $dom, $sec);
        } catch (Throwable $e) {
        }
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            // Single canonical PHPSESSID (domain-scoped)
            $sameSite = $sec ? 'None' : 'Lax';
            @setcookie(session_name(), session_id(), ['expires' => 0, 'path' => '/', 'domain' => $dom, 'secure' => $sec, 'httponly' => true, 'samesite' => $sameSite]);
        } catch (Throwable $e) {
        }
        try {
            error_log('[AUTH-SEAL] ' . json_encode([
                'time' => date('c'),
                'event' => 'seal_set',
                'sid' => session_id(),
                'user_id' => $user['user_id'],
                'domain' => $dom,
                'secure' => $sec,
            ]));
        } catch (Throwable $e) {
        }
    }

    // Ensure session is written before redirect to avoid losing user state
    try {
        @session_write_close();
    } catch (Throwable $e) {
    }
    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'seal_exception', 'message' => $e->getMessage()]);
}
