<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check what data we have
    $results = [];
    
    // Check products table
    $stmt = $pdo->query("SELECT id, name, productType, basePrice, description, image FROM products LIMIT 3");
    $results['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check inventory table
    $stmt = $pdo->query("SELECT id, sku, name, stockLevel, retailPrice, description FROM inventory LIMIT 3");
    $results['inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check joined data
    $stmt = $pdo->query("SELECT i.id, i.sku, i.name, i.stockLevel, i.retailPrice, i.description, p.productType FROM inventory i LEFT JOIN products p ON p.sku = i.sku LIMIT 3");
    $results['joined'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 