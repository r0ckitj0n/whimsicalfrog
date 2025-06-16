<?php
// Debug script for get_products API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing get_products API...\n";

// Include the configuration file
require_once 'api/config.php';

echo "Config loaded successfully\n";

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Database connection successful\n";
    
    // Test basic query
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM products');
    $result = $stmt->fetch();
    echo "Products table has " . $result['count'] . " records\n";
    
    // Test the specific query from get_products.php
    $sql = "SELECT p.*, 
                   (SELECT pi.filename 
                    FROM product_images pi 
                    WHERE pi.product_id = p.id AND pi.is_primary = 1 
                    LIMIT 1) as primary_image
            FROM products p 
            LIMIT 3";
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
    
    echo "Sample products:\n";
    foreach ($products as $product) {
        echo "ID: " . $product['id'] . ", Name: " . $product['name'] . ", Primary Image: " . ($product['primary_image'] ?? 'none') . "\n";
    }
    
    // Test with specific IDs (simulating POST request)
    $productIds = array_column($products, 'id');
    if (!empty($productIds)) {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        
        $sql = "SELECT p.*, 
                       (SELECT pi.filename 
                        FROM product_images pi 
                        WHERE pi.product_id = p.id AND pi.is_primary = 1 
                        LIMIT 1) as primary_image
                FROM products p 
                WHERE p.id IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($productIds);
        $specificProducts = $stmt->fetchAll();
        
        echo "Specific products query successful, found " . count($specificProducts) . " products\n";
    }
    
    echo "All tests passed!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
?> 