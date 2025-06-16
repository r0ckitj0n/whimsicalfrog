<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get all inventory items
    $stmt = $pdo->query("SELECT sku, name FROM inventory ORDER BY sku");
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    $imageDir = __DIR__ . '/../images/products/';
    
    foreach ($inventory as $item) {
        $sku = $item['sku'];
        $name = $item['name'];
        
        // Check what images exist for this SKU
        $expectedImage = $sku . 'A.png';
        $expectedImageWebp = $sku . 'A.webp';
        
        $hasImage = file_exists($imageDir . $expectedImage) || file_exists($imageDir . $expectedImageWebp);
        
        $results[] = [
            'sku' => $sku,
            'name' => $name,
            'expected_image' => $expectedImage,
            'has_image' => $hasImage,
            'image_exists' => file_exists($imageDir . $expectedImage) ? $expectedImage : (file_exists($imageDir . $expectedImageWebp) ? $expectedImageWebp : 'none')
        ];
    }
    
    echo json_encode(['success' => true, 'inventory' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 