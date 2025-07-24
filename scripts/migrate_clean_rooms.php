<?php
/**
 * Migration runner for clean rooms SQL.
 * Uses PDO credentials from config.php to avoid CLI auth issues.
 */
require_once __DIR__ . '/../api/config.php';

try {
    $pdo = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/../db/migrate_clean_rooms.sql');
    $pdo->exec($sql);
    echo "Migration 'migrate_clean_rooms.sql' applied successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
