<?php
// CLI script to test database connectivity
require_once __DIR__ . '/api/config.php';
try {
    $pdo = Database::getInstance();
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Connected to database: {$dbName}\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
