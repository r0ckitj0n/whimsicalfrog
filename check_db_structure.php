<?php
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Checking database structure...\n\n";
    
    // Check if product_images table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_images'");
    if ($stmt->rowCount() > 0) {
        echo "✅ product_images table exists\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE product_images");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nColumns in product_images table:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM product_images LIMIT 3");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nSample data:\n";
        foreach ($samples as $i => $sample) {
            echo "Row " . ($i + 1) . ":\n";
            foreach ($sample as $key => $value) {
                echo "  $key: $value\n";
            }
            echo "\n";
        }
        
    } else {
        echo "❌ product_images table does not exist\n";
    }
    
    // Check items table structure
    echo "\n" . str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("DESCRIBE items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in items table:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 