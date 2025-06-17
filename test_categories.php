<?php
// Simple test to verify category functionality
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Test getting categories
    $stmt = $pdo->query("SELECT DISTINCT productType FROM products WHERE productType IS NOT NULL ORDER BY productType");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Categories found: " . count($categories) . "\n";
    foreach ($categories as $cat) {
        echo "- " . $cat . "\n";
    }
    
    // Test getting inventory with categories (using correct join)
    $stmt = $pdo->query("SELECT i.id, i.name, i.sku, p.productType AS category FROM inventory i LEFT JOIN products p ON p.id = i.productId LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nInventory items with categories:\n";
    foreach ($items as $item) {
        echo "- {$item['name']} ({$item['sku']}) - Category: " . ($item['category'] ?? 'None') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 