<?php
// Include centralized systems
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json(null, 200);
}

// Validate HTTP method
Response::validateMethod('POST');

try {
    // Get and validate input
    $data = Response::getJsonInput();
    
    // Validate required fields
    $required = ['username', 'password', 'email'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            Response::error("$field is required");
        }
    }
    
    // Get database connection using centralized system
    $pdo = Database::getInstance();
    
    // Sanitize inputs using centralized function
    $username = trim($data['username']);
    $password = $data['password'];
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        Response::error('Invalid email format');
    }
    
    // Optional fields with defaults
    $role = $data['role'] ?? 'Customer';
    $roleType = $data['roleType'] ?? 'Customer';
    $firstName = trim($data['firstName'] ?? '');
    $lastName = trim($data['lastName'] ?? '');
    $phoneNumber = trim($data['phoneNumber'] ?? '');
    $addressLine1 = trim($data['addressLine1'] ?? '');
    $addressLine2 = trim($data['addressLine2'] ?? '');
    $city = trim($data['city'] ?? '');
    $state = trim($data['state'] ?? '');
    $zipCode = trim($data['zipCode'] ?? '');
    
    // Check if username already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        Response::error('Username already exists');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        Response::error('Email already exists');
    }
    
    // Generate compact customer ID format: [MonthDay][SequenceNum]
    // Example: A15001 (6 characters total)
    
    // Get compact date format: Month letter (A-L) + Day (01-31)
    $monthLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
    $monthLetter = $monthLetters[date('n') - 1]; // n = 1-12, array is 0-11
    $dayOfMonth = date('d');
    $compactDate = $monthLetter . $dayOfMonth;
    
    // Get the next sequence number (total customer count + 1)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    $totalCount = $stmt->fetchColumn();
    $sequenceNum = str_pad($totalCount + 1, 3, '0', STR_PAD_LEFT);
    
    // Create customer ID: A15001
    $userId = $compactDate . $sequenceNum;
    
    // Hash password using secure method
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $pdo->prepare('INSERT INTO users (id, username, password, email, role, roleType, firstName, lastName, phoneNumber, addressLine1, addressLine2, city, state, zipCode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $username,
        $hashedPassword,
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
    
    // Log successful registration
    Logger::info("User registered successfully", [
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'role' => $role
    ]);
    
    // Return success response using centralized method
    Response::success([
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'roleType' => $roleType,
        'firstName' => $firstName,
        'lastName' => $lastName
    ], 'User registered successfully');
    
} catch (PDOException $e) {
    // Log database error using centralized logging
    Logger::databaseError($e, 'User registration failed', [
        'username' => $username ?? 'unknown',
        'email' => $email ?? 'unknown'
    ]);
    Response::serverError('Registration failed');
    
} catch (Exception $e) {
    // Log general error using centralized logging
    Logger::error('Registration error: ' . $e->getMessage(), [
        'username' => $username ?? 'unknown',
        'email' => $email ?? 'unknown'
    ]);
    Response::serverError('Registration failed');
}
?>