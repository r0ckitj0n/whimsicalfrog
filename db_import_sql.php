<?php
set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------------------------------------------------------
// Simple token gate for deployments
// ------------------------------------------------------------------
$requiredToken = 'whfdeploytoken'; // must match value in deploy_full.sh
if (php_sapi_name() !== 'cli') { // if called via web, enforce token
    $supplied = $_GET['token'] ?? '';
    if ($supplied !== $requiredToken) {
        http_response_code(403);
        exit("Invalid or missing token\n");
    }
}

// Load DB credentials
require_once __DIR__ . '/api/config.php';

header('Content-Type: text/plain');

// Allow custom filename via GET parameter
$customFile = $_GET['file'] ?? 'whimsicalfrog_sync.sql';
$sqlFile = __DIR__ . '/' . basename($customFile); // basename for security
if (!file_exists($sqlFile)) {
    exit("SQL file not found: $sqlFile\n");
}

echo "\n=== WhimsicalFrog SQL Import Utility ===\n";

echo "Connecting to database ... \n";
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connection successful.\n";
} catch (PDOException $e) {
    exit("Connection failed: " . $e->getMessage() . "\n");
}

// Disable foreign key checks to allow dropping and adding tables freely
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

echo "Importing SQL from " . basename($sqlFile) . " ...\n";
$handle = fopen($sqlFile, 'r');
$query  = '';
$executed = 0;
$skipped  = 0;

while (($line = fgets($handle)) !== false) {
    $trim = trim($line);
    // Skip comments and blank lines
    if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*')) {
        $skipped++;
        continue;
    }
    $query .= $line;
    if (str_ends_with(trim($line), ';')) {
        // Execute the collected statement
        try {
            $pdo->exec($query);
            $executed++;
        } catch (PDOException $e) {
            echo "Error executing query: " . $e->getMessage() . "\nStatement:\n$query\n---\n";
        }
        $query = '';
    }
}

fclose($handle);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\nImport complete. Statements executed: $executed, lines skipped as comments/blank: $skipped.\n";

// Optionally delete the SQL file after successful import to keep the web root clean
if (file_exists($sqlFile)) {
    unlink($sqlFile);
    echo "SQL file deleted from server.\n";
}
?> 