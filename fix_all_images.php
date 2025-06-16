<?php
// Fix all product image paths to match actual files
require_once 'api/config.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Fixing ALL product image paths...\n\n";
    
    // Map of correct image paths based on what actually exists
    $correctPaths = [
        'AW001' => 'images/products/AW001A.png',
        'GN001' => 'images/products/GN001A.png', 
        'MG001' => 'images/products/MG001A.png',
        'TS001' => 'images/products/TS001A.png',
        'TS002' => 'images/products/TS002A.webp', // Already fixed
        'TU001' => 'images/products/TU001A.png',
        'TU002' => 'images/products/TU002A.png'
    ];
    
    $updateStmt = $pdo->prepare('UPDATE products SET image = ? WHERE id = ?');
    $updated = 0;
    
    foreach ($correctPaths as $productId => $correctPath) {
        // Get current path
        $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $current = $stmt->fetch();
        
        if ($current) {
            $currentPath = $current['image'];
            
            if ($currentPath !== $correctPath) {
                $result = $updateStmt->execute([$correctPath, $productId]);
                if ($result) {
                    echo "✅ FIXED $productId: '$currentPath' -> '$correctPath'\n";
                    $updated++;
                } else {
                    echo "❌ FAILED $productId: Could not update\n";
                }
            } else {
                echo "✅ OK $productId: Already correct ($correctPath)\n";
            }
        } else {
            echo "❌ NOT FOUND: $productId\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "SUMMARY: Updated $updated products\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // Show final state
    echo "FINAL PRODUCT STATES:\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->query('SELECT id, name, image FROM products ORDER BY id');
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $status = isset($correctPaths[$product['id']]) && 
                 $product['image'] === $correctPaths[$product['id']] ? "✅" : "❌";
        
        echo "$status {$product['id']}: {$product['name']}\n";
        echo "   Image: {$product['image']}\n\n";
    }
    
    echo "All fixes completed!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?> 