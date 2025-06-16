<?php
// Simple fix for the specific image path issue
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Fixing specific image path issue...\n";
    
    // Fix the specific TS002 product that's causing the 404
    $stmt = $pdo->prepare('UPDATE products SET image = ? WHERE id = ?');
    $result = $stmt->execute(['images/products/TS002A.webp', 'TS002']);
    
    if ($result) {
        echo "Updated TS002 image path to: images/products/TS002A.webp\n";
    } else {
        echo "Failed to update TS002\n";
    }
    
    // Check current products
    $stmt = $pdo->query('SELECT id, name, image FROM products ORDER BY id');
    $products = $stmt->fetchAll();
    
    echo "\nCurrent products:\n";
    foreach ($products as $product) {
        echo $product['id'] . ": " . $product['name'] . " -> " . $product['image'] . "\n";
    }
    
    echo "\nDone!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 