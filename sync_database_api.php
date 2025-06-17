<?php
/**
 * Database Sync Script for WhimsicalFrog - API Method
 * Uses the existing API infrastructure to sync databases
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

echo "🔄 Starting database sync from local to live via API...\n";

try {
    // Connect to local database
    echo "📱 Connecting to local database...\n";
    $localPdo = new PDO($localDsn, $localUser, $localPass, $options);
    
    // Get list of tables to sync (exclude backup tables)
    $tables = $localPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tablesToSync = array_filter($tables, function($table) {
        return !preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });
    
    echo "📋 Tables to sync: " . implode(', ', $tablesToSync) . "\n";
    
    // For each table, sync via API
    foreach ($tablesToSync as $table) {
        echo "🔄 Syncing table: $table\n";
        
        // Get table structure
        $createTableStmt = $localPdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $createTableSql = $createTableStmt['Create Table'];
        
        // Drop and recreate table via API
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
            
            // Get all data in batches to avoid memory issues
            $batchSize = 100;
            $offset = 0;
            
            while ($offset < $count) {
                $data = $localPdo->query("SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset")->fetchAll();
                
                if (!empty($data)) {
                    // Get column names
                    $columns = array_keys($data[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
                    // Build batch insert
                    $values = [];
                    foreach ($data as $row) {
                        $escapedValues = array_map(function($value) {
                            return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                        }, $row);
                        $values[] = '(' . implode(', ', $escapedValues) . ')';
                    }
                    
                    $insertSql = "INSERT INTO `$table` ($columnList) VALUES " . implode(', ', $values);
                    
                    $response = sendApiQuery($insertSql);
                    if (!$response || !$response['success']) {
                        echo "  ❌ Failed to insert batch: " . ($response['error'] ?? 'Unknown error') . "\n";
                        break;
                    }
                }
                
                $offset += $batchSize;
                echo "  ⏳ Processed " . min($offset, $count) . "/$count records\n";
            }
        } else {
            echo "  📊 Table is empty, skipping data copy\n";
        }
        
        echo "  ✅ Table $table synced successfully\n";
    }
    
    echo "\n🎉 Database sync completed successfully!\n";
    
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

echo "\n✅ Database sync process completed!\n";

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
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['success' => false, 'error' => 'API request failed'];
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON response: ' . $response];
    }
    
    return $decoded;
}
?> 