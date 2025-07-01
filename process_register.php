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
    $phoneNumber = $data['phoneNumber'] ?? null;
    $addressLine1 = $data['addressLine1'] ?? null;
    $addressLine2 = $data['addressLine2'] ?? null;
    $city = $data['city'] ?? null;
    $state = $data['state'] ?? null;
    $zipCode = $data['zipCode'] ?? null;
    
    // Create database connection using config
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
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
    
    // Generate customer ID using the proper specification: [MonthLetter][Day][SequenceNum]
    // Example: A15001 (January 15th, customer #1)
    
    // Get compact date format: Month letter (A-L) + Day (01-31)
    $monthLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
    $monthLetter = $monthLetters[date('n') - 1]; // n = 1-12, array is 0-11
    $dayOfMonth = date('d');
    $compactDate = $monthLetter . $dayOfMonth;
    
    // Get the next sequence number (total customer count + 1)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "Customer"');
    $stmt->execute();
    $customerCount = $stmt->fetchColumn();
    $sequenceNum = str_pad($customerCount + 1, 3, '0', STR_PAD_LEFT);
    
    // Create customer ID
    $userId = $compactDate . $sequenceNum;
    
    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, firstName, lastName, phoneNumber, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
    if ($stmt->execute([$username, $email, $password, $firstName, $lastName, $phoneNumber])) {
        $userId = $pdo->lastInsertId();
        
        // Log successful registration
        DatabaseLogger::logUserActivity(
            'registration',
            'New user registered: ' . $username,
            'user',
            $userId,
            $userId
        );
        
        // Registration successful - now automatically log the user in
        
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store user data in session (same format as login process)
        $_SESSION['user'] = [
            'userId' => $userId,
            'username' => $username,
            'email' => $email,
            'role' => 'Customer',
            'firstName' => $firstName,
            'lastName' => $lastName
        ];
        
        // Return success response with user data for frontend
        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'userId' => $userId,
            'autoLogin' => true,
            'userData' => [
                'userId' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => 'Customer',
                'firstName' => $firstName,
                'lastName' => $lastName
            ]
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
