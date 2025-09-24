<?php
/**
 * PostgreSQL Database Setup Script
 * Converts MySQL dump to PostgreSQL for production-quality database
 */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pgHost = getenv('PGHOST');
$pgPort = getenv('PGPORT');
$pgDatabase = getenv('PGDATABASE');
$pgUser = getenv('PGUSER');
$pgPassword = getenv('PGPASSWORD');
$mysqlDumpPath = __DIR__ . '/attached_assets/local_db_dump_2025-09-23_23-24-29_1758686013297.sql';

if (!$pgHost || !$pgPort || !$pgDatabase || !$pgUser || !$pgPassword) {
    die("Error: PostgreSQL environment variables not found.\n");
}

echo "Setting up PostgreSQL database...\n";
echo "Host: $pgHost:$pgPort\n";
echo "Database: $pgDatabase\n";
echo "User: $pgUser\n";

try {
    // Connect to PostgreSQL using individual parameters
    $dsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDatabase;sslmode=require";
    $pdo = new PDO($dsn, $pgUser, $pgPassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to PostgreSQL successfully\n";

    // Read and process MySQL dump
    if (!file_exists($mysqlDumpPath)) {
        throw new Exception("MySQL dump file not found: $mysqlDumpPath");
    }

    echo "Processing MySQL dump file...\n";
    
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
                $statements[] = convertMysqlToPostgres($statement);
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
        $statements[] = convertMysqlToPostgres(trim($currentStatement));
    }
    
    // Process final batch
    if (!empty($statements)) {
        processStatements($pdo, $statements);
    }
    
    echo "Read $lineCount lines from dump file\n";
    
    // Test connection and count tables
    $result = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'public'")->fetch();
    echo "Created " . $result['table_count'] . " tables in PostgreSQL\n";
    
    // Test some key tables
    $testTables = ['users', 'inventory_materials', 'backgrounds'];
    foreach ($testTables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch();
            echo "Table $table: " . $count['count'] . " records\n";
        } catch (Exception $e) {
            echo "Table $table: not found or error\n";
        }
    }
    
    echo "PostgreSQL database setup complete!\n";
    
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

function convertMysqlToPostgres($sql) {
    // Remove MySQL-specific comments and settings
    $sql = preg_replace('/\/\*!\d+.*?\*\/;?/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//', '', $sql);
    
    // Remove LOCK/UNLOCK statements
    $sql = preg_replace('/LOCK TABLES.*?;/i', '', $sql);
    $sql = preg_replace('/UNLOCK TABLES;/i', '', $sql);
    
    // Convert MySQL data types to PostgreSQL equivalents
    $sql = preg_replace('/\bint\(\d+\)\s+(NOT\s+NULL\s+)?AUTO_INCREMENT/i', 'SERIAL', $sql);
    $sql = preg_replace('/\bint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\btinyint\(\d+\)/i', 'SMALLINT', $sql);
    $sql = preg_replace('/\bsmallint\(\d+\)/i', 'SMALLINT', $sql);
    $sql = preg_replace('/\bmediumint\(\d+\)/i', 'INTEGER', $sql);
    $sql = preg_replace('/\bbigint\(\d+\)/i', 'BIGINT', $sql);
    $sql = preg_replace('/\btimestamp/i', 'TIMESTAMP', $sql);
    $sql = preg_replace('/\bdatetime/i', 'TIMESTAMP', $sql);
    $sql = preg_replace('/\bvarchar\(\d+\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\btext/i', 'TEXT', $sql);
    $sql = preg_replace('/\blongtext/i', 'TEXT', $sql);
    $sql = preg_replace('/\bmediumtext/i', 'TEXT', $sql);
    $sql = preg_replace('/\bdecimal\([^)]+\)/i', 'DECIMAL', $sql);
    $sql = preg_replace('/\bfloat\([^)]+\)/i', 'REAL', $sql);
    $sql = preg_replace('/\bdouble\([^)]+\)/i', 'DOUBLE PRECISION', $sql);
    
    // Convert AUTO_INCREMENT to SERIAL
    $sql = preg_replace('/AUTO_INCREMENT/i', '', $sql);
    
    // Convert DEFAULT CURRENT_TIMESTAMP
    $sql = preg_replace('/DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP/i', "DEFAULT CURRENT_TIMESTAMP", $sql);
    
    // Remove ENGINE and CHARSET declarations
    $sql = preg_replace('/\) ENGINE=\w+.*?;/', ');', $sql);
    
    // Remove character set and collation specifications
    $sql = preg_replace('/CHARACTER SET \w+/i', '', $sql);
    $sql = preg_replace('/COLLATE \w+/i', '', $sql);
    
    // Convert backticks to double quotes for identifiers
    $sql = str_replace('`', '"', $sql);
    
    // Remove unsupported KEY declarations (PostgreSQL handles them differently)
    $sql = preg_replace('/,\s*KEY[^,)]+/i', '', $sql);
    $sql = preg_replace('/,\s*UNIQUE KEY[^,)]+/i', '', $sql);
    $sql = preg_replace('/,\s*INDEX[^,)]+/i', '', $sql);
    
    // Clean up any double commas or trailing commas
    $sql = preg_replace('/,\s*,/', ',', $sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql);
    
    return $sql;
}

echo "PostgreSQL database setup script ready to run\n";
?>