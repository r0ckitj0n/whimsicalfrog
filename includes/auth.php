<?php

/**
 * WhimsicalFrog User Authentication and Authorization
 * Centralized functions to eliminate duplication and improve maintainability
 * Generated: 2025-07-01 23:15:56
 */

// Bootstrap environment and Database singleton via api/config.php (not includes/database.php)
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth_cookie.php';
require_once __DIR__ . '/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/helpers/CustomerAddressSyncHelper.php';

/**
 * Ensure PHP session is started before accessing $_SESSION
 */
function ensureSessionStarted()
{
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
    // Auto-login disabled - users must explicitly log in
    // AuthSessionHelper::reconstructSessionFromCookie();
}


/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn()
{
    ensureSessionStarted();
    // Only check session - no cookie fallbacks to ensure logout works properly
    if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
        return true;
    }
    return false;
}


/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser()
{
    ensureSessionStarted();
    if (!isLoggedIn()) {
        return null;
    }

    $userData = $_SESSION['user'];

    // Handle both string and array formats (normalize to array)
    if (is_string($userData)) {
        $decoded = json_decode($userData, true);
        if (is_array($decoded)) {
            $_SESSION['user'] = $decoded; // Normalize session storage
            return $decoded;
        } else {
            // Invalid JSON, clear session
            unset($_SESSION['user']);
            return null;
        }
    } elseif (is_array($userData)) {
        return $userData;
    } else {
        // Unexpected format, clear session
        unset($_SESSION['user']);
        return null;
    }
}


/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin()
{
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    return in_array($role, [
        WF_Constants::ROLE_ADMIN,
        WF_Constants::ROLE_SUPERADMIN,
        WF_Constants::ROLE_DEVOPS,
        'administrator',
    ], true);
}


/**
 * Get user role
 * @return string
 */
function getUserRole()
{
    $user = getCurrentUser();
    return $user['role'] ?? WF_Constants::ROLE_GUEST;
}


/**
 * Get user ID
 * @return string|null
 */
function getUserId()
{
    $user = getCurrentUser();
    if ($user && isset($user['user_id'])) {
        return $user['user_id'];
    }
    // No cookie fallbacks - only use session to ensure logout works properly
    return null;
}


/**
 * Get username
 * @return string|null
 */
function getUsername()
{
    $user = getCurrentUser();
    return $user['username'] ?? null;
}


/**
 * Require authentication (redirect if not logged in)
 * @param string $redirectTo Where to redirect after login
 */
function requireAuth($redirectTo = null)
{
    // Local diagnostic bypass: allow wf_diag_bypass=1 to skip auth on localhost
    try {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'localhost') !== false && isset($_GET['wf_diag_bypass']) && $_GET['wf_diag_bypass'] === '1') {
            return;
        }
    } catch (\Throwable $____e) { /* noop */
    }

    ensureSessionStarted();
    if (!isLoggedIn()) {
        if ($redirectTo) {
            $_SESSION['redirect_after_login'] = $redirectTo;
        }
        header('Location: /login');
        exit;
    }
}


/**
 * Require admin privileges
 * @param bool $apiResponse Whether to return JSON response instead of redirect
 */
function requireAdmin($apiResponse = false)
{
    // Local diagnostic bypass: allow wf_diag_bypass=1 to skip admin enforcement on localhost
    try {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'localhost') !== false && isset($_GET['wf_diag_bypass']) && $_GET['wf_diag_bypass'] === '1') {
            return;
        }
    } catch (\Throwable $____e) { /* noop */
    }

    if (!isLoggedIn()) {
        if ($apiResponse) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        } else {
            header('Location: /login');
            exit;
        }
    }

    if (!isAdmin()) {
        if ($apiResponse) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Admin privileges required']);
            exit;
        } else {
            header('Location: /room/main');
            exit;
        }
    }
}


/**
 * Check if current user is admin (legacy compatibility)
 *
 * DEPRECATED: Token-based admin fallback is no longer supported.
 * This function now simply delegates to session-based `isAdmin()`
 * to preserve backward compatibility with existing callers.
 *
 * @param string $validToken Ignored (kept for signature compatibility)
 * @return bool
 */
function isAdminWithToken($validToken = 'whimsical_admin_2024')
{
    return isAdmin();
}


/**
 * API Authentication check with JSON response
 * @param bool $requireAdmin Whether admin privileges are required
 * @param string $adminToken Optional admin token for fallback
 */
function checkApiAuth($requireAdmin = false, $adminToken = 'whimsical_admin_2024')
{
    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        // Check for admin token fallback if admin is required
        if ($requireAdmin && $adminToken) {
            // Check POST, GET, and JSON body for admin token
            $providedToken = $_POST['admin_token'] ?? $_GET['admin_token'] ?? '';

            // Also check JSON body
            if (empty($providedToken)) {
                $jsonInput = json_decode(file_get_contents('php://input'), true);
                $providedToken = $jsonInput['admin_token'] ?? '';
            }

            if ($providedToken !== $adminToken) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }

    if ($requireAdmin && !isAdminWithToken($adminToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin privileges required']);
        exit;
    }
}


/**
 * Login user
 * @param array $userData User data from database
 */
function loginUser($userData)
{
    // Ensure session is active before using it
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $userId = (string) ($userData['id'] ?? $userData['user_id'] ?? '');
    $profile = CustomerAddressSyncHelper::mergeUserWithPrimaryAddress($userId, is_array($userData) ? $userData : []);
    $_SESSION['user'] = [
        'user_id' => $userData['id'] ?? $userData['user_id'],
        'username' => $userData['username'],
        'email' => $userData['email'],
        'role' => $userData['role'],
        'first_name' => $userData['first_name'] ?? null,
        'last_name' => $userData['last_name'] ?? null,
        'phone_number' => $userData['phone_number'] ?? null,
        'address_line_1' => ($profile['address_line_1'] ?? '') !== '' ? $profile['address_line_1'] : null,
        'address_line_2' => ($profile['address_line_2'] ?? '') !== '' ? $profile['address_line_2'] : null,
        'city' => ($profile['city'] ?? '') !== '' ? $profile['city'] : null,
        'state' => ($profile['state'] ?? '') !== '' ? $profile['state'] : null,
        'zip_code' => ($profile['zip_code'] ?? '') !== '' ? $profile['zip_code'] : null
    ];
}


/**
 * Logout user
 */
function logoutUser()
{
    // Ensure session is initialized with the correct environment (save_path, etc.)
    // before attempting to destroy it, otherwise we might destroy a session
    // in the default PHP location while the app user's session remains active.
    ensureSessionStarted();
    AuthSessionHelper::logout();
}
