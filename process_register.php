<?php
// Include the configuration file
require_once 'api/config.php';

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
    if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, email, and password are required']);
        exit;
    }
    
    $username = $data['username'];
    $email = $data['email'];
    $password = $data['password'];
    $firstName = $data['firstName'] ?? null;
    $lastName = $data['lastName'] ?? null;
    
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if username already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Username already exists']);
        exit;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Email already exists']);
        exit;
    }
    
    // Insert new user
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)');
    $result = $stmt->execute([$username, $email, $password, $firstName, $lastName, 'Customer']);
    
    if ($result) {
        // Registration successful
        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'userId' => $pdo->lastInsertId()
        ]);
    } else {
        throw new Exception('Failed to register user');
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>
