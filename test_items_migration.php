<?php
// Test script to verify items table migration
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "=== Testing Items Table Migration ===\n";
    
    // Check if items table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'items'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Items table exists\n";
    } else {
        echo "❌ Items table does not exist\n";
        exit;
    }
    
    // Check if products table still exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Products table still exists (should be dropped)\n";
    } else {
        echo "✅ Products table has been dropped\n";
    }
    
    // Check items table structure
    echo "\n=== Items Table Structure ===\n";
    $stmt = $pdo->query("DESCRIBE items");
    while ($row = $stmt->fetch()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    // Check items data
    echo "\n=== Items Data ===\n";
    $stmt = $pdo->query("SELECT id, sku, name, category FROM items LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "- {$row['id']}: {$row['sku']} - {$row['name']} ({$row['category']})\n";
    }
    
    // Test categories
    echo "\n=== Categories ===\n";
    $stmt = $pdo->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($categories as $cat) {
        echo "- $cat\n";
    }
    
    echo "\n✅ Migration test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 