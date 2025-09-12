<?php
// Debug current session contents. Protected by WF_AUTH_PROBE_TOKEN.
// GET /api/debug_session.php?token=...

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';

$token = $_GET['token'] ?? '';
$expected = getenv('WF_AUTH_PROBE_TOKEN') ?: 'wf_probe_2025_09';
if (!hash_equals($expected, (string)$token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

try { ensureSessionStarted(); } catch (Throwable $e) {}

$out = [
    'ok' => true,
    'session_id' => session_id(),
    'session_active' => session_status() === PHP_SESSION_ACTIVE,
    'save_path' => ini_get('session.save_path'),
    'has_user' => !empty($_SESSION['user']),
    'keys' => array_keys($_SESSION ?? []),
];
if (!empty($_SESSION['user'])) {
    $u = $_SESSION['user'];
    $out['user'] = [
        'userId' => $u['userId'] ?? ($u['id'] ?? null),
        'username' => $u['username'] ?? null,
        'role' => $u['role'] ?? null,
    ];
}

echo json_encode($out);
