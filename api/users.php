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
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Query for all users
    $stmt = $pdo->query('SELECT * FROM users');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Map database fields to expected output format
    $formattedUsers = array_map(function($user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'roleType' => $user['role'], // For backward compatibility
            'firstName' => $user['firstName'] ?? '',
            'lastName' => $user['lastName'] ?? '',
            'phoneNumber' => $user['phoneNumber'] ?? '',
            'addressLine1' => $user['addressLine1'] ?? '',
            'addressLine2' => $user['addressLine2'] ?? '',
            'city' => $user['city'] ?? '',
            'state' => $user['state'] ?? '',
            'zipCode' => $user['zipCode'] ?? ''
        ];
    }, $users);
    
    // Return users as JSON
    echo json_encode($formattedUsers);
    
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
