<?php

// scripts/db/bootstrap_dev_db.php
// Idempotent local database bootstrap.
// Creates the core schema and seeds a default admin user by delegating to
// DatabaseSchemaHelper. Safe to run repeatedly: existing tables/records are skipped.
//
// Usage: php scripts/db/bootstrap_dev_db.php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/api/config.php';
require_once $root . '/includes/database/helpers/DatabaseSchemaHelper.php';

try {
    $pdo = Database::getInstance();
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    fwrite(STDOUT, "[bootstrap] Connected to database: {$dbName}\n");

    $result = DatabaseSchemaHelper::initializeDatabase();

    fwrite(STDOUT, sprintf(
        "[bootstrap] tables_created=%d tables_skipped=%d default_records=%d (%s)\n",
        $result['tables_created'] ?? 0,
        $result['tables_skipped'] ?? 0,
        $result['default_records'] ?? 0,
        $result['execution_time'] ?? 'n/a'
    ));

    foreach (($result['warnings'] ?? []) as $warning) {
        fwrite(STDERR, "[bootstrap][warn] {$warning}\n");
    }

    fwrite(STDOUT, "[bootstrap] Done.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[bootstrap] Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
