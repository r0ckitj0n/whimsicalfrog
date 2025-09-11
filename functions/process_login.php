<?php

/**
 * WhimsicalFrog Login Processing Endpoint
 *
 * Handles user authentication using centralized auth system
 * with proper password hashing and session management.
 */

// Unify session bootstrap with centralized manager so save_path and cookie are identical to readers
require_once __DIR__ . '/../includes/session.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) {
        $baseDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
    }
    $domain = '.' . $baseDomain;
    session_init([
        'name'    => 'PHPSESSID',
        'lifetime'=> 0,
        'path'    => '/',
        'domain'  => $domain,
        'secure'  => $isHttps,
        'httponly'=> true,
        'samesite'=> 'None',
    ]);
}

// Start output buffering to capture any unexpected output from includes (optional)
if (!ob_get_level()) { ob_start(); }


// Include the configuration and auth files
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database_logger.php';
require_once __DIR__ . '/../includes/auth_cookie.php';

// Set CORS headers only when Origin is present (dev cross-origin). For same-origin, omit CORS to avoid cookie issues.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    // Remove any wildcard CORS header potentially set upstream to avoid credentials rejection
    if (function_exists('header_remove')) {
        @header_remove('Access-Control-Allow-Origin');
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    while (ob_get_level() > 0) { ob_end_clean(); }
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }

    $username = $data['username'];
    $password = $data['password'];

    // Create database connection using centralized Database class
    $pdo = Database::getInstance();

    // Query for user (only get username, not password in WHERE clause)
    $user = Database::queryOne('SELECT * FROM users WHERE username = ?', [$username]);

    // Verify user exists and password is correct using password_verify
    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, log the user in
        $userData = [
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        $_SESSION['user'] = $userData;
        $_SESSION['auth_time'] = time();

        // Check for redirect after login
        $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']); // Clear it

        // Use centralized login function
        loginUser($user);

        // Log successful login
        DatabaseLogger::logUserActivity(
            'login',
            'User logged in successfully',
            'user',
            $user['id'],
            $user['id']
        );

        // Regenerate session id to prevent fixation and ensure new cookie is sent
        try { @session_regenerate_id(true); } catch (\Throwable $e) {}
        // Explicitly set canonical cookie for apex+www to avoid host-only duplicates
        try {
            $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
            if (strpos($host, ':') !== false) { $host = explode(':', $host)[0]; }
            $parts = explode('.', $host);
            $baseDomain = $host;
            if (count($parts) >= 2) {
                $baseDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            }
            $cookieDomain = '.' . $baseDomain;
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
            // Clear any host-only variant
            @setcookie(session_name(), '', [ 'expires' => time()-3600, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'None' ]);
            // Set canonical
            @setcookie(session_name(), session_id(), [ 'expires' => 0, 'path' => '/', 'domain' => $cookieDomain, 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'None' ]);
        } catch (\Throwable $e) {}
        // Also set a signed WF_AUTH cookie to reconstruct auth if PHP session engine flakes
        try {
            wf_auth_set_cookie($user['id'], $cookieDomain, $isHttps);
        } catch (\Throwable $e) {}
        // Ensure session is flushed to storage and cookie is sent
        try { @session_write_close(); } catch (\Throwable $e) {}

        // User authenticated successfully
        echo json_encode([
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'roleType' => $user['role'], // For backward compatibility
            'firstName' => $user['firstName'] ?? null,
            'lastName' => $user['lastName'] ?? null,
            'redirectUrl' => $redirectUrl // Include redirect URL in response
        ]);
        exit;
    } else {
        // Log failed login attempt
        if (class_exists('DatabaseLogger')) {
            DatabaseLogger::logUserActivity(
                'login_failed',
                "Failed login attempt for username: $username",
                'user',
                null,
                null
            );
        }

        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }

} catch (PDOException $e) {
    // Handle database errors
    error_log("Database error in login: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => 'Please try again later'
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => 'Please try again later'
    ]);
    exit;
}
