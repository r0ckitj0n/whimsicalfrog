<?php

// Include the configuration file
require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    // Check if specific user ID is requested
    $userId = $_GET['id'] ?? null;

    if ($userId) {
        // Query for specific user
        $userData = Database::queryOne('SELECT * FROM users WHERE id = ?', [$userId]);

        if (!$userData) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Format single user data (normalize snake_case -> camelCase)
        $formattedUser = [
            'id' => $userData['id'] ?? null,
            'username' => $userData['username'] ?? '',
            'email' => $userData['email'] ?? '',
            'role' => $userData['role'] ?? '',
            'roleType' => $userData['role'] ?? '', // Backward compatibility
            'firstName' => $userData['first_name'] ?? ($userData['firstName'] ?? ''),
            'lastName' => $userData['last_name'] ?? ($userData['lastName'] ?? ''),
            'phoneNumber' => $userData['phone_number'] ?? ($userData['phoneNumber'] ?? ''),
            'addressLine1' => $userData['address_line1'] ?? ($userData['addressLine1'] ?? ''),
            'addressLine2' => $userData['address_line2'] ?? ($userData['addressLine2'] ?? ''),
            'city' => $userData['city'] ?? '',
            'state' => $userData['state'] ?? '',
            'zipCode' => $userData['zip_code'] ?? ($userData['zipCode'] ?? ''),
        ];

        // Return single user as JSON
        echo json_encode($formattedUser);
    } else {
        // Query for all users
        $users = Database::queryAll('SELECT * FROM users');

        // Map database fields to expected output format (normalize snake_case -> camelCase)
        $formattedUsers = array_map(function ($user) {
            return [
                'id' => $user['id'] ?? null,
                'username' => $user['username'] ?? '',
                'email' => $user['email'] ?? '',
                'role' => $user['role'] ?? '',
                'roleType' => $user['role'] ?? '',
                'firstName' => $user['first_name'] ?? ($user['firstName'] ?? ''),
                'lastName' => $user['last_name'] ?? ($user['lastName'] ?? ''),
                'phoneNumber' => $user['phone_number'] ?? ($user['phoneNumber'] ?? ''),
                'addressLine1' => $user['address_line1'] ?? ($user['addressLine1'] ?? ''),
                'addressLine2' => $user['address_line2'] ?? ($user['addressLine2'] ?? ''),
                'city' => $user['city'] ?? '',
                'state' => $user['state'] ?? '',
                'zipCode' => $user['zip_code'] ?? ($user['zipCode'] ?? ''),
            ];
        }, $users);

        // Return users as JSON
        echo json_encode($formattedUsers);
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
