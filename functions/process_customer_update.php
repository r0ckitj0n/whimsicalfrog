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

// Debug: Log script start
error_log("process_customer_update.php started");

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

    // Debug: Log received data
    error_log("Received POST data for customer ID: " . $customerId);
    error_log("Form data: " . json_encode([
        'firstName' => $firstName,
        'lastName' => $lastName,
        'username' => $username,
        'email' => $email,
        'role' => $role
    ]));

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

    error_log("Checking customer existence - ID: " . $customerId);
    $existingCustomer = Database::queryOne('SELECT id, firstName, lastName FROM users WHERE id = ?', [$customerId]);
    error_log("Customer exists: " . ($existingCustomer ? 'YES' : 'NO'));
    if ($existingCustomer) {
        error_log("Current customer data: " . json_encode($existingCustomer));
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
        // Hash password using secure method
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

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
            $hashedPassword,
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

    // Debug: Log the update result
    error_log("Database update result - affected rows: " . $affected);
    error_log("Final result: " . ($result ? 'SUCCESS' : 'FAILED'));

    if ($result) {
        error_log("About to return success JSON");
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'customerId' => $customerId,
            'affectedRows' => $affected
        ]);
        error_log("Success JSON sent");
    } else {
        error_log("About to return error JSON");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update customer in database'
        ]);
        error_log("Error JSON sent");
    }

} catch (PDOException $e) {
    // Handle database errors
    error_log("PDOException caught: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Handle general errors
    error_log("General Exception caught: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?> 