<?php
// Ensure auth cookies are persisted from same-origin response after login
// POST only. Idempotent. Returns minimal user info.

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

try { ensureSessionStarted(); } catch (Throwable $e) {}

// Determine current user from session or WF_AUTH
$user = getCurrentUser();
if (!$user) {
    $parsed = wf_auth_parse_cookie($_COOKIE[wf_auth_cookie_name()] ?? '');
    if (is_array($parsed) && !empty($parsed['userId'])) {
        // Try DB fetch for role/username
        try {
            $row = Database::queryOne('SELECT id, username, email, role FROM users WHERE id = ?', [$parsed['userId']]);
            if ($row) {
                $user = [
                    'userId' => $row['id'],
                    'username' => $row['username'] ?? null,
                    'email' => $row['email'] ?? null,
                    'role' => $row['role'] ?? null,
                ];
                $_SESSION['user'] = $user; // hydrate session
            } else {
                $user = [ 'userId' => $parsed['userId'] ];
            }
        } catch (Throwable $e) {
            $user = [ 'userId' => $parsed['userId'] ];
        }
    }
}

if (!$user || empty($user['userId'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No authenticated user']);
    exit;
}

// Compute cookie domain/secure
$host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
$p = explode('.', $host); $bd = $host; if (count($p) >= 2) { $bd = $p[count($p)-2] . '.' . $p[count($p)-1]; }
$dom = '.' . $bd;
$sec = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443) || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');

// Refresh WF_AUTH and WF_AUTH_V
try { wf_auth_set_cookie($user['userId'], $dom, $sec); } catch (Throwable $e) {}
try { wf_auth_set_client_hint($user['userId'], $user['role'] ?? null, $dom, $sec); } catch (Throwable $e) {}

// Ensure session cookie is canonical
try {
    @session_regenerate_id(true);
    $sameSite = $sec ? 'None' : 'Lax';
    @setcookie(session_name(), session_id(), [ 'expires' => 0, 'path' => '/', 'domain' => $dom, 'secure' => $sec, 'httponly' => true, 'samesite' => $sameSite ]);
} catch (Throwable $e) {}

try { @session_write_close(); } catch (Throwable $e) {}

echo json_encode([
    'ok' => true,
    'userId' => (string)$user['userId'],
    'username' => $user['username'] ?? null,
    'role' => $user['role'] ?? null,
]);
