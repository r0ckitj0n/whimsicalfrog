<?php

// Include the configuration file
require_once 'config.php';

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
    if (!isset($data['userId']) && !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }

    // Get the user ID (support both userId and id fields for compatibility)
    $userId = $data['userId'] ?? $data['id'];

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Define allowed fields for update
    $allowedFields = [
        'username' => 'username',
        'email' => 'email',
        'firstName' => 'first_name',
        'first_name' => 'first_name',
        'lastName' => 'last_name',
        'last_name' => 'last_name',
        'phoneNumber' => 'phone_number',
        'phone_number' => 'phone_number',
        'addressLine1' => 'address_line1',
        'address_line1' => 'address_line1',
        'addressLine2' => 'address_line2',
        'address_line2' => 'address_line2',
        'city' => 'city',
        'state' => 'state',
        'zipCode' => 'zip_code',
        'zip_code' => 'zip_code',
        'role' => 'role',
        'password' => 'password'
    ];

    // Build the SQL update query dynamically
    $updateFields = [];
    $params = [];

    foreach ($data as $key => $value) {
        // Skip userId/id fields as they're used in the WHERE clause
        if ($key === 'userId' || $key === 'id') {
            continue;
        }

        // Check if the field is allowed to be updated
        if (isset($allowedFields[$key])) {
            $dbField = $allowedFields[$key];
            $updateFields[] = "$dbField = ?";
            $params[] = $value;
        }
    }

    // If no fields to update, return error
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        exit;
    }

    // Add userId to params for the WHERE clause
    $params[] = $userId;

    // Prepare and execute the update query
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        // No rows updated could mean the user doesn't exist or no changes were made
        // Check if the user exists
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $checkStmt->execute([$userId]);

        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        } else {
            // User exists but no changes were made (values were the same)
            echo json_encode(['success' => true, 'message' => 'No changes were made']);
        }
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
