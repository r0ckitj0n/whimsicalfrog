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
    
    // Get recent activity - check what date column exists
    $lastOrderDate = null;
    try {
        // Try different possible date column names
        $possibleDateColumns = ['created_at', 'date_created', 'order_date', 'timestamp'];
        foreach ($possibleDateColumns as $column) {
            try {
                $stmt = $pdo->query("SELECT $column FROM orders ORDER BY $column DESC LIMIT 1");
                $lastOrderResult = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($lastOrderResult && $lastOrderResult[$column]) {
                    $lastOrderDate = $lastOrderResult[$column];
                    break;
                }
            } catch (PDOException $e) {
                // Column doesn't exist, try next one
                continue;
            }
        }
    } catch (PDOException $e) {
        // If all fail, just set to null
        $lastOrderDate = null;
    }
    
    // Organize tables by category and check existence
    $tableCategories = [
        'core_tables' => ['items', 'item_images', 'orders', 'order_items'],
        'user_management' => ['users'],
        'inventory_cost' => ['inventory_materials', 'inventory_labor', 'inventory_energy', 'inventory_equipment'],
        'product_categories' => ['categories'],
        'room_management' => ['room_maps', 'room_category_assignments', 'area_mappings', 'backgrounds'],
        'email_system' => ['email_logs', 'email_campaigns', 'email_subscribers', 'email_campaign_sends'],
        'business_config' => ['business_settings', 'discount_codes'],
        'social_media' => ['social_accounts', 'social_posts']
    ];
    
    $organizedTables = [];
    foreach ($tableCategories as $category => $tableList) {
        $organizedTables[$category] = [];
        foreach ($tableList as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->fetch() !== false;
            $organizedTables[$category][$table] = $exists;
        }
    }
    
    // Get active tables (exclude backup tables)
    $activeTables = array_filter($tables, function($table) {
        return !preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });
    
    // Get backup tables separately
    $backupTables = array_filter($tables, function($table) {
        return preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });
    
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
                'organized' => $organizedTables,
                'active_tables' => array_values($activeTables),
                'backup_tables' => array_values($backupTables),
                'all_tables' => $tables,
                'total_active' => count($activeTables),
                'total_backup' => count($backupTables)
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