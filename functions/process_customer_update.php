<?php
// Include the configuration file (absolute path)
require_once dirname(__DIR__) . '/api/config.php';

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
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

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

    // Get password data (optional)
    $newPassword = trim($_POST['newPassword'] ?? '');
    $confirmPassword = trim($_POST['confirmPassword'] ?? '');

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

    // Validate password fields if provided
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $fieldErrors[] = 'newPassword';
        }
        if ($newPassword !== $confirmPassword) {
            $fieldErrors[] = 'confirmPassword';
        }
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
    $existingCustomer = Database::queryOne('SELECT id FROM users WHERE id = ?', [$customerId]);

    if (!$existingCustomer) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Customer not found'
        ]);
        exit;
    }

    // Check if username or email already exists for different customer
    $duplicate = Database::queryOne('SELECT id FROM users WHERE (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) AND id != ?', [$username, $email, $customerId]);

    if ($duplicate) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Username or email already exists for another customer'
        ]);
        exit;
    }

    // Prepare update query based on whether password is being changed
    if (!empty($newPassword)) {
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
                        zipCode = ?,
                        password = ?
                      WHERE id = ?";

        $affected = Database::execute($updateSql, [
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
            $newPassword,
            $customerId
        ]);
        $result = ($affected !== false);

        $successMessage = 'Customer updated successfully (password changed)';
    } else {
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

        $affected = Database::execute($updateSql, [
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
        $result = ($affected !== false);

        $successMessage = 'Customer updated successfully';
    }

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
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