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
<<<<<<< HEAD
    $query = "SELECT i.*, p.productType AS category FROM inventory i LEFT JOIN products p ON p.id = i.productId";
=======
    $query = "SELECT * FROM inventory";
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
    $params = [];
    $whereConditions = [];
    
    // Add search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
<<<<<<< HEAD
        $whereConditions[] = "(i.name LIKE ? OR p.productType LIKE ? OR i.sku LIKE ? OR i.description LIKE ?)";
=======
        $whereConditions[] = "(name LIKE ? OR category LIKE ? OR sku LIKE ? OR description LIKE ?)";
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
        $params = array_merge($params, [$search, $search, $search, $search]);
    }
    
    // Add category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
<<<<<<< HEAD
        $whereConditions[] = "p.productType = ?";
=======
        $whereConditions[] = "category = ?";
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
        $params[] = $_GET['category'];
    }
    
    // Add WHERE clause if conditions exist
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Add sorting (using correct column name 'name' instead of 'itemName')
<<<<<<< HEAD
    $query .= " ORDER BY i.name ASC";
=======
    $query .= " ORDER BY name ASC";
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
    
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
?>
