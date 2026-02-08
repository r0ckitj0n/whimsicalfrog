<?php

// Include centralized systems (absolute paths)
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/response.php';
require_once dirname(__DIR__) . '/includes/user_meta.php';

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
    $first_name = trim($data['first_name'] ?? '');
    $last_name = trim($data['last_name'] ?? '');
    $phone_number = trim($data['phone_number'] ?? '');
    $address_line_1 = trim($data['address_line_1'] ?? '');
    $address_line_2 = trim($data['address_line_2'] ?? '');
    $city = trim($data['city'] ?? '');
    $state = trim($data['state'] ?? '');
    $zip_code = trim($data['zip_code'] ?? '');

    // Check if username already exists
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM users WHERE username = ?', [$username]);
    if ((int) ($row['c'] ?? 0) > 0) {
        Response::error('Username already exists', null, 409);
    }

    // Check if email already exists
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM users WHERE email = ?', [$email]);
    if ((int) ($row['c'] ?? 0) > 0) {
        Response::error('Email already exists', null, 409);
    }

    // Generate customer ID using proper specification: [MonthLetter][Day][SequenceNum]
    $monthLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
    $monthLetter = $monthLetters[date('n') - 1];
    $dayOfMonth = date('d');
    $compactDate = $monthLetter . $dayOfMonth;

    // Get the next sequence number
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM users WHERE role = "Customer"');
    $customerCount = (int) ($row['c'] ?? 0);
    $sequenceNum = str_pad($customerCount + 1, 3, '0', STR_PAD_LEFT);

    // Create customer ID
    $user_id = $compactDate . $sequenceNum;

    // Hash password using secure method
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user with all fields
    Database::execute("INSERT INTO users (id, username, email, password, first_name, last_name, phone_number, address_line_1, address_line_2, city, state, zip_code, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Customer')", [
        $user_id,
        $username,
        $email,
        $hashedPassword,
        $first_name,
        $last_name,
        $phone_number,
        $address_line_1,
        $address_line_2,
        $city,
        $state,
        $zip_code
    ]);

    set_user_meta_many($user_id, [
        'profile_completion_required' => '1'
    ]);

    // Log successful registration using centralized logging
    Logger::info("User registered successfully", [
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email
    ]);

    // Start session and log user in automatically
    if (session_status() == PHP_SESSION_NONE) {

    }

    $_SESSION['user'] = [
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'role' => 'Customer',
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone_number' => $phone_number,
        'address_line_1' => $address_line_1,
        'profile_completion_required' => true
    ];

    // Return success response using centralized method
    Response::success([
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'role' => 'Customer',
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone_number' => $phone_number,
        'address_line_1' => $address_line_1,
        'profile_completion_required' => true,
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
