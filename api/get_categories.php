<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Get categories from products table to ensure single source of truth
    $stmt = $pdo->query("SELECT DISTINCT productType FROM products WHERE productType IS NOT NULL ORDER BY productType");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!is_array($categories)) {
        $categories = [];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch categories: ' . $e->getMessage()
    ]);
}
?> 