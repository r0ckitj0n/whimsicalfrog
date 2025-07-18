<?php

// Include the configuration file
require_once 'api/config.php';

// Set appropriate headers
header('Content-Type: application/json');

try {
    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if specific ID is provided
    $userId = isset($_GET['id']) ? $_GET['id'] : null;

    if ($userId) {
        // Fetch specific customer
        $stmt = $pdo->prepare('SELECT id, username, email, role, roleType, firstName, lastName, phoneNumber, addressLine1, addressLine2, city, state, zipCode FROM users WHERE id = ? AND (role = "Customer" OR roleType = "Customer")');
        $stmt->execute([$userId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            http_response_code(404);
            echo json_encode(['error' => 'Customer not found']);
            exit;
        }

        // Return customer data as JSON
        echo json_encode($customer);
    } else {
        // Fetch all customers
        $stmt = $pdo->query('SELECT id, username, email, role, roleType, firstName, lastName, phoneNumber, addressLine1, addressLine2, city, state, zipCode FROM users WHERE role = "Customer" OR roleType = "Customer" ORDER BY lastName, firstName');
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return customers as JSON
        echo json_encode($customers);
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
