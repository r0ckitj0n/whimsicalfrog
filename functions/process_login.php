<?php

/**
 * WhimsicalFrog Login Processing Endpoint
 *
 * Handles user authentication using centralized auth system
 * with proper password hashing and session management.
 */

// Start session first before any headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to capture any unexpected output from includes
if (!ob_get_level()) {
    ob_start();
}


// Include the configuration and auth files
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database_logger.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    // Discard any buffered output to keep response body empty for preflight
    if (ob_get_length()) { ob_end_clean(); }
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        if (ob_get_length()) { ob_clean(); }
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }

    $username = $data['username'];
    $password = $data['password'];

    // Create database connection using centralized Database class
    $pdo = Database::getInstance();

    // Query for user (only get username, not password in WHERE clause)
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

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

        // User authenticated successfully
        if (ob_get_length()) { ob_clean(); }
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
        if (ob_get_length()) { ob_clean(); }
        echo json_encode(['error' => 'Invalid username or password']);
    }

} catch (PDOException $e) {
    // Handle database errors
    error_log("Database error in login: " . $e->getMessage());
    http_response_code(500);
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => 'Please try again later'
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => 'Please try again later'
    ]);
    exit;
}
