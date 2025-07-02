<?php
/**
 * WhimsicalFrog Live Database Restore Script
 * 
 * This script restores the cleaned database backup on the live server.
 * Run this script on the live server after uploading the backup file.
 */

class LiveDatabaseRestore {
    private $liveConfig = [
        'host' => 'db5017975223.hosting-data.io',
        'db' => 'dbs14295502',
        'user' => 'dbu2826619',
        'pass' => 'Palz2516!'
    ];
    
    private $backupFile;
    private $pdo;
    
    public function __construct($backupFile) {
        $this->backupFile = $backupFile;
        $this->connectToDatabase();
    }
    
    private function connectToDatabase() {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $dsn = "mysql:host={$this->liveConfig['host']};dbname={$this->liveConfig['db']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $this->liveConfig['user'], $this->liveConfig['pass'], $options);
            
            echo "✅ Connected to live database successfully\n";
        } catch (Exception $e) {
            throw new Exception("Failed to connect to live database: " . $e->getMessage());
        }
    }
    
    public function validateBackupFile() {
        echo "🔍 Validating backup file...\n";
        echo "============================\n";
        
        if (!file_exists($this->backupFile)) {
            echo "❌ Backup file not found: {$this->backupFile}\n";
            echo "💡 Make sure you've uploaded the backup file to the server\n";
            return false;
        }
        
        $size = filesize($this->backupFile);
        echo "📄 File: {$this->backupFile}\n";
        echo "📊 Size: " . $this->formatBytes($size) . "\n";
        
        if (!is_readable($this->backupFile)) {
            echo "❌ Backup file is not readable\n";
            return false;
        }
        
        // Check file content
        $handle = fopen($this->backupFile, 'r');
        $firstLine = fgets($handle);
        fclose($handle);
        
        if (strpos($firstLine, 'WhimsicalFrog') === false) {
            echo "⚠️  File doesn't appear to be a WhimsicalFrog backup\n";
            echo "First line: " . substr($firstLine, 0, 100) . "\n";
            echo "Continuing anyway...\n";
        } else {
            echo "✅ Backup file appears valid\n";
        }
        
        return true;
    }
    
    public function createPreRestoreBackup() {
        echo "\n💾 Creating pre-restore backup of current live database...\n";
        echo "========================================================\n";
        
        $backupFile = "live_backup_before_restore_" . date('Y-m-d_H-i-s') . ".sql";
        
        try {
            $backupContent = "-- Live Database Backup Before Restore\n";
            $backupContent .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
            $backupContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            // Get all current tables
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "📊 Backing up " . count($tables) . " existing tables...\n";
            
            foreach ($tables as $table) {
                echo "  📄 Backing up: {$table}\n";
                $backupContent .= $this->generateTableBackup($table);
            }
            
            $backupContent .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
            
            file_put_contents($backupFile, $backupContent);
            echo "✅ Pre-restore backup created: {$backupFile}\n";
            
            return $backupFile;
        } catch (Exception $e) {
            echo "⚠️  Could not create pre-restore backup: " . $e->getMessage() . "\n";
            echo "Continuing with restore anyway...\n";
            return null;
        }
    }
    
    private function generateTableBackup($table) {
        $backup = "\n-- Table: {$table}\n";
        $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        try {
            // Get table structure
            $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
            $createTable = $stmt->fetch();
            $backup .= $createTable['Create Table'] . ";\n\n";
            
            // Get table data (limit to prevent memory issues)
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $rowCount = $stmt->fetchColumn();
            
            if ($rowCount > 0 && $rowCount < 10000) { // Only backup smaller tables
                $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $backup .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                    
                    $values = [];
                    foreach ($rows as $row) {
                        $escapedValues = array_map(function($value) {
                            return $value === null ? 'NULL' : $this->pdo->quote($value);
                        }, array_values($row));
                        $values[] = '(' . implode(', ', $escapedValues) . ')';
                    }
                    $backup .= implode(",\n", $values) . ";\n\n";
                }
            } else {
                $backup .= "-- Table has {$rowCount} rows - data not included in backup\n\n";
            }
        } catch (Exception $e) {
            $backup .= "-- Error backing up table {$table}: " . $e->getMessage() . "\n\n";
        }
        
        return $backup;
    }
    
    public function restoreDatabase() {
        echo "\n🔄 Restoring database from backup...\n";
        echo "====================================\n";
        
        // Read the backup file
        echo "📖 Reading backup file...\n";
        $backupContent = file_get_contents($this->backupFile);
        
        if ($backupContent === false) {
            throw new Exception("Could not read backup file");
        }
        
        // Split into statements
        $statements = $this->splitSqlStatements($backupContent);
        echo "📊 Found " . count($statements) . " SQL statements to execute\n\n";
        
        // Execute statements
        echo "⚡ Executing SQL statements...\n";
        $executed = 0;
        $errors = 0;
        $startTime = time();
        
        // Disable foreign key checks during restore
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($statements as $i => $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $this->pdo->exec($statement);
                $executed++;
                
                if ($executed % 50 === 0) {
                    $elapsed = time() - $startTime;
                    echo "  ✅ Executed {$executed} statements... ({$elapsed}s elapsed)\n";
                }
            } catch (Exception $e) {
                $errors++;
                if ($errors <= 5) { // Show first 5 errors
                    echo "  ⚠️  Error in statement " . ($i + 1) . ": " . substr($statement, 0, 100) . "...\n";
                    echo "     " . $e->getMessage() . "\n";
                }
            }
        }
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $totalTime = time() - $startTime;
        
        echo "\n📈 Restore Summary:\n";
        echo "==================\n";
        echo "  ✅ Successful statements: {$executed}\n";
        echo "  ❌ Failed statements: {$errors}\n";
        echo "  ⏱️  Total time: {$totalTime} seconds\n";
        
        if ($errors > 0) {
            echo "  ⚠️  Some statements failed, but core data should be restored\n";
        }
        
        return $errors === 0;
    }
    
    public function verifyRestore() {
        echo "\n🔍 Verifying database restore...\n";
        echo "================================\n";
        
        try {
            // Check table count
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "📊 Total tables: " . count($tables) . "\n";
            
            // Check core tables
            $coreTables = ['items', 'item_images', 'orders', 'order_items', 'users', 'global_css_rules'];
            echo "\n📂 Core table verification:\n";
            
            foreach ($coreTables as $table) {
                if (in_array($table, $tables)) {
                    $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
                    $count = $stmt->fetchColumn();
                    echo "  ✅ {$table}: {$count} rows\n";
                } else {
                    echo "  ❌ {$table}: Table missing\n";
                }
            }
            
            // Test a simple query
            echo "\n🔧 Testing database functionality...\n";
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM items WHERE stockLevel > 0");
            $result = $stmt->fetch();
            echo "  ✅ Items with stock: " . $result['total'] . "\n";
            
            echo "\n🎉 Database restore verification completed!\n";
            
        } catch (Exception $e) {
            echo "❌ Verification failed: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    private function splitSqlStatements($sql) {
        // Remove comments and split by semicolons
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = preg_split('/;\s*$/m', $sql);
        
        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $backupFile = $argv[1] ?? 'full_database_backup_2025-07-02_20-25-44.sql';
    $skipBackup = in_array('--skip-backup', $argv);
    
    echo "🚀 WhimsicalFrog Live Database Restore\n";
    echo "======================================\n";
    echo "Backup file: {$backupFile}\n\n";
    
    try {
        $restore = new LiveDatabaseRestore($backupFile);
        
        // Step 1: Validate backup file
        if (!$restore->validateBackupFile()) {
            echo "❌ Backup file validation failed\n";
            exit(1);
        }
        
        // Step 2: Create pre-restore backup (optional)
        if (!$skipBackup) {
            $preBackup = $restore->createPreRestoreBackup();
            if ($preBackup) {
                echo "💡 Pre-restore backup saved as: {$preBackup}\n";
            }
        } else {
            echo "⚠️  Skipping pre-restore backup as requested\n";
        }
        
        // Step 3: Restore database
        $success = $restore->restoreDatabase();
        
        // Step 4: Verify restore
        $restore->verifyRestore();
        
        if ($success) {
            echo "\n🎉 Database restore completed successfully!\n";
            echo "💡 Your live database has been updated with the cleaned local data\n";
        } else {
            echo "\n⚠️  Database restore completed with some errors\n";
            echo "💡 Check the error messages above and verify your site functionality\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Restore failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
} else {
    // Web interface
    echo "<h1>WhimsicalFrog Database Restore</h1>";
    echo "<p>This script restores the cleaned database backup to the live server.</p>";
    echo "<p>Run via command line: <code>php restore_live_database.php [backup_file] [--skip-backup]</code></p>";
}
?> 