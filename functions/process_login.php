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
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );
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
    // Get POST data with robust fallbacks and safe tracing
    $raw = file_get_contents('php://input');
    $data = null;
    $parsedFrom = 'none';
    if (is_string($raw) && strlen(trim($raw)) > 0) {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) { $data = $tmp; $parsedFrom = 'json'; }
    }
    if (!$data) {
        // Fallback to form-encoded
        if (!empty($_POST) && (isset($_POST['username']) || isset($_POST['password']))) {
            $data = [ 'username' => $_POST['username'] ?? null, 'password' => $_POST['password'] ?? null ];
            $parsedFrom = 'form';
        }
    }
    if (!$data) {
        // Last resort: parse query string (debug/degraded clients)
        parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
        if (!empty($qs) && (isset($qs['username']) || isset($qs['password']))) {
            $data = [ 'username' => $qs['username'] ?? null, 'password' => $qs['password'] ?? null ];
            $parsedFrom = 'query';
        }
    }

    // Validate required fields
    if (!is_array($data) || !isset($data['username']) || !isset($data['password'])) {
        try {
            error_log('[AUTH-TRACE] {"event":"login_input_missing","ct":"' . ((string)($_SERVER['CONTENT_TYPE'] ?? '')) . '","origin":"' . ((string)($_SERVER['HTTP_ORIGIN'] ?? '')) . '","parsedFrom":"' . $parsedFrom . '"}');
        } catch (\Throwable $e) {}
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required', 'code' => 'INPUT_MISSING']);
        exit;
    }

    $username = (string)$data['username'];
    $password = (string)$data['password'];

    // Create database connection using centralized Database class
    $pdo = Database::getInstance();

    // Query for user by username OR email (email match is case-insensitive)
    $user = null;
    try {
        $user = Database::queryOne('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
        if (!$user) {
            // Attempt email match
            $user = Database::queryOne('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1', [$username]);
        }
    } catch (\Throwable $e) { $user = null; }

    // Safe trace: user lookup result (no secrets)
    try {
        $found = $user ? 'yes' : 'no';
        error_log('[AUTH-TRACE] ' . json_encode([
            'event' => 'login_user_lookup',
            'parsedFrom' => $parsedFrom,
            'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
            'host' => $_SERVER['HTTP_HOST'] ?? null,
            'ct' => $_SERVER['CONTENT_TYPE'] ?? null,
            'username_len' => strlen($username),
            'user_found' => $found,
        ]));
    } catch (\Throwable $e) { /* noop */ }

    // Verify user exists and password is correct using password_verify
    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, log the user in
        // SAFETY: ensure session is active and regenerate ID WITHOUT destroying old data to avoid host handler quirks
        try { if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } } catch (\Throwable $e) {}
        try { @session_regenerate_id(false); } catch (\Throwable $e) {}
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

        // Hydrate via centralized login function too (keeps structure consistent)
        try { loginUser($user); } catch (\Throwable $e) {}

        // Log successful login
        DatabaseLogger::logUserActivity(
            'login',
            'User logged in successfully',
            'user',
            $user['id'],
            $user['id']
        );

        // Explicitly send session cookie with current id (canonical domain)
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
            $isHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
                (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
                (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
            );
            // Clear any host-only variant
            @setcookie(session_name(), '', [ 'expires' => time()-3600, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'None' ]);
            // Set canonical (domain-scoped)
            @setcookie(session_name(), session_id(), [ 'expires' => 0, 'path' => '/', 'domain' => $cookieDomain, 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'None' ]);
            // Also emit a host-only session cookie for compatibility
            @setcookie(session_name(), session_id(), [ 'expires' => 0, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'None' ]);
        } catch (\Throwable $e) {}
        // Also set a signed WF_AUTH cookie to reconstruct auth if PHP session engine flakes
        try {
            wf_auth_set_cookie($user['id'], $cookieDomain, $isHttps);
            // And host-only duplicate for edge-case client compatibility
            [$valTmp, $expTmp] = wf_auth_make_cookie($user['id']);
            @setcookie(wf_auth_cookie_name(), $valTmp, [ 'expires' => $expTmp, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'None' ]);
            // And a client-visible hint for immediate header UI sync (non-HttpOnly)
            wf_auth_set_client_hint($user['id'], $user['role'] ?? null, $cookieDomain, $isHttps);
        } catch (\Throwable $e) {}
        // Ensure session is flushed to storage and cookie is sent
        try { @session_write_close(); } catch (\Throwable $e) {}

        // User authenticated successfully
        try {
            $dbg = [
                'event' => 'login_success',
                'sid' => session_id(),
                'userId' => $user['id'],
                'cookieDomain' => $cookieDomain ?? null,
                'https' => $isHttps ?? null,
                'set_cookies' => [ 'PHPSESSID' => true, 'WF_AUTH' => true ],
                'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
                'host' => $_SERVER['HTTP_HOST'] ?? null,
                'cookie_header_in' => isset($_SERVER['HTTP_COOKIE']) ? substr((string)$_SERVER['HTTP_COOKIE'], 0, 300) : null,
                'save_path' => ini_get('session.save_path'),
            ];
            error_log('[AUTH-TRACE] ' . json_encode($dbg));
        } catch (\Throwable $e) { /* noop */ }
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

        // Safe trace for failure reason
        try {
            error_log('[AUTH-TRACE] ' . json_encode([
                'event' => 'login_failed',
                'user_found' => (bool)$user,
                'password_verified' => ($user ? password_verify($password, $user['password']) : false),
                'host' => $_SERVER['HTTP_HOST'] ?? null,
                'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
            ]));
        } catch (\Throwable $e) { /* noop */ }

        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password', 'code' => ($user ? 'BAD_PASSWORD' : 'NO_USER')]);
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
