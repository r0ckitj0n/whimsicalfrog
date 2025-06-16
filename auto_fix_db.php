<?php
// Auto-fix database on access
require_once 'api/config.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Auto-fixing database image paths...\n\n";
    
    // Get current state
    $stmt = $pdo->query('SELECT id, name, image FROM products WHERE id = "TS002"');
    $product = $stmt->fetch();
    
    if ($product) {
        echo "Current TS002 data:\n";
        echo "ID: " . $product['id'] . "\n";
        echo "Name: " . $product['name'] . "\n";
        echo "Image: " . $product['image'] . "\n\n";
        
        if ($product['image'] !== 'images/products/TS002A.webp') {
            // Fix the image path
            $stmt = $pdo->prepare('UPDATE products SET image = ? WHERE id = ?');
            $result = $stmt->execute(['images/products/TS002A.webp', 'TS002']);
            
            if ($result) {
                echo "✅ FIXED: Updated TS002 image path to: images/products/TS002A.webp\n";
            } else {
                echo "❌ FAILED: Could not update TS002\n";
            }
        } else {
            echo "✅ OK: TS002 already has correct image path\n";
        }
    } else {
        echo "❌ ERROR: TS002 product not found\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "ALL PRODUCTS:\n";
    echo str_repeat("=", 50) . "\n";
    
    // Show all products
    $stmt = $pdo->query('SELECT id, name, image FROM products ORDER BY id');
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        echo $product['id'] . ": " . $product['name'] . "\n";
        echo "   Image: " . $product['image'] . "\n\n";
    }
    
    echo "Database fix completed!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?> 