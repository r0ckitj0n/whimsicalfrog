<?php

// Auth Redirect Probe: sets WF_AUTH via Set-Cookie, then 302 redirects to whoami (and optionally to /shop)
// Usage: /api/auth_redirect_probe.php?token=...&next=whoami|shop
// Token is WF_AUTH_PROBE_TOKEN from env (default: wf_probe_2025_09)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

header('Cache-Control: no-store');
$token = $_GET['token'] ?? '';
$expected = getenv('WF_AUTH_PROBE_TOKEN') ?: 'wf_probe_2025_09';
if (!hash_equals($expected, (string)$token)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$next = $_GET['next'] ?? 'whoami';
try {
    // Prefer an admin user for visibility
    $row = Database::queryOne("SELECT id, username, role FROM users WHERE role=? ORDER BY id ASC LIMIT 1", [WF_Constants::ROLE_ADMIN]);
    if (!$row) {
        $row = Database::queryOne("SELECT id, username, role FROM users ORDER BY id ASC LIMIT 1", []);
    }
    if (!$row) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'no_users']);
        exit;
    }
    $uid = $row['id'];
    $hostFull = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us'; // may include port
    $host = $hostFull;
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    $p = explode('.', $host);
    $bd = $host;
    if (count($p) >= 2) {
        $bd = $p[count($p) - 2] . '.' . $p[count($p) - 1];
    }
    $isIp = (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host);
    $isLocal = ($host === 'localhost' || $host === '127.0.0.1' || $isIp);
    $dom = $isLocal ? '' : ('.' . $bd);
    $sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    // Set WF_AUTH cookie to client
    [$val, $exp] = wf_auth_make_cookie($uid);
    $sameSite = $sec ? 'None' : 'Lax';
    $opts1 = [ 'expires' => $exp, 'path' => '/', 'secure' => $sec, 'httponly' => true, 'samesite' => $sameSite ];
    if (!empty($dom)) {
        $opts1['domain'] = $dom;
    }
    @setcookie(wf_auth_cookie_name(), $val, $opts1);
    // Also a visible hint for quick UI checks
    $opts2 = [ 'expires' => $exp, 'path' => '/', 'secure' => $sec, 'httponly' => false, 'samesite' => $sameSite ];
    if (!empty($dom)) {
        $opts2['domain'] = $dom;
    }
    @setcookie('WF_AUTH_V', base64_encode(json_encode(['uid' => (string)$uid, 'role' => $row['role'] ?? null])), $opts2);

    // Build redirect target
    $scheme = $sec ? 'https' : 'http';
    if ($next === 'shop') {
        $target = $scheme . '://' . $hostFull . '/shop';
    } else {
        $target = $scheme . '://' . $hostFull . '/api/whoami.php?wf_auth_debug=1';
    }

    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
