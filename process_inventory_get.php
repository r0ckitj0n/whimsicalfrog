<?php
// Include the configuration file
require_once 'api/config.php';

// Set appropriate headers
header('Content-Type: application/json');

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if category filter is provided
    $category = isset($_GET['category']) && $_GET['category'] !== 'All' ? $_GET['category'] : null;
    
    // Prepare SQL query based on category filter
    if ($category) {
        $stmt = $pdo->prepare('SELECT * FROM inventory WHERE category = ? ORDER BY name');
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->query('SELECT * FROM inventory ORDER BY name');
    }
    
    // Fetch all inventory items
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return inventory items as JSON
    echo json_encode($inventoryItems);
    
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
?>
