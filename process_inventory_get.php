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
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Build query based on filters
    $query = "SELECT * FROM inventory";
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
    
    // Debug information
    $debugInfo = [
        'query' => $query,
        'params' => $params,
        'server' => $_SERVER['SERVER_NAME'],
        'isLocalhost' => $isLocalhost ? 'true' : 'false',
        'path' => __DIR__,
        'configPath' => realpath(__DIR__ . '/api/config.php')
    ];
    
    // Prepare and execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output inventory data
    echo json_encode([
        'success' => true,
        'data' => $inventory,
        'debug' => $debugInfo
    ]);
    
} catch (PDOException $e) {
    // Handle database errors with detailed information
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString(),
        'debug' => [
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'isLocalhost' => isset($isLocalhost) ? ($isLocalhost ? 'true' : 'false') : 'unknown',
            'path' => __DIR__,
            'configPath' => realpath(__DIR__ . '/api/config.php') ?: 'not found',
            'configExists' => file_exists(__DIR__ . '/api/config.php') ? 'true' : 'false',
            'dsn' => isset($dsn) ? preg_replace('/password=([^;]*)/', 'password=***', $dsn) : 'not set',
            'user' => $user ?? 'not set'
        ]
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'General error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
?>
