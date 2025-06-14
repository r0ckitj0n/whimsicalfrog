<?php
require_once __DIR__ . '/api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "<h2>Fixing Missing TS001B.webp Image</h2>";
    
    // Check if the file exists
    $imagePath = 'images/products/TS001B.webp';
    $fullPath = __DIR__ . '/' . $imagePath;
    
    echo "<p>Checking file: $fullPath</p>";
    if (file_exists($fullPath)) {
        echo "<p>✅ File exists</p>";
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO product_images (product_id, image_path, is_primary, alt_text, sort_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            'TS001',
            $imagePath,
            false, // Not primary
            'TS001B.webp',
            1 // Sort order 1 (B = second image)
        ]);
        
        if ($result) {
            echo "<p>✅ Successfully inserted into database</p>";
            echo "<p>Inserted ID: " . $pdo->lastInsertId() . "</p>";
        } else {
            echo "<p>❌ Failed to insert into database</p>";
        }
        
    } else {
        echo "<p>❌ File does not exist</p>";
    }
    
    // Show current state
    echo "<h3>Current Images in Database:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = 'TS001' ORDER BY sort_order");
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($images)) {
        echo "<p>No images found</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Product ID</th><th>Image Path</th><th>Is Primary</th><th>Sort Order</th></tr>";
        foreach ($images as $img) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($img['id']) . "</td>";
            echo "<td>" . htmlspecialchars($img['product_id']) . "</td>";
            echo "<td>" . htmlspecialchars($img['image_path']) . "</td>";
            echo "<td>" . ($img['is_primary'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($img['sort_order']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 