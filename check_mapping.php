<?php
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Mapping old product IDs to new SKUs:\n\n";
    
    // Get all items with their SKUs
    $stmt = $pdo->query('SELECT sku, name FROM items');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all product images
    $stmt = $pdo->query('SELECT image_path, alt_text FROM product_images');
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Items (SKUs):\n";
    foreach ($items as $item) {
        echo "  " . $item['sku'] . " - " . $item['name'] . "\n";
    }
    
    echo "\nImages (old paths):\n";
    foreach ($images as $image) {
        echo "  " . $image['image_path'] . " - " . $image['alt_text'] . "\n";
    }
    
    // Try to create mapping
    echo "\nSuggested mapping:\n";
    $mapping = [
        'TS001' => 'WF-TS-001',
        'TS002' => 'WF-TS-002', 
        'TU001' => 'WF-TU-001',
        'TU002' => 'WF-TU-002',
        'AW001' => 'WF-AR-001',
        'SB001' => 'WF-SU-001',
        'SB002' => 'WF-SU-002',
        'WW001' => 'WF-WW-001',
        'WW002' => 'WF-WW-002'
    ];
    
    foreach ($mapping as $oldId => $newSku) {
        echo "  $oldId -> $newSku\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 