<?php
require_once __DIR__ . '/api/config.php';

echo "<h2>Local Database Check & Fix</h2>";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p>✅ Database connection successful</p>";
    echo "<p>Environment: " . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "</p>";
    echo "<p>Database: $host/$db</p>";
    
    // Check if product_images table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_images'");
    if ($stmt->rowCount() === 0) {
        echo "<p>❌ product_images table does not exist. Creating...</p>";
        
        // Create the table
        $createTable = "
        CREATE TABLE `product_images` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_id` varchar(50) NOT NULL,
            `image_path` varchar(255) NOT NULL,
            `is_primary` tinyint(1) DEFAULT 0,
            `sort_order` int(11) DEFAULT 0,
            `alt_text` varchar(255) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_product_path` (`product_id`,`image_path`),
            KEY `idx_product_id` (`product_id`),
            KEY `idx_is_primary` (`is_primary`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTable);
        echo "<p>✅ product_images table created successfully</p>";
    } else {
        echo "<p>✅ product_images table exists</p>";
    }
    
    // Check for existing images in the filesystem and add them to database
    $imagesDir = __DIR__ . '/images/products/';
    if (is_dir($imagesDir)) {
        $files = glob($imagesDir . '*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE);
        echo "<p>Found " . count($files) . " image files in products directory</p>";
        
        foreach ($files as $file) {
            $filename = basename($file);
            $relativePath = 'images/products/' . $filename;
            
            // Extract product ID from filename (e.g., TS001A.png -> TS001)
            if (preg_match('/^([A-Z]+\d+)[A-Z]?\.(png|jpg|jpeg|webp|gif)$/i', $filename, $matches)) {
                $productId = $matches[1];
                
                // Check if this image is already in database
                $stmt = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? AND image_path = ?");
                $stmt->execute([$productId, $relativePath]);
                
                if ($stmt->rowCount() === 0) {
                    // Determine if this should be primary (first image for this product)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                    $stmt->execute([$productId]);
                    $isPrimary = $stmt->fetchColumn() === 0;
                    
                    // Determine sort order from letter suffix
                    $sortOrder = 0;
                    if (preg_match('/([A-Z])\./', $filename, $letterMatch)) {
                        $sortOrder = ord($letterMatch[1]) - 65; // A=0, B=1, etc.
                    }
                    
                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, image_path, is_primary, alt_text, sort_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$productId, $relativePath, $isPrimary, $filename, $sortOrder]);
                    
                    echo "<p>➕ Added $filename to database (Product: $productId, Primary: " . ($isPrimary ? 'Yes' : 'No') . ")</p>";
                } else {
                    echo "<p>✅ $filename already in database</p>";
                }
            } else {
                echo "<p>⚠️ Skipped $filename (doesn't match naming pattern)</p>";
            }
        }
    } else {
        echo "<p>❌ Images directory does not exist: $imagesDir</p>";
    }
    
    // Show current state
    echo "<h3>Current Images in Database:</h3>";
    $stmt = $pdo->query("SELECT * FROM product_images ORDER BY product_id, sort_order");
    $images = $stmt->fetchAll();
    
    if (empty($images)) {
        echo "<p>No images found in database</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Product ID</th><th>Image Path</th><th>Primary</th><th>Sort Order</th><th>File Exists</th></tr>";
        foreach ($images as $img) {
            $fileExists = file_exists(__DIR__ . '/' . $img['image_path']);
            echo "<tr>";
            echo "<td>" . htmlspecialchars($img['id']) . "</td>";
            echo "<td>" . htmlspecialchars($img['product_id']) . "</td>";
            echo "<td>" . htmlspecialchars($img['image_path']) . "</td>";
            echo "<td>" . ($img['is_primary'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($img['sort_order']) . "</td>";
            echo "<td>" . ($fileExists ? '✅' : '❌') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 