<?php
// Fix image paths in database to match actual files
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Fixing image paths in database...\n";
    
    // Get all products
    $stmt = $pdo->query('SELECT id, name, image FROM products');
    $products = $stmt->fetchAll();
    
    echo "Found " . count($products) . " products\n";
    
    // Map of correct image paths based on product IDs
    $imageMap = [
        'TS001' => 'images/products/TS001A.png',
        'TS002' => 'images/products/TS002A.webp',
        'TU001' => 'images/products/TU001A.png',
        'TU002' => 'images/products/TU002A.png',
        'AW001' => 'images/products/AW001A.png',
        'SB001' => 'images/products/placeholder.png', // No specific sublimation image found
        'SB002' => 'images/products/placeholder.png',
        'WW001' => 'images/products/placeholder.png', // No specific window wrap image found
        'WW002' => 'images/products/placeholder.png',
        'GN001' => 'images/products/GN001A.png',
        'MG001' => 'images/products/MG001A.png'
    ];
    
    $updateStmt = $pdo->prepare('UPDATE products SET image = ? WHERE id = ?');
    $updated = 0;
    
    foreach ($products as $product) {
        $productId = $product['id'];
        $currentImage = $product['image'];
        
        if (isset($imageMap[$productId])) {
            $correctImage = $imageMap[$productId];
            
            if ($currentImage !== $correctImage) {
                $updateStmt->execute([$correctImage, $productId]);
                echo "Updated $productId: '$currentImage' -> '$correctImage'\n";
                $updated++;
            } else {
                echo "OK $productId: '$currentImage'\n";
            }
        } else {
            echo "No mapping for $productId (current: '$currentImage')\n";
        }
    }
    
    echo "\nUpdated $updated products\n";
    echo "Done!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 