<?php
// SQLite-compatible migration: Create genders reference table and seed values
// Note: Adding FKs post-hoc in SQLite would require table rebuilds; this script only creates and seeds the reference table.
// Usage: php scripts/migrations/2025_10_02_sqlite_create_genders_reference.php

require_once __DIR__ . '/../../api/config.php';

try {
    $pdo = Database::getInstance();
    // Ensure foreign_keys PRAGMA is enabled for future operations
    @Database::execute('PRAGMA foreign_keys = ON');

    Database::execute("CREATE TABLE IF NOT EXISTS genders (
        gender TEXT PRIMARY KEY,
        display_name TEXT,
        is_active INTEGER NOT NULL DEFAULT 1
    )");

    $canonical = ['Unisex','Men','Women','Boys','Girls','Baby'];
    foreach ($canonical as $g) {
        // INSERT OR IGNORE is SQLite friendly
        Database::execute("INSERT OR IGNORE INTO genders (gender, display_name, is_active) VALUES (?, ?, 1)", [$g, $g]);
    }

    echo "SQLite genders table ensured and seeded.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
