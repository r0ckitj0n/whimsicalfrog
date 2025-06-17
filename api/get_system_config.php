<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get items count and categories
    $stmt = $pdo->query("SELECT COUNT(*) as total_items FROM items");
    $itemsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_items'];
    
    $stmt = $pdo->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get images count
    $stmt = $pdo->query("SELECT COUNT(*) as total_images FROM item_images");
    $imagesCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_images'];
    
    // Get orders count
    $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
    $ordersCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];
    
    // Get order items count
    $stmt = $pdo->query("SELECT COUNT(*) as total_order_items FROM order_items");
    $orderItemsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_order_items'];
    
    // Get database table information
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get SKU patterns
    $stmt = $pdo->query("SELECT sku FROM items ORDER BY sku LIMIT 10");
    $sampleSkus = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get recent activity
    $stmt = $pdo->query("SELECT created_at FROM orders ORDER BY created_at DESC LIMIT 1");
    $lastOrderResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastOrderDate = $lastOrderResult ? $lastOrderResult['created_at'] : null;
    
    // Check if cost breakdown tables exist
    $costTables = [];
    foreach (['inventory_materials', 'inventory_labor', 'inventory_energy', 'inventory_equipment'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch() !== false;
        $costTables[$table] = $exists;
    }
    
    // Get category mapping
    $categoryMap = [
        'T-Shirts' => 'TS',
        'Tumblers' => 'TU', 
        'Artwork' => 'AR',
        'Sublimation' => 'SU',
        'Window Wraps' => 'WW'
    ];
    
    $response = [
        'success' => true,
        'data' => [
            'system_info' => [
                'primary_identifier' => 'SKU',
                'main_entity' => 'Items',
                'sku_format' => 'WF-[CATEGORY]-[NUMBER]',
                'image_directory' => 'images/items/',
                'database_host' => parse_url($dsn, PHP_URL_HOST) ?: 'localhost'
            ],
            'statistics' => [
                'total_items' => (int)$itemsCount,
                'total_images' => (int)$imagesCount,
                'total_orders' => (int)$ordersCount,
                'total_order_items' => (int)$orderItemsCount,
                'categories_count' => count($categories),
                'last_order_date' => $lastOrderDate
            ],
            'database_tables' => [
                'core_tables' => array_filter($tables, function($table) {
                    return in_array($table, ['items', 'item_images', 'orders', 'order_items']);
                }),
                'all_tables' => $tables,
                'cost_breakdown_tables' => $costTables
            ],
            'categories' => $categories,
            'category_codes' => $categoryMap,
            'sample_skus' => $sampleSkus,
            'api_endpoints' => [
                '/api/get_items.php',
                '/api/get_item_images.php', 
                '/api/delete_item_image.php',
                '/api/set_primary_image.php',
                '/process_inventory_update.php'
            ],
            'migration_status' => [
                'products_to_items' => true,
                'product_images_to_item_images' => true,
                'sku_only_system' => true,
                'image_path_migration' => true,
                'terminology_cleanup' => true
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 