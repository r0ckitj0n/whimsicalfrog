<?php
/**
 * Database Sync Script for WhimsicalFrog
 * Exports local database and syncs it to live server
 */

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Configuration
$localDsn = "mysql:host=localhost;dbname=whimsicalfrog;charset=utf8mb4";
$localUser = "root";
$localPass = "Palz2516";

$liveDsn = "mysql:host=db5017975223.hosting-data.io;dbname=dbs14295502;charset=utf8mb4";
$liveUser = "dbu2826619";
$livePass = "Palz2516!";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

echo "🔄 Starting database sync from local to live...\n";

try {
    // Connect to local database
    echo "📱 Connecting to local database...\n";
    $localPdo = new PDO($localDsn, $localUser, $localPass, $options);
    
    // Connect to live database
    echo "🌐 Connecting to live database...\n";
    $livePdo = new PDO($liveDsn, $liveUser, $livePass, $options);
    
    // Get list of tables to sync (exclude backup tables)
    $tables = $localPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tablesToSync = array_filter($tables, function($table) {
        return !preg_match('/backup|_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}/', $table);
    });
    
    echo "📋 Tables to sync: " . implode(', ', $tablesToSync) . "\n";
    
    // For each table, get structure and data
    foreach ($tablesToSync as $table) {
        echo "🔄 Syncing table: $table\n";
        
        // Get table structure
        $createTableStmt = $localPdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $createTableSql = $createTableStmt['Create Table'];
        
        // Drop and recreate table on live server
        $livePdo->exec("DROP TABLE IF EXISTS `$table`");
        $livePdo->exec($createTableSql);
        
        // Get data count
        $count = $localPdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        
        if ($count > 0) {
            echo "  📊 Copying $count records...\n";
            
            // Get all data
            $data = $localPdo->query("SELECT * FROM `$table`")->fetchAll();
            
            if (!empty($data)) {
                // Get column names
                $columns = array_keys($data[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                $placeholders = ':' . implode(', :', $columns);
                
                // Prepare insert statement
                $insertSql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";
                $stmt = $livePdo->prepare($insertSql);
                
                // Insert each row
                foreach ($data as $row) {
                    $stmt->execute($row);
                }
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
        $liveCount = $livePdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        
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
?> 