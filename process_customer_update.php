<?php
// Include the configuration file
require_once 'api/config.php';

// Set appropriate headers
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get form data
    $customerId = $_POST['customerId'] ?? '';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $addressLine1 = trim($_POST['addressLine1'] ?? '');
    $addressLine2 = trim($_POST['addressLine2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zipCode = trim($_POST['zipCode'] ?? '');
    
    // Validate required fields
    $fieldErrors = [];
    if (empty($customerId)) {
        $fieldErrors[] = 'customerId';
    }
    if (empty($firstName)) {
        $fieldErrors[] = 'firstName';
    }
    if (empty($lastName)) {
        $fieldErrors[] = 'lastName';
    }
    if (empty($username)) {
        $fieldErrors[] = 'username';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors[] = 'email';
    }
    if (empty($role)) {
        $fieldErrors[] = 'role';
    }
    
    if (!empty($fieldErrors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Required fields are missing or invalid',
            'field_errors' => $fieldErrors
        ]);
        exit;
    }
    
    // Check if customer exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$customerId]);
    $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingCustomer) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Customer not found'
        ]);
        exit;
    }
    
    // Check if username or email already exists for different customer
    $stmt = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
    $stmt->execute([$username, $email, $customerId]);
    $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($duplicate) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Username or email already exists for another customer'
        ]);
        exit;
    }
    
    // Update customer
    $updateSql = "UPDATE users SET 
                    firstName = ?, 
                    lastName = ?, 
                    username = ?, 
                    email = ?, 
                    role = ?, 
                    phoneNumber = ?, 
                    addressLine1 = ?, 
                    addressLine2 = ?, 
                    city = ?, 
                    state = ?, 
                    zipCode = ?
                  WHERE id = ?";
    
    $stmt = $pdo->prepare($updateSql);
    $result = $stmt->execute([
        $firstName,
        $lastName, 
        $username,
        $email,
        $role,
        $phoneNumber,
        $addressLine1,
        $addressLine2,
        $city,
        $state,
        $zipCode,
        $customerId
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully',
            'customerId' => $customerId
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update customer in database'
        ]);
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?> 