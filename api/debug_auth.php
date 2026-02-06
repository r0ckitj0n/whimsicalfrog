<?php
/**
 * api/debug_auth.php
 * Debug endpoint to check authentication state, cookies, and session
 */

require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

header('Content-Type: application/json');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'cookies' => [
        'WF_AUTH' => $_COOKIE['WF_AUTH'] ?? null,
        'WF_AUTH_V' => $_COOKIE['WF_AUTH_V'] ?? null,
        'WF_LOGOUT_IN_PROGRESS' => $_COOKIE['WF_LOGOUT_IN_PROGRESS'] ?? null,
        'session_cookie' => $_COOKIE[session_name()] ?? null,
    ],
    'session' => [
        'status' => session_status(),
        'id' => session_id(),
        'user' => $_SESSION['user'] ?? null,
    ],
    'auth_check' => [
        'isLoggedIn' => isLoggedIn(),
        'currentUser' => getCurrentUser(),
    ],
    'request' => [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]
];

echo json_encode($debug, JSON_PRETTY_PRINT);
exit;
