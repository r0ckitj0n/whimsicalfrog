<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get active tables (exclude backup tables)
    $activeTables = array_filter($tables, function($table) {
        return !preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });
    
    // Get backup tables separately
    $backupTables = array_filter($tables, function($table) {
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
        
        $organizedTables[$category][$table] = true;
    }
    
    $response = [
        'success' => true,
        'data' => [
            'organized' => $organizedTables,
            'active_tables' => array_values($activeTables),
            'backup_tables' => array_values($backupTables),
            'all_tables' => $tables,
            'total_active' => count($activeTables),
            'total_backup' => count($backupTables)
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