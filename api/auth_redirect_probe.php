<?php

// Local-only auth redirect probe: establishes an admin session + WF_AUTH cookies, then redirects.
// Usage on loopback/dev only:
//   /api/auth_redirect_probe.php?token=...&next=whoami|shop|admin
// Optional: &section=orders&view=<order_id> when next=admin

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
$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true); // pragma: allowlist secret
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

$next = strtolower(trim((string)($_GET['next'] ?? 'whoami')));
$allowedNext = ['whoami', 'shop', 'admin'];
if (!in_array($next, $allowedNext, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'invalid_next']);
    exit;
}

try {
    // Prefer an admin user for visibility
    $row = Database::queryOne(
        "SELECT id, username, email, role, first_name, last_name, phone_number
         FROM users
         WHERE role=?
         ORDER BY id ASC
         LIMIT 1",
        [WF_Constants::ROLE_ADMIN]
    );
    if (!$row) {
        $row = Database::queryOne(
            "SELECT id, username, email, role, first_name, last_name, phone_number
             FROM users
             ORDER BY id ASC
             LIMIT 1",
            []
        );
    }
    if (!$row) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'no_users']);
        exit;
    }

    $uid = $row['id'];
    $cookieHost = $hostFull;
    if (strpos($cookieHost, ':') !== false) {
        $cookieHost = explode(':', $cookieHost)[0];
    }
    $p = explode('.', $cookieHost);
    $bd = $cookieHost;
    if (count($p) >= 2) {
        $bd = $p[count($p) - 2] . '.' . $p[count($p) - 1];
    }
    $isIp = (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $cookieHost);
    $isLocal = $isLocalhost || $isIp || ($cookieHost === '127.0.0.1'); // pragma: allowlist secret
    $dom = $isLocal ? '' : ('.' . $bd);
    $sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    // Establish a real PHP session for local probe use. WF_AUTH alone is no longer
    // enough for requireAdmin() after logout was made authoritative (no auto-reconstruct).
    require_once __DIR__ . '/../includes/session.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_init([
            'name' => 'PHPSESSID',
            'lifetime' => 0,
            'path' => '/',
            'domain' => $dom,
            'secure' => $sec,
            'httponly' => true,
            'samesite' => $sec ? 'None' : 'Lax',
        ]);
    }
    $full = Database::queryOne(
        'SELECT id, username, email, role, first_name, last_name, phone_number FROM users WHERE id = ? LIMIT 1',
        [$uid]
    ) ?: $row;
    $_SESSION['user'] = [
        'user_id' => $full['id'],
        'username' => $full['username'] ?? null,
        'email' => $full['email'] ?? null,
        'role' => $full['role'] ?? 'admin',
        'first_name' => $full['first_name'] ?? null,
        'last_name' => $full['last_name'] ?? null,
        'phone_number' => $full['phone_number'] ?? null,
    ];

    // Some proxy layers preserve only one Set-Cookie header; set hint first, auth last.
    wf_auth_set_client_hint($uid, $row['role'] ?? null, $dom, $sec);
    wf_auth_set_cookie($uid, $dom, $sec);

    // Relative redirects keep the browser on the Vite origin (e.g. :5176) when
    // the API is reached through a changeOrigin proxy that rewrites Host to :8080.
    if ($next === 'shop') {
        $target = '/shop';
    } elseif ($next === 'admin') {
        $section = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['section'] ?? 'orders')) ?: 'orders';
        $target = '/admin?section=' . rawurlencode($section);
        $view = trim((string)($_GET['view'] ?? ''));
        if ($view !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $view)) {
            $target .= '&view=' . rawurlencode($view);
        }
    } else {
        $target = '/api/whoami.php?wf_auth_debug=1';
    }

    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
