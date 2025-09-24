<?php
/**
 * Development Database Setup Script
 * Converts MySQL dump to SQLite for local development
 */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sqliteDbPath = __DIR__ . '/database/whimsicalfrog_dev.sqlite';
$mysqlDumpPath = __DIR__ . '/attached_assets/local_db_dump_2025-09-23_23-24-29_1758686013297.sql';

// Create database directory if it doesn't exist
$dbDir = dirname($sqliteDbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

echo "Setting up development database...\n";

try {
    // Remove existing database if it exists
    if (file_exists($sqliteDbPath)) {
        unlink($sqliteDbPath);
        echo "Removed existing database\n";
    }

    // Create SQLite connection
    $pdo = new PDO("sqlite:$sqliteDbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Created SQLite database: $sqliteDbPath\n";

    // Read and process MySQL dump line by line to avoid memory issues
    if (!file_exists($mysqlDumpPath)) {
        throw new Exception("MySQL dump file not found: $mysqlDumpPath");
    }

    echo "Processing MySQL dump file line by line...\n";
    
    $statements = [];
    $currentStatement = '';
    $handle = fopen($mysqlDumpPath, 'r');
    $lineCount = 0;
    
    if (!$handle) {
        throw new Exception("Could not open MySQL dump file");
    }
    
    while (($line = fgets($handle)) !== false) {
        $lineCount++;
        $line = trim($line);
        
        // Skip comments and MySQL-specific directives
        if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
            continue;
        }
        
        $currentStatement .= $line . ' ';
        
        // Check if statement is complete (ends with semicolon)
        if (substr(rtrim($line), -1) === ';') {
            $statement = trim($currentStatement);
            if (!empty($statement)) {
                $statements[] = convertMysqlToSqlite($statement);
            }
            $currentStatement = '';
        }
        
        // Process in batches to avoid memory buildup
        if (count($statements) >= 100) {
            processStatements($pdo, $statements);
            $statements = [];
        }
    }
    
    fclose($handle);
    
    // Process any remaining statements
    if (!empty($currentStatement)) {
        $statements[] = convertMysqlToSqlite(trim($currentStatement));
    }
    
    echo "Read $lineCount lines from dump file\n";
    
    // Process final batch
    if (!empty($statements)) {
        processStatements($pdo, $statements);
    }
    
    echo "Database setup complete!\n";
    
    // Test connection
    $result = $pdo->query("SELECT COUNT(*) as table_count FROM sqlite_master WHERE type='table'")->fetch();
    echo "Created " . $result['table_count'] . " tables\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function processStatements($pdo, $statements) {
    static $totalProcessed = 0;
    static $totalErrors = 0;
    
    $processed = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '/*!') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $processed++;
            $totalProcessed++;
        } catch (PDOException $e) {
            $errors++;
            $totalErrors++;
            if ($totalErrors <= 10) { // Only show first 10 errors
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    if ($processed > 0 || $errors > 0) {
        echo "Batch: processed $processed, errors $errors (Total: $totalProcessed processed, $totalErrors errors)\n";
    }
}

function convertMysqlToSqlite($sql) {
    // Remove MySQL-specific comments and settings
    $sql = preg_replace('/\/\*!\d+.*?\*\/;?/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//', '', $sql);
    
    // Remove LOCK/UNLOCK statements
    $sql = preg_replace('/LOCK TABLES.*?;/i', '', $sql);
    $sql = preg_replace('/UNLOCK TABLES;/i', '', $sql);
    
    // Convert AUTO_INCREMENT to AUTOINCREMENT
    $sql = preg_replace('/AUTO_INCREMENT/i', 'AUTOINCREMENT', $sql);
    
    // Convert ENGINE and CHARSET declarations
    $sql = preg_replace('/\) ENGINE=\w+.*?;/', ');', $sql);
    
    // Convert MySQL data types to SQLite equivalents
    $sql = preg_replace('/\bint\(\d+\)\s+(NOT\s+NULL\s+)?AUTO_INCREMENT/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\btinyint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bsmallint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bmediumint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bbigint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\btimestamp/i', 'DATETIME', $sql);
    $sql = preg_replace('/\bdatetime/i', 'DATETIME', $sql);
    $sql = preg_replace('/\bvarchar\(\d+\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\btext/i', 'TEXT', $sql);
    $sql = preg_replace('/\blongtext/i', 'TEXT', $sql);
    $sql = preg_replace('/\bmediumtext/i', 'TEXT', $sql);
    $sql = preg_replace('/\bdecimal\([^)]+\)/i', 'REAL', $sql);
    $sql = preg_replace('/\bfloat\([^)]+\)/i', 'REAL', $sql);
    $sql = preg_replace('/\bdouble\([^)]+\)/i', 'REAL', $sql);
    
    // Remove character set and collation specifications
    $sql = preg_replace('/CHARACTER SET \w+/i', '', $sql);
    $sql = preg_replace('/COLLATE \w+/i', '', $sql);
    
    // Convert DEFAULT CURRENT_TIMESTAMP
    $sql = preg_replace('/DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP/i', "DEFAULT CURRENT_TIMESTAMP", $sql);
    
    // Remove unsupported KEY declarations (SQLite handles them differently)
    $sql = preg_replace('/,\s*KEY[^,)]+/i', '', $sql);
    $sql = preg_replace('/,\s*UNIQUE KEY[^,)]+/i', '', $sql);
    $sql = preg_replace('/,\s*INDEX[^,)]+/i', '', $sql);
    
    // Clean up any double commas or trailing commas
    $sql = preg_replace('/,\s*,/', ',', $sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql);
    
    return $sql;
}

echo "Development database setup script ready to run\n";
?>