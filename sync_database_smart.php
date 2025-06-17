<?php
/**
 * Smart Database Sync Script for WhimsicalFrog
 * Handles foreign key constraints by syncing in correct order
 */

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Configuration for local database
$localDsn = "mysql:host=localhost;dbname=whimsicalfrog;charset=utf8mb4";
$localUser = "root";
$localPass = "Palz2516";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

echo "🔄 Starting smart database sync from local to live...\n";

try {
    // Connect to local database
    echo "📱 Connecting to local database...\n";
    $localPdo = new PDO($localDsn, $localUser, $localPass, $options);
    
    // Define table sync order to respect foreign key constraints
    $tableOrder = [
        // Independent tables first
        'categories',
        'users',
        'social_accounts',
        'backgrounds',
        'discount_codes',
        'email_campaigns',
        'email_subscribers',
        
        // Items table (referenced by others)
        'items',
        
        // Tables that reference items
        'item_images',
        
        // Order-related tables
        'orders',
        'order_items',
        
        // Room and category assignments
        'room_maps',
        'room_category_assignments',
        
        // Social posts (references social_accounts)
        'social_posts',
        
        // Skip inventory cost tables for now (they have old foreign key references)
        // 'inventory_materials',
        // 'inventory_labor', 
        // 'inventory_energy',
        // 'inventory_equipment',
    ];
    
    // Get all available tables
    $allTables = $localPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $availableTables = array_filter($allTables, function($table) {
        return !preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });
    
    // Only sync tables that exist and are in our order
    $tablesToSync = array_intersect($tableOrder, $availableTables);
    
    echo "📋 Tables to sync in order: " . implode(', ', $tablesToSync) . "\n";
    
    // Disable foreign key checks on live server
    echo "🔧 Disabling foreign key checks...\n";
    $response = sendApiQuery("SET FOREIGN_KEY_CHECKS = 0");
    if (!$response || !$response['success']) {
        echo "  ⚠️  Warning: Could not disable foreign key checks\n";
    }
    
    // Sync each table in order
    foreach ($tablesToSync as $table) {
        echo "🔄 Syncing table: $table\n";
        
        // Get table structure
        $createTableStmt = $localPdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $createTableSql = $createTableStmt['Create Table'];
        
        // Drop and recreate table
        $dropSql = "DROP TABLE IF EXISTS `$table`";
        $response = sendApiQuery($dropSql);
        if (!$response || !$response['success']) {
            echo "  ❌ Failed to drop table: " . ($response['error'] ?? 'Unknown error') . "\n";
            continue;
        }
        
        $response = sendApiQuery($createTableSql);
        if (!$response || !$response['success']) {
            echo "  ❌ Failed to create table: " . ($response['error'] ?? 'Unknown error') . "\n";
            continue;
        }
        
        // Get data count
        $count = $localPdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        
        if ($count > 0) {
            echo "  📊 Copying $count records...\n";
            
            // Sync data in smaller batches for better reliability
            $batchSize = 50;
            $offset = 0;
            
            while ($offset < $count) {
                $data = $localPdo->query("SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset")->fetchAll();
                
                if (!empty($data)) {
                    // Get column names
                    $columns = array_keys($data[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
                    // Build batch insert with proper escaping
                    $values = [];
                    foreach ($data as $row) {
                        $escapedValues = array_map(function($value) {
                            if ($value === null) {
                                return 'NULL';
                            } elseif (is_numeric($value)) {
                                return $value;
                            } else {
                                return "'" . addslashes($value) . "'";
                            }
                        }, $row);
                        $values[] = '(' . implode(', ', $escapedValues) . ')';
                    }
                    
                    $insertSql = "INSERT INTO `$table` ($columnList) VALUES " . implode(', ', $values);
                    
                    $response = sendApiQuery($insertSql);
                    if (!$response || !$response['success']) {
                        echo "  ❌ Failed to insert batch: " . ($response['error'] ?? 'Unknown error') . "\n";
                        // Continue with next batch instead of breaking
                    }
                }
                
                $offset += $batchSize;
                echo "  ⏳ Processed " . min($offset, $count) . "/$count records\n";
            }
        } else {
            echo "  📊 Table is empty, skipping data copy\n";
        }
        
        echo "  ✅ Table $table sync completed\n";
    }
    
    // Re-enable foreign key checks
    echo "🔧 Re-enabling foreign key checks...\n";
    $response = sendApiQuery("SET FOREIGN_KEY_CHECKS = 1");
    if (!$response || !$response['success']) {
        echo "  ⚠️  Warning: Could not re-enable foreign key checks\n";
    }
    
    echo "\n🎉 Smart database sync completed!\n";
    
    // Verify sync by comparing record counts
    echo "\n📊 Verification - Record counts:\n";
    foreach ($tablesToSync as $table) {
        $localCount = $localPdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        
        // Get live count via API
        $response = sendApiQuery("SELECT COUNT(*) as count FROM `$table`");
        $liveCount = ($response && $response['success'] && !empty($response['data'])) ? $response['data'][0]['count'] : 0;
        
        $status = ($localCount == $liveCount) ? "✅" : "❌";
        echo "  $status $table: Local=$localCount, Live=$liveCount\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database sync failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Sync failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Smart database sync process completed!\n";

/**
 * Send SQL query to live server via API
 */
function sendApiQuery($sql) {
    $url = 'https://whimsicalfrog.us/api/db_manager.php';
    $data = [
        'action' => 'query',
        'sql' => $sql,
        'admin_token' => 'whimsical_admin_2024'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 60 // Increased timeout for large operations
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['success' => false, 'error' => 'API request failed'];
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON response: ' . substr($response, 0, 200) . '...'];
    }
    
    return $decoded;
}
?> 