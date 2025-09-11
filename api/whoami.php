<?php
// Minimal whoami endpoint: returns current authenticated user info from session
// Response shape: { success: true, userId: <int|null>, userIdRaw?: string, username?: string, role?: string }

// Standardize session initialization to prevent host-only cookie conflicts
require_once __DIR__ . '/../includes/session.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) {
        $baseDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
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
        'samesite' => 'Lax',
    ]);
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$userId = null;
$userIdRaw = null;
$username = null;
$role = null;

if (!empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
    // Prefer explicit userId, fallback to id
    if (isset($user['userId'])) {
        $userId = $user['userId'];
        $userIdRaw = is_scalar($user['userId']) ? (string)$user['userId'] : null;
    } elseif (isset($user['id'])) {
        $userId = $user['id'];
        $userIdRaw = is_scalar($user['id']) ? (string)$user['id'] : null;
    }
    $username = $user['username'] ?? null;
    $role = $user['role'] ?? null;
} elseif (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userIdRaw = is_scalar($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
}

// Normalize: only accept positive integer IDs; otherwise treat as unauthenticated
if (!is_null($userId)) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        $userId = null;
    }
}

// Standard success payload without sensitive data
$payload = [
    'success' => true,
    'userId' => $userId,
];
if ($userIdRaw !== null && $userIdRaw !== '') { $payload['userIdRaw'] = $userIdRaw; }
if ($username !== null) { $payload['username'] = (string)$username; }
if ($role !== null) { $payload['role'] = (string)$role; }

echo json_encode($payload);
