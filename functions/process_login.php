<?php
/**
 * api/process_login.php
 * Handles user authentication using centralized auth system.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database_logger.php';
require_once __DIR__ . '/../includes/auth_cookie.php';
require_once __DIR__ . '/../includes/helpers/LoginHelper.php';
require_once __DIR__ . '/../includes/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/../includes/helpers/ProfileCompletionHelper.php';
require_once __DIR__ . '/../includes/user_meta.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_init([
        'name' => 'PHPSESSID',
        'lifetime' => 0,
        'path' => '/',
        'domain' => AuthSessionHelper::getCookieDomain(),
        'secure' => AuthSessionHelper::isHttps(),
        'httponly' => true,
        'samesite' => 'None',
    ]);
}

if (!ob_get_level())
    ob_start();

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    if (function_exists('header_remove'))
        @header_remove('Access-Control-Allow-Origin');
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    while (ob_get_level() > 0)
        ob_end_clean();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = LoginHelper::parseInput();
    if (!isset($data['username']) || !isset($data['password'])) {
        LoginHelper::logTrace('login_input_missing', ['ct' => $_SERVER['CONTENT_TYPE'] ?? '']);
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }

    $user = LoginHelper::validateAuth((string) $data['username'], (string) $data['password']);

    if ($user) {
        $missingProfileFields = wf_profile_missing_fields($user);
        $profileCompletionRequired = count($missingProfileFields) > 0;
        set_user_meta_many($user['id'], [
            'profile_completion_required' => $profileCompletionRequired ? '1' : '0',
        ]);

        if (session_status() !== PHP_SESSION_ACTIVE)
            session_start();
        @session_regenerate_id(false);

        $_SESSION['user'] = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone_number' => $user['phone_number'] ?? null,
            'address_line_1' => $user['address_line_1'] ?? null,
            'address_line_2' => $user['address_line_2'] ?? null,
            'city' => $user['city'] ?? null,
            'state' => $user['state'] ?? null,
            'zip_code' => $user['zip_code'] ?? null,
            'profile_missing_fields' => $missingProfileFields,
            'profile_completion_required' => $profileCompletionRequired
        ];
        $_SESSION['auth_time'] = time();

        loginUser($user);
        DatabaseLogger::getInstance()->logUserActivity('login', 'User logged in successfully', 'user', $user['id'], $user['id']);

        $dom = AuthSessionHelper::getCookieDomain();
        $sec = AuthSessionHelper::isHttps();
        wf_auth_set_cookie($user['id'], $dom, $sec);
        wf_auth_set_client_hint($user['id'], $user['role'] ?? null, $dom, $sec);

        @setcookie(session_name(), session_id(), ['expires' => 0, 'path' => '/', 'secure' => $sec, 'httponly' => true, 'samesite' => $sec ? 'None' : 'Lax', 'domain' => $dom ?: null]);
        @session_write_close();

        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone_number' => $user['phone_number'] ?? null,
            'address_line_1' => $user['address_line_1'] ?? null,
            'address_line_2' => $user['address_line_2'] ?? null,
            'city' => $user['city'] ?? null,
            'state' => $user['state'] ?? null,
            'zip_code' => $user['zip_code'] ?? null,
            'profile_missing_fields' => $missingProfileFields,
            'profile_completion_required' => $profileCompletionRequired,
            'redirectUrl' => $_SESSION['redirect_after_login'] ?? null
        ]);
        exit;
    } else {
        DatabaseLogger::getInstance()->logUserActivity('login_failed', "Failed login attempt for: {$data['username']}");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
