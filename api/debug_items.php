<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get items data
    $stmt = $pdo->query("SELECT sku, name, imageUrl FROM items LIMIT 10");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get item_images data
    $stmt2 = $pdo->query("SELECT sku, image_path, is_primary FROM item_images LIMIT 10");
    $images = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'item_images' => $images
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?> 