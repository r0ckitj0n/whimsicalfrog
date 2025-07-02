#!/usr/bin/env php
<?php
/**
 * WhimsicalFrog Database Manager
 * Comprehensive database access and management tool
 * 
 * Usage:
 * php db_manager.php --help
 * php db_manager.php --env=local --action=status
 * php db_manager.php --env=live --action=query --sql="SELECT COUNT(*) FROM items"
 * php db_manager.php --action=sync --from=local --to=live
 */

// Include configuration
require_once __DIR__ . '/includes/database.php';

class DatabaseManager {
    private $localConfig = [
        'host' => 'localhost',
        'db' => 'whimsicalfrog',
        'user' => 'root',
        'pass' => 'Palz2516'
    ];
    
    private $liveConfig = [
        'host' => 'db5017975223.hosting-data.io',
        'db' => 'dbs14295502',
        'user' => 'dbu2826619',
        'pass' => 'Palz2516!'
    ];
    
    private $connections = [];
    
    public function __construct() {
        echo "🐸 WhimsicalFrog Database Manager\n";
        echo "================================\n\n";
    }
    
    /**
     * Get database connection
     */
    public function getConnection($env = 'local') {
        if (isset($this->connections[$env])) {
            return $this->connections[$env];
        }
        
        $config = $env === 'live' ? $this->liveConfig : $this->localConfig;
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
            $this->connections[$env] = $pdo;
            
            echo "✅ Connected to {$env} database ({$config['host']}/{$config['db']})\n";
            return $pdo;
            
        } catch (PDOException $e) {
            echo "❌ Failed to connect to {$env} database: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    /**
     * Show database status
     */
    public function showStatus($env = 'local') {
        $pdo = $this->getConnection($env);
        if (!$pdo) return;
        
        $config = $env === 'live' ? $this->liveConfig : $this->localConfig;
        
        echo "\n📊 Database Status ({$env})\n";
        echo "========================\n";
        echo "Host: {$config['host']}\n";
        echo "Database: {$config['db']}\n";
        echo "User: {$config['user']}\n";
        
        try {
            // Get MySQL version
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch()['version'];
            echo "MySQL Version: {$version}\n";
            
            // Get table count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '{$config['db']}'");
            $tableCount = $stmt->fetch()['count'];
            echo "Tables: {$tableCount}\n";
            
            // Get database size
            $stmt = $pdo->query("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = '{$config['db']}'
            ");
            $size = $stmt->fetch()['size_mb'];
            echo "Size: {$size} MB\n";
            
            // List tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "\nTables:\n";
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
                $count = $stmt->fetch()['count'];
                echo "  - {$table} ({$count} rows)\n";
            }
            
        } catch (Exception $e) {
            echo "Error getting status: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Execute SQL query
     */
    public function executeQuery($env, $sql) {
        $pdo = $this->getConnection($env);
        if (!$pdo) return;
        
        echo "\n🔍 Executing Query ({$env})\n";
        echo "========================\n";
        echo "SQL: {$sql}\n\n";
        
        try {
            $stmt = $pdo->query($sql);
            
            if ($stmt->columnCount() > 0) {
                // SELECT query - show results
                $results = $stmt->fetchAll();
                
                if (empty($results)) {
                    echo "No results found.\n";
                    return;
                }
                
                // Show column headers
                $columns = array_keys($results[0]);
                $this->printTable($columns, $results);
                
            } else {
                // INSERT/UPDATE/DELETE - show affected rows
                echo "✅ Query executed successfully.\n";
                echo "Affected rows: " . $stmt->rowCount() . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Query failed: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Print table with results
     */
    private function printTable($columns, $data) {
        // Calculate column widths
        $widths = [];
        foreach ($columns as $col) {
            $widths[$col] = strlen($col);
        }
        
        foreach ($data as $row) {
            foreach ($row as $col => $value) {
                $widths[$col] = max($widths[$col], strlen((string)$value));
            }
        }
        
        // Print header
        echo "+";
        foreach ($columns as $col) {
            echo str_repeat("-", $widths[$col] + 2) . "+";
        }
        echo "\n|";
        
        foreach ($columns as $col) {
            echo " " . str_pad($col, $widths[$col]) . " |";
        }
        echo "\n+";
        
        foreach ($columns as $col) {
            echo str_repeat("-", $widths[$col] + 2) . "+";
        }
        echo "\n";
        
        // Print data
        foreach ($data as $row) {
            echo "|";
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                echo " " . str_pad((string)$value, $widths[$col]) . " |";
            }
            echo "\n";
        }
        
        echo "+";
        foreach ($columns as $col) {
            echo str_repeat("-", $widths[$col] + 2) . "+";
        }
        echo "\n";
        
        echo "\nTotal rows: " . count($data) . "\n";
    }
    
    /**
     * Backup database
     */
    public function backup($env = 'local', $outputFile = null) {
        $config = $env === 'live' ? $this->liveConfig : $this->localConfig;
        
        if (!$outputFile) {
            $outputFile = "backup_{$env}_" . date('Y-m-d_H-i-s') . ".sql";
        }
        
        echo "\n💾 Creating Database Backup ({$env})\n";
        echo "==================================\n";
        echo "Output file: {$outputFile}\n";
        
        $command = sprintf(
            "mysqldump -h%s -u%s -p%s %s > %s",
            escapeshellarg($config['host']),
            escapeshellarg($config['user']),
            escapeshellarg($config['pass']),
            escapeshellarg($config['db']),
            escapeshellarg($outputFile)
        );
        
        $result = shell_exec($command . " 2>&1");
        
        if (file_exists($outputFile) && filesize($outputFile) > 0) {
            $size = round(filesize($outputFile) / 1024 / 1024, 2);
            echo "✅ Backup created successfully ({$size} MB)\n";
        } else {
            echo "❌ Backup failed\n";
            if ($result) {
                echo "Error: {$result}\n";
            }
        }
    }
    
    /**
     * Restore database from backup
     */
    public function restore($env, $backupFile) {
        if (!file_exists($backupFile)) {
            echo "❌ Backup file not found: {$backupFile}\n";
            return;
        }
        
        $config = $env === 'live' ? $this->liveConfig : $this->localConfig;
        
        echo "\n🔄 Restoring Database ({$env})\n";
        echo "============================\n";
        echo "Backup file: {$backupFile}\n";
        echo "⚠️  This will overwrite the existing database!\n";
        
        echo "Continue? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 'y') {
            echo "Restore cancelled.\n";
            return;
        }
        
        $command = sprintf(
            "mysql -h%s -u%s -p%s %s < %s",
            escapeshellarg($config['host']),
            escapeshellarg($config['user']),
            escapeshellarg($config['pass']),
            escapeshellarg($config['db']),
            escapeshellarg($backupFile)
        );
        
        $result = shell_exec($command . " 2>&1");
        
        if (empty($result)) {
            echo "✅ Database restored successfully\n";
        } else {
            echo "❌ Restore failed\n";
            echo "Error: {$result}\n";
        }
    }
    
    /**
     * Sync databases
     */
    public function sync($from = 'local', $to = 'live') {
        echo "\n🔄 Database Sync ({$from} → {$to})\n";
        echo "==============================\n";
        echo "⚠️  This will overwrite the {$to} database with {$from} data!\n";
        
        echo "Continue? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 'y') {
            echo "Sync cancelled.\n";
            return;
        }
        
        // Create backup first
        $backupFile = "sync_backup_{$from}_to_{$to}_" . date('Y-m-d_H-i-s') . ".sql";
        $this->backup($from, $backupFile);
        
        // Restore to target
        $this->restore($to, $backupFile);
        
        echo "🗑️  Clean up backup file? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) === 'y') {
            unlink($backupFile);
            echo "Backup file deleted.\n";
        }
    }
    
    /**
     * Interactive mode
     */
    public function interactive() {
        echo "\n🔧 Interactive Database Manager\n";
        echo "==============================\n";
        echo "Commands:\n";
        echo "  status [local|live]     - Show database status\n";
        echo "  query [local|live]      - Execute SQL query\n";
        echo "  tables [local|live]     - List all tables\n";
        echo "  desc [table] [local|live] - Describe table structure\n";
        echo "  exit                    - Exit interactive mode\n\n";
        
        while (true) {
            echo "db> ";
            $handle = fopen("php://stdin", "r");
            $input = trim(fgets($handle));
            fclose($handle);
            
            if ($input === 'exit') {
                break;
            }
            
            $parts = explode(' ', $input);
            $command = $parts[0];
            
            switch ($command) {
                case 'status':
                    $env = isset($parts[1]) ? $parts[1] : 'local';
                    $this->showStatus($env);
                    break;
                    
                case 'query':
                    $env = isset($parts[1]) ? $parts[1] : 'local';
                    echo "SQL: ";
                    $handle = fopen("php://stdin", "r");
                    $sql = trim(fgets($handle));
                    fclose($handle);
                    
                    if ($sql) {
                        $this->executeQuery($env, $sql);
                    }
                    break;
                    
                case 'tables':
                    $env = isset($parts[1]) ? $parts[1] : 'local';
                    $this->executeQuery($env, "SHOW TABLES");
                    break;
                    
                case 'desc':
                    $table = isset($parts[1]) ? $parts[1] : '';
                    $env = isset($parts[2]) ? $parts[2] : 'local';
                    if ($table) {
                        $this->executeQuery($env, "DESCRIBE `{$table}`");
                    } else {
                        echo "Table name required\n";
                    }
                    break;
                    
                default:
                    echo "Unknown command: {$command}\n";
            }
            
            echo "\n";
        }
    }
    
    /**
     * Show help
     */
    public function showHelp() {
        echo "Usage: php db_manager.php [options]\n\n";
        echo "Options:\n";
        echo "  --help                          Show this help\n";
        echo "  --env=[local|live]             Set environment\n";
        echo "  --action=[status|query]        Action to perform\n";
        echo "  --sql=\"SQL QUERY\"              SQL to execute (with --action=query)\n";
        echo "  --interactive                  Start interactive mode\n\n";
        echo "Examples:\n";
        echo "  php db_manager.php --action=status --env=local\n";
        echo "  php db_manager.php --action=query --env=live --sql=\"SELECT COUNT(*) FROM items\"\n";
        echo "  php db_manager.php --interactive\n";
    }
}

// Parse command line arguments
$options = getopt('', [
    'help',
    'env:',
    'action:',
    'sql:',
    'interactive'
]);

$manager = new DatabaseManager();

if (isset($options['help'])) {
    $manager->showHelp();
    exit;
}

if (isset($options['interactive'])) {
    $manager->interactive();
    exit;
}

$env = $options['env'] ?? 'local';
$action = $options['action'] ?? 'status';

switch ($action) {
    case 'status':
        $manager->showStatus($env);
        break;
        
    case 'query':
        $sql = $options['sql'] ?? '';
        if (empty($sql)) {
            echo "❌ SQL query required. Use --sql=\"YOUR QUERY\"\n";
            exit(1);
        }
        $manager->executeQuery($env, $sql);
        break;
        
    case 'backup':
        $manager->backup($env);
        break;
        
    case 'restore':
        $file = $options['file'] ?? '';
        if (empty($file)) {
            echo "❌ Backup file required. Use --file=backup.sql\n";
            exit(1);
        }
        $manager->restore($env, $file);
        break;
        
    case 'sync':
        $from = $options['from'] ?? 'local';
        $to = $options['to'] ?? 'live';
        $manager->sync($from, $to);
        break;
        
    default:
        echo "❌ Unknown action: {$action}\n";
        $manager->showHelp();
        exit(1);
} 