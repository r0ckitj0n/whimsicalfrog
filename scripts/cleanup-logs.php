<?php
/**
 * Log Cleanup Script
 * 
 * Cleans up old log files and database logs according to retention policies
 * Can be run manually or via cron job
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/logging_config.php';

echo "Starting log cleanup process...\n";

try {
    // Initialize logging
    $config = LoggingConfig::getCompleteConfig();
    
    // Clean up file logs
    echo "Cleaning up file logs...\n";
    $fileConfig = $config['file_logging'];
    
    foreach ($fileConfig['files'] as $logType => $logFile) {
        if (file_exists($logFile)) {
            echo "  Checking {$logType} log: {$logFile}\n";
            LoggingConfig::rotateLogFile($logFile);
            
            $size = filesize($logFile);
            $sizeKB = round($size / 1024, 2);
            echo "    Current size: {$sizeKB} KB\n";
        }
    }
    
    // Clean up database logs
    echo "\nCleaning up database logs...\n";
    LoggingConfig::cleanupDatabaseLogs();
    
    // Get database log statistics
    $pdo = Database::getInstance();
    $dbConfig = $config['database_logging'];
    
    foreach ($dbConfig['tables'] as $tableName => $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "  {$tableName}: {$result['count']} records\n";
        } catch (Exception $e) {
            echo "  {$tableName}: Error getting count - {$e->getMessage()}\n";
        }
    }
    
    // Clean up old log files in logs directory
    echo "\nCleaning up old rotated log files...\n";
    $logsDir = $fileConfig['directory'];
    $maxFiles = $fileConfig['max_files'];
    
    if (is_dir($logsDir)) {
        $files = glob($logsDir . '/*.log.*');
        foreach ($files as $file) {
            $basename = preg_replace('/\.\d+$/', '', $file);
            $rotationNumber = (int) substr($file, strrpos($file, '.') + 1);
            
            if ($rotationNumber > $maxFiles) {
                echo "  Deleting old rotated file: " . basename($file) . "\n";
                unlink($file);
            }
        }
    }
    
    // Display disk usage
    echo "\nDisk usage for logs directory:\n";
    if (is_dir($logsDir)) {
        $totalSize = 0;
        $files = glob($logsDir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size = filesize($file);
                $totalSize += $size;
                $sizeKB = round($size / 1024, 2);
                echo "  " . basename($file) . ": {$sizeKB} KB\n";
            }
        }
        
        $totalSizeMB = round($totalSize / (1024 * 1024), 2);
        echo "  Total: {$totalSizeMB} MB\n";
    }
    
    echo "\nLog cleanup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error during log cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
