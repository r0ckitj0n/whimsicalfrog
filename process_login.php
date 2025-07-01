<?php
/**
 * WhimsicalFrog Login Processing Endpoint
 * 
 * Handles user authentication using centralized auth system
 * with proper password hashing and session management.
 */

// Include the configuration and auth files
require_once 'api/config.php';
require_once 'includes/auth.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    // Verify user exists and password is correct using password_verify
    if ($user && password_verify($password, $user['password'])) {
        
        // Check for redirect after login
        $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']); // Clear it
        
        // Use centralized login function
        loginUser($user);
        
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
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
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
?>
