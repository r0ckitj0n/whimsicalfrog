<?php
// Migration: Add FKs and indexes for item option tables
// - Adds FKs:
//     item_colors.item_sku -> items.sku (ON DELETE CASCADE)
//     item_sizes.item_sku -> items.sku (ON DELETE CASCADE)
//     item_sizes.color_id -> item_colors.id (ON DELETE CASCADE)
//     item_genders.item_sku -> items.sku (ON DELETE CASCADE)
// - Adds helpful indexes for read paths
// Usage: php scripts/migrations/2025_10_02_add_item_option_fks_and_indexes.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    Database::getInstance();

    $dbNameRow = Database::queryOne('SELECT DATABASE() as db');
    $dbName = $dbNameRow && isset($dbNameRow['db']) ? $dbNameRow['db'] : null;

    // Add indexes (ignore if exist)
    $indexStatements = [
        "ALTER TABLE item_colors ADD INDEX idx_item_colors_item (item_sku)",
        "ALTER TABLE item_colors ADD INDEX idx_item_colors_active (item_sku, is_active)",
        "ALTER TABLE item_sizes ADD INDEX idx_item_sizes_item (item_sku)",
        "ALTER TABLE item_sizes ADD INDEX idx_item_sizes_item_active (item_sku, is_active)",
        "ALTER TABLE item_sizes ADD INDEX idx_item_sizes_color (color_id)",
        "ALTER TABLE item_sizes ADD INDEX idx_item_sizes_lookup (item_sku, color_id, size_code, gender)",
        "ALTER TABLE item_genders ADD INDEX idx_item_genders_item (item_sku)",
        "ALTER TABLE item_genders ADD INDEX idx_item_genders_item_gender (item_sku, gender)"
    ];

    foreach ($indexStatements as $stmt) {
        try { Database::execute($stmt); } catch (Exception $e) { /* ignore if exists */ }
    }

    // Add FKs if missing
    if ($dbName) {
        $existing = Database::queryAll(
            'SELECT CONSTRAINT_NAME, TABLE_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?',
            [$dbName]
        );
        $has = function($table, $name) use ($existing) {
            foreach ($existing as $r) {
                if ($r['TABLE_NAME'] === $table && strcasecmp($r['CONSTRAINT_NAME'], $name) === 0) return true;
            }
            return false;
        };

        if (!$has('item_colors', 'fk_item_colors_item')) {
            Database::execute("ALTER TABLE item_colors 
                ADD CONSTRAINT fk_item_colors_item 
                FOREIGN KEY (item_sku) REFERENCES items(sku)
                ON DELETE CASCADE ON UPDATE CASCADE");
            echo "Added FK fk_item_colors_item -> items(sku)\n";
        }
        if (!$has('item_sizes', 'fk_item_sizes_item')) {
            Database::execute("ALTER TABLE item_sizes 
                ADD CONSTRAINT fk_item_sizes_item 
                FOREIGN KEY (item_sku) REFERENCES items(sku)
                ON DELETE CASCADE ON UPDATE CASCADE");
            echo "Added FK fk_item_sizes_item -> items(sku)\n";
        }
        if (!$has('item_sizes', 'fk_item_sizes_color')) {
            Database::execute("ALTER TABLE item_sizes 
                ADD CONSTRAINT fk_item_sizes_color 
                FOREIGN KEY (color_id) REFERENCES item_colors(id)
                ON DELETE CASCADE ON UPDATE CASCADE");
            echo "Added FK fk_item_sizes_color -> item_colors(id)\n";
        }
        if (!$has('item_genders', 'fk_item_genders_item')) {
            Database::execute("ALTER TABLE item_genders 
                ADD CONSTRAINT fk_item_genders_item 
                FOREIGN KEY (item_sku) REFERENCES items(sku)
                ON DELETE CASCADE ON UPDATE CASCADE");
            echo "Added FK fk_item_genders_item -> items(sku)\n";
        }
    }

    echo "Migration complete.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
