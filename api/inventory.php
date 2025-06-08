<?php
// Set error reporting for debugging
ini_set('display_errors', 0); // Turn off display errors for production
error_reporting(E_ALL);

// Include the configuration file with correct path
require_once __DIR__ . '/config.php'; // Use absolute path to avoid path issues

// Set CORS headers - only after making sure no output has been sent
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');
}

// Handle preflight OPTIONS request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!headers_sent()) {
        http_response_code(200);
    }
    exit;
}

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Build query based on filters
    $query = "SELECT * FROM inventory"; // This will include costPrice and retailPrice fields
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
    
    // Add sorting
    $query .= " ORDER BY name ASC";
    
    // Prepare and execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output inventory data directly as an array (not wrapped in a success/debug object)
    // This matches the format expected by the JavaScript in admin_inventory.php
    echo json_encode($inventory);
    
} catch (PDOException $e) {
    // Handle database errors with detailed information
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
    // Log error for debugging
    error_log("Inventory API Database Error: " . $e->getMessage());
    exit;
} catch (Exception $e) {
    // Handle general errors
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode([
        'error' => 'General error',
        'message' => $e->getMessage()
    ]);
    // Log error for debugging
    error_log("Inventory API General Error: " . $e->getMessage());
    exit;
}
