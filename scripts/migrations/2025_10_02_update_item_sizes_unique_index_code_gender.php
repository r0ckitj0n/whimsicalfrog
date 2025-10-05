<?php
// Migration: Update unique index on item_sizes to use size_code and gender
// Drops existing unique index (item_sku, color_id, size_name, gender) if present and creates
// a new one (item_sku, color_id, size_code, gender)
// Usage: php scripts/migrations/2025_10_02_update_item_sizes_unique_index_code_gender.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    Database::getInstance();

    // Detect existing indexes on item_sizes
    $dbNameRow = Database::queryOne('SELECT DATABASE() as db');
    $dbName = $dbNameRow && isset($dbNameRow['db']) ? $dbNameRow['db'] : null;

    $hasOld = false; // unique_item_color_size_gender (by size_name)
    $hasTarget = false; // unique_item_color_sizecode_gender (by size_code)

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
            if (strcasecmp($name, 'unique_item_color_size_gender') === 0) $hasOld = true;
            if (strcasecmp($name, 'unique_item_color_sizecode_gender') === 0) $hasTarget = true;
        }
    }

    if ($hasOld) {
        Database::execute('ALTER TABLE item_sizes DROP INDEX unique_item_color_size_gender');
        echo "Dropped index unique_item_color_size_gender (size_name-based)\n";
    } else {
        echo "Index unique_item_color_size_gender not found (ok).\n";
    }

    if (!$hasTarget) {
        Database::execute('ALTER TABLE item_sizes ADD UNIQUE KEY unique_item_color_sizecode_gender (item_sku, color_id, size_code, gender)');
        echo "Created index unique_item_color_sizecode_gender (item_sku, color_id, size_code, gender)\n";
    } else {
        echo "Target index unique_item_color_sizecode_gender already exists.\n";
    }

    echo "Migration complete.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
