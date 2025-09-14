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

/**
 * Ensure PHP session is started before accessing $_SESSION
 */
function ensureSessionStarted()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
        if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
        $parts = explode('.', $host);
        $baseDomain = $host;
        if (count($parts) >= 2) { $baseDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1]; }
        $cookieDomain = '.' . $baseDomain;
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        );
        session_init([
            'name' => 'PHPSESSID',
            'lifetime' => 0,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    }
    // Reconstruct session from WF_AUTH cookie if needed (stateless fallback)
    try {
        if (empty($_SESSION['user'])) {
            $cookieVal = $_COOKIE[wf_auth_cookie_name()] ?? null;
            $parsed = wf_auth_parse_cookie($cookieVal ?? '');
            if (is_array($parsed) && !empty($parsed['userId'])) {
                $uid = $parsed['userId'];
                // Fetch user
                $row = null;
                try { $row = Database::queryOne('SELECT id, username, email, role, firstName, lastName FROM users WHERE id = ?', [$uid]); } catch (\Throwable $e) { $row = null; }
                if ($row && !empty($row['id'])) {
                    $_SESSION['user'] = [
                        'userId' => $row['id'],
                        'username' => $row['username'] ?? null,
                        'email' => $row['email'] ?? null,
                        'role' => $row['role'] ?? 'user',
                        'firstName' => $row['firstName'] ?? null,
                        'lastName' => $row['lastName'] ?? null,
                    ];
                    // Refresh cookie TTL
                    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
                    if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
                    $p = explode('.', $host); $bd = $host; if (count($p) >= 2) { $bd = $p[count($p)-2] . '.' . $p[count($p)-1]; }
                    $dom = '.' . $bd; $sec = (
                        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
                        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
                        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
                    );
                    wf_auth_set_cookie($row['id'], $dom, $sec);
                } else {
                    // Minimal reconstruction when DB is unavailable: set only userId
                    $_SESSION['user'] = [ 'userId' => $uid ];
                }
            }
        }
    } catch (\Throwable $e) { /* non-fatal */ }
}


/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn()
{
    ensureSessionStarted();
    if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
        return true;
    }
    // Fallback: accept signed WF_AUTH cookie as authenticated indicator
    try {
        $cookieVal = $_COOKIE[wf_auth_cookie_name()] ?? null;
        $parsed = wf_auth_parse_cookie($cookieVal ?? '');
        if (is_array($parsed) && !empty($parsed['userId'])) {
            return true;
        }
        // Last-resort: trust minimal client hint WF_AUTH_V for UI-only state
        $vis = $_COOKIE[wf_auth_client_cookie_name()] ?? null;
        if ($vis) {
            $raw = base64_decode($vis, true);
            $obj = $raw ? json_decode($raw, true) : null;
            if (is_array($obj) && !empty($obj['uid'])) { return true; }
        }
    } catch (\Throwable $e) { /* noop */ }
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

    $role = strtolower($user['role'] ?? '');
    return $role === 'admin';
}


/**
 * Get user role
 * @return string
 */
function getUserRole()
{
    $user = getCurrentUser();
    return $user['role'] ?? 'guest';
}


/**
 * Get user ID
 * @return string|null
 */
function getUserId()
{
    $user = getCurrentUser();
    if ($user && isset($user['userId'])) return $user['userId'];
    // Fallback to WF_AUTH if session user missing
    try {
        $cookieVal = $_COOKIE[wf_auth_cookie_name()] ?? null;
        $parsed = wf_auth_parse_cookie($cookieVal ?? '');
        if (is_array($parsed) && !empty($parsed['userId'])) {
            return $parsed['userId'];
        }
        // Last-resort: extract uid from WF_AUTH_V
        $vis = $_COOKIE[wf_auth_client_cookie_name()] ?? null;
        if ($vis) {
            $raw = base64_decode($vis, true);
            $obj = $raw ? json_decode($raw, true) : null;
            if (is_array($obj) && !empty($obj['uid'])) { return $obj['uid']; }
        }
    } catch (\Throwable $e) { /* noop */ }
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
 * Check admin with fallback token (for API endpoints)
 * @param string $validToken Optional admin token for fallback auth
 * @return bool
 */
function isAdminWithToken($validToken = 'whimsical_admin_2024')
{
    // First check session-based admin
    if (isAdmin()) {
        return true;
    }

    // Check for admin token fallback in POST/GET
    $providedToken = $_POST['admin_token'] ?? $_GET['admin_token'] ?? '';

    // Also check JSON body
    if (empty($providedToken)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $providedToken = $jsonInput['admin_token'] ?? '';
    }

    return $providedToken === $validToken;
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
    $_SESSION['user'] = [
        'userId' => $userData['id'] ?? $userData['userId'],
        'username' => $userData['username'],
        'email' => $userData['email'],
        'role' => $userData['role'],
        'firstName' => $userData['firstName'] ?? null,
        'lastName' => $userData['lastName'] ?? null
    ];
}


/**
 * Logout user
 */
function logoutUser()
{
    // Ensure session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    // Derive cookie context (proxy-aware) once
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
    $p = explode('.', $host); $bd = $host; if (count($p) >= 2) { $bd = $p[count($p)-2] . '.' . $p[count($p)-1]; }
    $dom = '.' . $bd;
    $sec = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );

    // Clear session array and destroy; also unlink the session file explicitly to avoid stale reuse
    $sid = session_id();
    $savePath = ini_get('session.save_path');
    $_SESSION = [];
    try { @session_unset(); } catch (\Throwable $e) {}
    try { @session_destroy(); } catch (\Throwable $e) {}
    // Best-effort: remove the backing session file
    try {
        if (!empty($sid) && !empty($savePath)) {
            $base = rtrim((string)$savePath, '/');
            $sessFile = $base . '/sess_' . $sid;
            if (is_file($sessFile)) { @unlink($sessFile); }
        }
    } catch (\Throwable $e) { /* noop */ }

    // Clear PHPSESSID both domain-scoped and host-only
    try {
        $sameSite = $sec ? 'None' : 'Lax';
        @setcookie(session_name(), '', [ 'expires' => time() - 3600, 'path' => '/', 'domain' => $dom, 'secure' => $sec, 'httponly' => true, 'samesite' => $sameSite ]);
        @setcookie(session_name(), '', [ 'expires' => time() - 3600, 'path' => '/', 'secure' => $sec, 'httponly' => true, 'samesite' => $sameSite ]);
    } catch (\Throwable $e) { /* noop */ }

    // Clear WF_AUTH (HttpOnly) and WF_AUTH_V (non-HttpOnly), both domain and host-only
    try {
        require_once __DIR__ . '/auth_cookie.php';
        // Domain-scoped clears
        wf_auth_clear_cookie($dom, $sec);
        wf_auth_clear_client_hint($dom, $sec);
        // Host-only clears
        $sameSite = $sec ? 'None' : 'Lax';
        @setcookie(wf_auth_cookie_name(), '', [ 'expires' => time() - 3600, 'path' => '/', 'secure' => $sec, 'httponly' => true, 'samesite' => $sameSite ]);
        @setcookie(wf_auth_client_cookie_name(), '', [ 'expires' => time() - 3600, 'path' => '/', 'secure' => $sec, 'httponly' => false, 'samesite' => $sameSite ]);
    } catch (\Throwable $e) { /* noop */ }

    // Finalize: rotate session id to avoid resurrecting old file
    try { @session_regenerate_id(true); } catch (\Throwable $e) {}
    try { @session_write_close(); } catch (\Throwable $e) {}
}
