<?php
// Auth probe: validates WF_AUTH-based reconstruction via HTTP roundtrip.
// Protected by token WF_AUTH_PROBE_TOKEN (env) passed as ?token=... or Header X-WF-Token

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

$token = $_GET['token'] ?? ($_SERVER['HTTP_X_WF_TOKEN'] ?? '');
$expected = getenv('WF_AUTH_PROBE_TOKEN') ?: 'wf_probe_2025_09';
if (!hash_equals($expected, (string)$token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

try {
    // Find an admin; fall back to any user
    $user = Database::queryOne("SELECT id, username, role FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1", []);
    if (!$user) {
        $user = Database::queryOne("SELECT id, username, role FROM users ORDER BY id ASC LIMIT 1", []);
    }
    if (!$user) {
        echo json_encode(['ok' => false, 'error' => 'no_users']);
        exit;
    }
    $uid = $user['id'];
    // Build WF_AUTH value directly
    [$val, $exp] = wf_auth_make_cookie($uid);

    // Probe whoami with WF_AUTH in Cookie header
    $origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    $base = $origin . '://' . $host;
    $url = $base . '/api/whoami.php';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . wf_auth_cookie_name() . '=' . $val,
        'Accept: application/json',
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = null;
    if ($resp && $code === 200) {
        $json = json_decode($resp, true);
    }

    echo json_encode([
        'ok' => true,
        'probe' => 'wf_auth_whoami',
        'user' => ['id' => $uid, 'username' => $user['username'] ?? null, 'role' => $user['role'] ?? null],
        'http' => ['code' => $code, 'error' => $err ?: null],
        'whoami' => $json,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
