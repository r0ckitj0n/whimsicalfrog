<?php

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow access from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database configuration
require_once __DIR__ . '/api/config.php';

try {
    // Create database connection
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Build query based on filters
    $query = "SELECT * FROM items";
    $params = [];
    $whereConditions = [];

    // Add search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(name LIKE ? OR category LIKE ? OR sku LIKE ? OR description LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    // Add category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $whereConditions[] = "category = ?";
        $params[] = $_GET['category'];
    }

    // Add WHERE clause if conditions exist
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }

    // Add sorting (using correct column name 'name' instead of 'itemName')
    $query .= " ORDER BY name ASC";

    // Prepare and execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output inventory data directly as an array (not wrapped in a success/debug object)
    // This change makes it compatible with the JavaScript frontend code
    echo json_encode($inventory);

} catch (PDOException $e) {
    // Handle database errors with detailed information
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'General error',
        'message' => $e->getMessage()
    ]);
    exit;
}
