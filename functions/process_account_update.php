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
    if (!isset($data['userId']) || !isset($data['email']) || !isset($data['currentPassword'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID, email, and current password are required']);
        exit;
    }

    // Extract data
    $userId = $data['userId'];
    $email = $data['email'];
    $firstName = $data['firstName'] ?? '';
    $lastName = $data['lastName'] ?? '';
    $currentPassword = $data['currentPassword'];
    $newPassword = $data['newPassword'] ?? '';

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Verify the user exists and the current password is correct
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND password = ?');
    $stmt->execute([$userId, $currentPassword]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid user ID or current password']);
        exit;
    }

    // Check if email is already used by another user
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Email already in use by another account']);
            exit;
        }
    }

    // Prepare update query based on whether a new password is provided
    if (!empty($newPassword)) {
        $stmt = $pdo->prepare('UPDATE users SET email = ?, first_name = ?, last_name = ?, password = ? WHERE id = ?');
        $result = $stmt->execute([$email, $firstName, $lastName, $newPassword, $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE id = ?');
        $result = $stmt->execute([$email, $firstName, $lastName, $userId]);
    }

    if ($result) {
        // Get updated user data
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch();

        // Return success response with updated user data
        echo json_encode([
            'success' => true,
            'message' => 'Account updated successfully',
            'userData' => [
                'userId' => $updatedUser['id'],
                'username' => $updatedUser['username'],
                'email' => $updatedUser['email'],
                'firstName' => $updatedUser['first_name'],
                'lastName' => $updatedUser['last_name'],
                'role' => $updatedUser['role']
            ]
        ]);
    } else {
        throw new Exception('Failed to update account');
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
