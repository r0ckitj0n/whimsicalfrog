<?php
// Debug session state for troubleshooting authentication issues
ob_start();
ob_clean();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();

header('Content-Type: application/json');

$debug_info = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION ?? [],
    'session_keys' => array_keys($_SESSION ?? []),
    'cookie_params' => session_get_cookie_params(),
    'server_info' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'not set',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'not set'
    ]
];

// Check authentication status
$auth_status = [
    'is_authenticated' => false,
    'user_role' => 'none',
    'auth_method' => 'none'
];

if (isset($_SESSION['user']['role'])) {
    $auth_status['is_authenticated'] = true;
    $auth_status['user_role'] = $_SESSION['user']['role'];
    $auth_status['auth_method'] = 'user.role';
} elseif (isset($_SESSION['role'])) {
    $auth_status['is_authenticated'] = true;
    $auth_status['user_role'] = $_SESSION['role'];
    $auth_status['auth_method'] = 'role';
} elseif (isset($_SESSION['user_role'])) {
    $auth_status['is_authenticated'] = true;
    $auth_status['user_role'] = $_SESSION['user_role'];
    $auth_status['auth_method'] = 'user_role';
}

echo json_encode([
    'success' => true,
    'debug_info' => $debug_info,
    'auth_status' => $auth_status,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?> 