<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get items count and categories
    $row = Database::queryOne("SELECT COUNT(*) as total_items FROM items");
    $itemsCount = $row ? $row['total_items'] : 0;

    $catRows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $categories = array_values(array_filter(array_map(function($r){ return $r['category'] ?? null; }, $catRows), function($v){ return $v !== null; }));

    // Get images count
    $row = Database::queryOne("SELECT COUNT(*) as total_images FROM item_images");
    $imagesCount = $row ? $row['total_images'] : 0;

    // Get orders count
    $row = Database::queryOne("SELECT COUNT(*) as total_orders FROM orders");
    $ordersCount = $row ? $row['total_orders'] : 0;

    // Get order items count
    $row = Database::queryOne("SELECT COUNT(*) as total_order_items FROM order_items");
    $orderItemsCount = $row ? $row['total_order_items'] : 0;

    // Get database table information
    $tRows = Database::queryAll("SHOW TABLES");
    $tables = array_map(function($r){ return array_values($r)[0]; }, $tRows);

    // Get SKU patterns
    $skuRows = Database::queryAll("SELECT sku FROM items ORDER BY sku LIMIT 10");
    $sampleSkus = array_values(array_map(function($r){ return $r['sku'] ?? null; }, $skuRows));

    // Get recent activity - check what date column exists
    $lastOrderDate = null;
    try {
        // Try different possible date column names
        $possibleDateColumns = ['created_at', 'date_created', 'order_date', 'timestamp'];
        foreach ($possibleDateColumns as $column) {
            try {
                $lastOrderResult = Database::queryOne("SELECT $column FROM orders ORDER BY $column DESC LIMIT 1");
                if ($lastOrderResult && !empty($lastOrderResult[$column])) {
                    $lastOrderDate = $lastOrderResult[$column];
                    break;
                }
            } catch (Exception $e) {
                // Column doesn't exist, try next one
                continue;
            }
        }
    } catch (Exception $e) {
        // If all fail, just set to null
        $lastOrderDate = null;
    }

    // Get active tables (exclude backup tables)
    $activeTables = array_filter($tables, function ($table) {
        return !preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });

    // Get backup tables separately
    $backupTables = array_filter($tables, function ($table) {
        return preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });

    // Dynamically categorize tables based on naming patterns
    $organizedTables = [];

    foreach ($activeTables as $table) {
        $category = 'other'; // default category

        // Categorize based on table name patterns
        if (in_array($table, ['items', 'item_images', 'orders', 'order_items'])) {
            $category = 'core_tables';
        } elseif (strpos($table, 'inventory_') === 0) {
            $category = 'inventory_cost';
        } elseif (strpos($table, 'email_') === 0) {
            $category = 'email_system';
        } elseif (strpos($table, 'room_') === 0 || $table === 'backgrounds' || $table === 'area_mappings') {
            $category = 'room_management';
        } elseif (strpos($table, 'social_') === 0) {
            $category = 'social_media';
        } elseif ($table === 'users') {
            $category = 'user_management';
        } elseif ($table === 'categories') {
            $category = 'product_categories';
        } elseif (in_array($table, ['business_settings', 'discount_codes'])) {
            $category = 'business_config';
        }

        if (!isset($organizedTables[$category])) {
            $organizedTables[$category] = [];
        }

        $organizedTables[$category][$table] = true; // All active tables exist by definition
    }

    // Get sample data for ID formats
    $row = Database::queryOne("SELECT id FROM users ORDER BY id DESC LIMIT 1");
    $sampleCustomer = $row ? $row['id'] : null;
    $row = Database::queryOne("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
    $sampleOrder = $row ? $row['id'] : null;
    $row = Database::queryOne("SELECT id FROM order_items ORDER BY id DESC LIMIT 1");
    $sampleOrderItem = $row ? $row['id'] : null;

    // Get some real examples
    $recentCustomers = Database::queryAll("SELECT id, username FROM users ORDER BY id DESC LIMIT 3");
    $rOrders = Database::queryAll("SELECT id FROM orders ORDER BY id DESC LIMIT 3");
    $recentOrders = array_values(array_map(function($r){ return $r['id'] ?? null; }, $rOrders));
    $rOrderItems = Database::queryAll("SELECT id FROM order_items ORDER BY id DESC LIMIT 3");
    $recentOrderItems = array_values(array_map(function($r){ return $r['id'] ?? null; }, $rOrderItems));

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
            'id_formats' => [
                'customer_example' => $sampleCustomer,
                'order_example' => $sampleOrder,
                'order_item_example' => $sampleOrderItem,
                'recent_customers' => $recentCustomers,
                'recent_orders' => $recentOrders,
                'recent_order_items' => $recentOrderItems
            ],
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