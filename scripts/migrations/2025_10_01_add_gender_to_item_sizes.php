<?php
// Migration: Add gender column to item_sizes (nullable)
// Usage: php scripts/migrations/2025_10_01_add_gender_to_item_sizes.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    Database::getInstance();

    // Detect if column already exists (MySQL)
    $dbNameRow = Database::queryOne('SELECT DATABASE() as db');
    $dbName = $dbNameRow && isset($dbNameRow['db']) ? $dbNameRow['db'] : null;

    $hasColumn = false;
    if ($dbName) {
        $col = Database::queryOne(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$dbName, 'item_sizes', 'gender']
        );
        $hasColumn = !empty($col);
    } else {
        // Fallback: try describing the table
        try {
            $cols = Database::queryAll('DESCRIBE item_sizes');
            foreach ($cols as $c) {
                if (strtolower($c['Field'] ?? '') === 'gender') { $hasColumn = true; break; }
            }
        } catch (Exception $e) {
            // Ignore; will attempt ALTER and let it fail if unsupported
        }
    }

    if ($hasColumn) {
        echo "Gender column already exists on item_sizes.\n";
        exit(0);
    }

    // Add the column (nullable) and an index for faster queries
    Database::execute('ALTER TABLE item_sizes ADD COLUMN gender VARCHAR(32) NULL AFTER color_id');
    try {
        Database::execute('CREATE INDEX idx_item_sizes_itemsku_gender ON item_sizes (item_sku, gender)');
    } catch (Exception $e) {
        // Index may already exist; ignore
    }

    echo "Migration complete: added item_sizes.gender (nullable) and index (item_sku, gender).\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
