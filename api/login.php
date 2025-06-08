<?php
// Include the configuration file
require_once 'config.php';

// Set API headers
setApiHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON data from request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['error' => 'Username and password are required']);
    exit();
}

// Database connection
try {
    $pdo = getConnection();
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit();
}

// Sanitize inputs
$username = $data['username'];
$password = $data['password'];

try {
    // Query to find user
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists and password matches
    if ($user && $user['password'] === $password) {
        // Return user data in the expected format
        echo json_encode([
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'roleType' => $user['roleType'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name']
        ]);
    } else {
        // Invalid credentials
        echo json_encode(['error' => 'Invalid username or password']);
    }
} catch (PDOException $e) {
    // Database error
    echo json_encode(['error' => 'Authentication failed', 'details' => $e->getMessage()]);
}
?>