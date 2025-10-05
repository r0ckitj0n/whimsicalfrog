<?php
// Migration: Update unique index on item_sizes to include gender
// Drops existing unique index (item_sku, color_id, size_name) and creates
// a new one (item_sku, color_id, size_name, gender)
// Usage: php scripts/migrations/2025_10_01_update_item_sizes_unique_index_gender.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    Database::getInstance();

    // Detect existing indexes on item_sizes
    $dbNameRow = Database::queryOne('SELECT DATABASE() as db');
    $dbName = $dbNameRow && isset($dbNameRow['db']) ? $dbNameRow['db'] : null;

    $hasOld = false;
    $hasNew = false;
    if ($dbName) {
        $rows = Database::queryAll(
            'SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as cols
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             GROUP BY INDEX_NAME, NON_UNIQUE',
            [$dbName, 'item_sizes']
        );
        foreach ($rows as $r) {
            $name = $r['INDEX_NAME'];
            $cols = $r['cols'];
            if (strcasecmp($name, 'unique_item_color_size') === 0) $hasOld = true;
            if (strcasecmp($name, 'unique_item_color_size_gender') === 0) $hasNew = true;
        }
    }

    if ($hasOld) {
        Database::execute('ALTER TABLE item_sizes DROP INDEX unique_item_color_size');
        echo "Dropped index unique_item_color_size\n";
    } else {
        echo "Old index unique_item_color_size not found (ok).\n";
    }

    if (!$hasNew) {
        Database::execute('ALTER TABLE item_sizes ADD UNIQUE KEY unique_item_color_size_gender (item_sku, color_id, size_name, gender)');
        echo "Created index unique_item_color_size_gender (item_sku, color_id, size_name, gender)\n";
    } else {
        echo "New index unique_item_color_size_gender already exists.\n";
    }

    echo "Migration complete.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
