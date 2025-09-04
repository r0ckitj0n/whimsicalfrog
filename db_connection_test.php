<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Use centralized configuration which sets $host, $db, $user, $pass, $port, $socket
require_once __DIR__ . '/api/config.php';

// Debug: show resolved DB connection parameters
global $host, $db, $user, $pass, $port, $socket;
header('Content-Type: text/plain');
echo "Resolved DB params:\n";
echo "  host:   {$host}\n";
echo "  db:     {$db}\n";
echo "  user:   {$user}\n";
echo "  port:   " . (isset($port) ? $port : '(unset)') . "\n";
echo "  socket: " . (isset($socket) && $socket ? $socket : '(none)') . "\n\n";

try {
    $pdo = Database::getInstance();
    if ($pdo) {
        echo "Database connection successful!\n";
        $stmt = $pdo->query("SHOW TABLES;");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found: " . implode(', ', $tables) . "\n";
    } else {
        echo "Failed to get Database instance.\n";
    }
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>
