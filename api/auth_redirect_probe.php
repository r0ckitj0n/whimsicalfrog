<?php

// Local-only auth redirect probe: sets WF_AUTH via Set-Cookie, then redirects.
// Usage on localhost only: /api/auth_redirect_probe.php?token=...&next=whoami|shop

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

header('Cache-Control: no-store');

$hostFull = $_SERVER['HTTP_HOST'] ?? '';
$host = strtolower(trim($hostFull));
if (str_starts_with($host, '[') && strpos($host, ']') !== false) {
    $host = substr($host, 1, strpos($host, ']') - 1);
} elseif (substr_count($host, ':') === 1) {
    $host = explode(':', $host)[0];
}
$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
if (!$isLocalhost) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

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
    $host = $hostFull; // may include port
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

    // Some proxy layers preserve only one Set-Cookie header; set hint first, auth last.
    wf_auth_set_client_hint($uid, $row['role'] ?? null, $dom, $sec);
    wf_auth_set_cookie($uid, $dom, $sec);

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
