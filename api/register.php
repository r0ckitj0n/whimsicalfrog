<?php
// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON data from request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['username']) || !isset($data['password']) || !isset($data['email'])) {
    echo json_encode(['error' => 'Username, password, and email are required']);
    exit();
}

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit();
}

// Sanitize inputs
$username = $data['username'];
$password = $data['password'];
$email = $data['email'];
$role = isset($data['role']) ? $data['role'] : 'Customer';
$roleType = isset($data['roleType']) ? $data['roleType'] : 'Customer';
$firstName = isset($data['firstName']) ? $data['firstName'] : '';
$lastName = isset($data['lastName']) ? $data['lastName'] : '';
$phoneNumber = isset($data['phoneNumber']) ? $data['phoneNumber'] : '';
$addressLine1 = isset($data['addressLine1']) ? $data['addressLine1'] : '';
$addressLine2 = isset($data['addressLine2']) ? $data['addressLine2'] : '';
$city = isset($data['city']) ? $data['city'] : '';
$state = isset($data['state']) ? $data['state'] : '';
$zipCode = isset($data['zipCode']) ? $data['zipCode'] : '';

try {
    // Check if username already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['error' => 'Username already exists']);
        exit();
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['error' => 'Email already exists']);
        exit();
    }
    
    // Generate a unique user ID
    $userId = 'U' . substr(uniqid(), -8);
    
    // Insert new user
    $stmt = $pdo->prepare('INSERT INTO users (id, username, password, email, role, roleType, first_name, last_name, phone_number, address_line1, address_line2, city, state, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $username,
        $password,
        $email,
        $role,
        $roleType,
        $firstName,
        $lastName,
        $phoneNumber,
        $addressLine1,
        $addressLine2,
        $city,
        $state,
        $zipCode
    ]);
    
    // Return success response with user data
    echo json_encode([
        'success' => true,
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'roleType' => $roleType,
        'firstName' => $firstName,
        'lastName' => $lastName
    ]);
    
} catch (PDOException $e) {
    // Database error
    echo json_encode(['error' => 'Registration failed', 'details' => $e->getMessage()]);
}
?>