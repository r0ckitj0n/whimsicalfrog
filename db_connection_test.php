<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set credentials manually to bypass config file issues
global $host, $db, $user, $pass, $port, $socket;
$host = '127.0.0.1';
$db   = 'whimsical_frog'; // Assuming standard db name
$user = 'root';
$pass = 'Palz2516!';
$port = 3306;
$socket = ini_get("mysqli.default_socket");

require_once __DIR__ . '/includes/database.php';

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
