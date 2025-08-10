<?php

// Include centralized systems
require_once 'api/config.php';
require_once 'includes/functions.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json(null, 200);
}

// Validate HTTP method using centralized function
Response::validateMethod('POST');

try {
    // Get and validate input using centralized method
    $data = Response::getJsonInput();

    // Validate required fields
    $required = ['username', 'email', 'password'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            Response::error("$field is required");
        }
    }

    // Get database connection using centralized system
    $pdo = Database::getInstance();

    // Sanitize inputs
    $username = trim($data['username']);
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    $password = $data['password'];

    if (!$email) {
        Response::error('Invalid email format');
    }

    // Optional fields
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
        Response::error('Username already exists', null, 409);
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        Response::error('Email already exists', null, 409);
    }

    // Generate customer ID using proper specification: [MonthLetter][Day][SequenceNum]
    $monthLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
    $monthLetter = $monthLetters[date('n') - 1];
    $dayOfMonth = date('d');
    $compactDate = $monthLetter . $dayOfMonth;

    // Get the next sequence number
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "Customer"');
    $stmt->execute();
    $customerCount = $stmt->fetchColumn();
    $sequenceNum = str_pad($customerCount + 1, 3, '0', STR_PAD_LEFT);

    // Create customer ID
    $userId = $compactDate . $sequenceNum;

    // Hash password using secure method
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user with all fields
    $stmt = $pdo->prepare("INSERT INTO users (id, username, email, password, firstName, lastName, phoneNumber, addressLine1, addressLine2, city, state, zipCode, role, roleType) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Customer', 'Customer')");
    $stmt->execute([
        $userId,
        $username,
        $email,
        $hashedPassword,
        $firstName,
        $lastName,
        $phoneNumber,
        $addressLine1,
        $addressLine2,
        $city,
        $state,
        $zipCode
    ]);

    // Log successful registration using centralized logging
    Logger::info("User registered successfully", [
        'userId' => $userId,
        'username' => $username,
        'email' => $email
    ]);

    // Start session and log user in automatically
    if (session_status() == PHP_SESSION_NONE) {
        
    }

    $_SESSION['user'] = [
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'role' => 'Customer',
        'firstName' => $firstName,
        'lastName' => $lastName
    ];

    // Return success response using centralized method
    Response::success([
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'role' => 'Customer',
        'firstName' => $firstName,
        'lastName' => $lastName,
        'autoLogin' => true
    ], 'Registration successful');

} catch (PDOException $e) {
    // Log database error using centralized logging
    Logger::databaseError($e, 'User registration failed');
    Response::serverError('Registration failed');

} catch (Exception $e) {
    // Log general error using centralized logging
    Logger::error('Registration error: ' . $e->getMessage());
    Response::serverError('Registration failed');
}
