<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
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
    
    // Dynamically categorize tables based on naming patterns and content analysis
    $organizedTables = [];
    $tableDetails = [];
    
    foreach ($activeTables as $table) {
        $category = 'other'; // default category
        
        // Get table structure for better categorization
        $structStmt = $pdo->prepare("DESCRIBE `$table`");
        $structStmt->execute();
        $structure = $structStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get row count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$table`");
        $countStmt->execute();
        $rowCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Store table details
        $tableDetails[$table] = [
            'structure' => $structure,
            'row_count' => $rowCount,
            'fields' => array_column($structure, 'Field')
        ];
        
        // Enhanced categorization based on table name patterns and structure
        if (in_array($table, ['items', 'item_images', 'item_colors', 'orders', 'order_items'])) {
            $category = 'core_ecommerce';
        } elseif (strpos($table, 'inventory_') === 0 || strpos($table, 'cost_') === 0) {
            $category = 'inventory_cost';
        } elseif (strpos($table, 'email_') === 0 || strpos($table, 'mail_') === 0) {
            $category = 'email_system';
        } elseif (strpos($table, 'room_') === 0 || $table === 'backgrounds' || $table === 'area_mappings') {
            $category = 'room_management';
        } elseif (strpos($table, 'social_') === 0 || strpos($table, 'marketing_') === 0) {
            $category = 'marketing_social';
        } elseif ($table === 'users' || strpos($table, 'user_') === 0) {
            $category = 'user_management';
        } elseif ($table === 'categories' || strpos($table, 'categor') !== false) {
            $category = 'product_categories';
        } elseif (in_array($table, ['business_settings', 'discount_codes', 'sales', 'sale_items'])) {
            $category = 'business_config';
        } elseif (strpos($table, 'help_') === 0 || strpos($table, 'tooltip') !== false) {
            $category = 'help_system';
        } elseif (strpos($table, 'ai_') === 0 || strpos($table, 'square_') === 0) {
            $category = 'integrations';
        } elseif (strpos($table, 'receipt_') === 0 || strpos($table, 'analytics') !== false) {
            $category = 'analytics_receipts';
        } elseif (strpos($table, 'css_') === 0 || strpos($table, 'style') !== false) {
            $category = 'styling_theme';
        }
        
        // Further categorization based on field names
        $fields = array_column($structure, 'Field');
        if (in_array('created_at', $fields) && in_array('updated_at', $fields)) {
            // This is likely a content/data table
            if ($category === 'other') {
                if (in_array('name', $fields) || in_array('title', $fields)) {
                    $category = 'content_data';
                }
            }
        }
        
        if (!isset($organizedTables[$category])) {
            $organizedTables[$category] = [];
        }
        
        $organizedTables[$category][$table] = [
            'exists' => true,
            'row_count' => $rowCount,
            'field_count' => count($structure)
        ];
    }
    
    // Sort categories and tables
    ksort($organizedTables);
    foreach ($organizedTables as $category => $tables) {
        ksort($organizedTables[$category]);
    }
    
    $response = [
        'success' => true,
        'data' => [
            'organized' => $organizedTables,
            'active_tables' => array_values($activeTables),
            'backup_tables' => array_values($backupTables),
            'all_tables' => $tables,
            'table_details' => $tableDetails,
            'total_active' => count($activeTables),
            'total_backup' => count($backupTables),
            'categories' => array_keys($organizedTables)
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