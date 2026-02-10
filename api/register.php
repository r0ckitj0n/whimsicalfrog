<?php

// Include centralized systems
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers/ProfileCompletionHelper.php';
require_once __DIR__ . '/../includes/helpers/AddressValidationHelper.php';

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
    $required = ['username', 'password', 'email', 'first_name', 'last_name', 'address_line_1', 'city', 'state', 'zip_code'];
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

    if ($username === '' || trim($password) === '') {
        Response::error('username and password are required');
    }

    // Optional fields with defaults
    $role = $data['role'] ?? 'Customer';
    $first_name = trim($data['first_name'] ?? '');
    $last_name = trim($data['last_name'] ?? '');
    $phone_number = trim($data['phone_number'] ?? '');
    $address_line_1 = trim($data['address_line_1'] ?? '');
    $address_line_2 = trim($data['address_line_2'] ?? '');
    $city = trim($data['city'] ?? '');
    $state = trim($data['state'] ?? '');
    $zip_code = trim($data['zip_code'] ?? '');

    $requiredProfileFields = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'address_line_1' => $address_line_1,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zip_code,
    ];
    foreach ($requiredProfileFields as $field => $value) {
        if ($value === '') {
            Response::error("$field is required");
        }
    }

    // Check if username already exists
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM users WHERE username = ?', [$username]);
    if (($row['c'] ?? 0) > 0) {
        Response::error('Username already exists');
    }

    // Check if email already exists
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM users WHERE email = ?', [$email]);
    if (($row['c'] ?? 0) > 0) {
        Response::error('Email already exists');
    }

    // Generate compact customer ID format: [MonthDay][SequenceNum]
    // Example: A15001 (6 characters total)

    // Get compact.created_at format: Month letter (A-L) + Day (01-31)
    $monthLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
    $monthLetter = $monthLetters[date('n') - 1]; // n = 1-12, array is 0-11
    $dayOfMonth = date('d');
    $compactDate = $monthLetter . $dayOfMonth;

    // Get the next sequence number (total customer count + 1)
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM users');
    $totalCount = (int) ($row['c'] ?? 0);
    $sequenceNum = str_pad($totalCount + 1, 3, '0', STR_PAD_LEFT);

    // Create customer ID: A15001
    $user_id = $compactDate . $sequenceNum;

    // Hash password using secure method
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    Database::execute('INSERT INTO users (id, username, password, email, role, first_name, last_name, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $user_id,
        $username,
        $hashedPassword,
        $email,
        $role,
        $first_name,
        $last_name,
        $phone_number
    ]);
    $normalizedAddress = AddressValidationHelper::normalize([
        'address_name' => 'Primary',
        'address_line_1' => $address_line_1,
        'address_line_2' => $address_line_2,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zip_code,
        'is_default' => 1,
    ]);
    AddressValidationHelper::assertRequired($normalizedAddress);
    AddressValidationHelper::assertOwnerExists('customer', (string) $user_id);

    Database::execute(
        'INSERT INTO addresses (owner_type, owner_id, address_name, address_line_1, address_line_2, city, state, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
        [
            'customer',
            $user_id,
            (string) $normalizedAddress['address_name'],
            (string) $normalizedAddress['address_line_1'],
            (string) $normalizedAddress['address_line_2'],
            (string) $normalizedAddress['city'],
            (string) $normalizedAddress['state'],
            (string) $normalizedAddress['zip_code']
        ]
    );

    $profileSeed = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'address_line_1' => $address_line_1,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zip_code,
    ];
    $missingProfileFields = wf_profile_missing_fields($profileSeed);
    $profileCompletionRequired = count($missingProfileFields) > 0;

    // Set default user meta
    require_once dirname(__DIR__) . '/includes/user_meta.php';
    set_user_meta_many($user_id, [
        'marketing_opt_in' => '1',
        'status' => 'active',
        'vip' => '0',
        'preferred_contact' => 'email',
        'preferred_language' => 'English',
        'profile_completion_required' => $profileCompletionRequired ? '1' : '0'
    ]);

    // Log successful registration
    Logger::info("User registered successfully", [
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'role' => $role
    ]);

    // Return success response using centralized method
    Response::success([
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone_number' => $phone_number,
        'profile_missing_fields' => $missingProfileFields,
        'profile_completion_required' => $profileCompletionRequired
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
