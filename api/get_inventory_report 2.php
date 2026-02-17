<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();
    
    // Fetch detailed inventory data
    $query = "
        SELECT 
            i.name as item_name,
            i.sku as item_sku,
            i.category,
            s.size_name,
            s.stock_level,
            s.gender,
            s.reorder_point
        FROM items i
        JOIN item_sizes s ON i.sku = s.item_sku
        WHERE i.is_active = 1 AND s.is_active = 1
        ORDER BY i.name ASC, s.gender ASC, s.size_name ASC
    ";
    
    // Note: reorder_point might not exist on item_sizes based on previous schema check. 
    // Let's re-check schema. The previous check didn't show reorder_point on item_sizes, only on items.
    // If so, we should use i.reorder_point or just omit it if it's not granular.
    
    $query = "
        SELECT 
            i.name as item_name,
            i.sku as item_sku,
            i.category,
            i.reorder_point as item_reorder_point,
            s.size_name,
            s.stock_level,
            s.gender
        FROM items i
        JOIN item_sizes s ON i.sku = s.item_sku
        WHERE i.is_active = 1 AND s.is_active = 1
        ORDER BY i.name ASC, s.gender ASC, s.size_name ASC
    ";

    $data = Database::queryAll($query);
    
    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
