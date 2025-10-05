<?php
// Migration: Create genders reference table and add FKs from item_sizes.gender and item_genders.gender
// - Creates table `genders` with canonical values
// - Normalizes existing gender strings to canonical forms where possible
// - Adds FK constraints:
//     item_sizes.gender  -> genders.gender (ON DELETE SET NULL ON UPDATE CASCADE)
//     item_genders.gender -> genders.gender (ON DELETE CASCADE ON UPDATE CASCADE)
// Usage: php scripts/migrations/2025_10_02_create_genders_reference_and_fk.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    Database::getInstance();

    // 1) Create genders table if not exists
    Database::execute("CREATE TABLE IF NOT EXISTS genders (
        gender VARCHAR(32) NOT NULL,
        display_name VARCHAR(64) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (gender)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Ensured table genders exists\n";

    // 2) Seed canonical genders (idempotent)
    $canonical = ['Unisex','Men','Women','Boys','Girls','Baby'];
    foreach ($canonical as $g) {
        Database::execute("INSERT IGNORE INTO genders (gender, display_name, is_active) VALUES (?, ?, 1)", [$g, $g]);
    }
    echo "Seeded canonical genders\n";

    // 3) Normalize existing data to canonical forms (common case/alias fixes)
    $map = [
        'unisex' => 'Unisex', 'uni' => 'Unisex',
        'men' => 'Men', 'male' => 'Men', 'm' => 'Men',
        'women' => 'Women', 'female' => 'Women', 'w' => 'Women', 'ladies' => 'Women',
        'boys' => 'Boys', 'boy' => 'Boys',
        'girls' => 'Girls', 'girl' => 'Girls',
        'baby' => 'Baby', 'infant' => 'Baby'
    ];

    foreach ($map as $from => $to) {
        // item_sizes
        Database::execute("UPDATE item_sizes SET gender = ? WHERE gender IS NOT NULL AND TRIM(LOWER(gender)) = ?", [$to, $from]);
        // item_genders
        Database::execute("UPDATE item_genders SET gender = ? WHERE gender IS NOT NULL AND TRIM(LOWER(gender)) = ?", [$to, $from]);
    }
    echo "Normalized existing gender values where applicable\n";

    // 4) Set unknown non-canonical genders to NULL to avoid FK failures
    // item_sizes
    Database::execute("UPDATE item_sizes s 
        LEFT JOIN genders g ON s.gender = g.gender 
        SET s.gender = NULL 
        WHERE s.gender IS NOT NULL AND g.gender IS NULL");
    // item_genders: remove unknowns entirely
    Database::execute("DELETE ig FROM item_genders ig 
        LEFT JOIN genders g ON ig.gender = g.gender 
        WHERE ig.gender IS NOT NULL AND g.gender IS NULL");
    echo "Cleaned non-canonical gender values\n";

    // 5) Add indexes to speed up validation (no-op if exist)
    try { Database::execute("ALTER TABLE item_sizes ADD INDEX idx_item_sizes_gender (gender)"); } catch (Exception $e) { /* ignore if exists */ }
    try { Database::execute("ALTER TABLE item_genders ADD INDEX idx_item_genders_gender (gender)"); } catch (Exception $e) { /* ignore if exists */ }

    // 6) Add foreign keys (drop if already exist then add)
    // Detect current schema to be safe
    $dbNameRow = Database::queryOne('SELECT DATABASE() as db');
    $dbName = $dbNameRow && isset($dbNameRow['db']) ? $dbNameRow['db'] : null;

    $hasFkSizes = false;
    $hasFkItemGenders = false;
    if ($dbName) {
        $rows = Database::queryAll(
            'SELECT CONSTRAINT_NAME, TABLE_NAME 
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?'
            , [$dbName, 'genders', 'gender']
        );
        foreach ($rows as $r) {
            if ($r['TABLE_NAME'] === 'item_sizes' && strcasecmp($r['CONSTRAINT_NAME'], 'fk_item_sizes_gender') === 0) $hasFkSizes = true;
            if ($r['TABLE_NAME'] === 'item_genders' && strcasecmp($r['CONSTRAINT_NAME'], 'fk_item_genders_gender') === 0) $hasFkItemGenders = true;
        }
    }

    if (!$hasFkSizes) {
        // Ensure a key exists on item_sizes.gender for FK
        try { Database::execute("ALTER TABLE item_sizes ADD INDEX idx_item_sizes_gender2 (gender)"); } catch (Exception $e) { }
        Database::execute("ALTER TABLE item_sizes 
            ADD CONSTRAINT fk_item_sizes_gender 
            FOREIGN KEY (gender) REFERENCES genders(gender)
            ON DELETE SET NULL ON UPDATE CASCADE");
        echo "Added FK fk_item_sizes_gender -> genders(gender)\n";
    } else {
        echo "FK fk_item_sizes_gender already exists (ok).\n";
    }

    if (!$hasFkItemGenders) {
        try { Database::execute("ALTER TABLE item_genders ADD INDEX idx_item_genders_gender2 (gender)"); } catch (Exception $e) { }
        Database::execute("ALTER TABLE item_genders 
            ADD CONSTRAINT fk_item_genders_gender 
            FOREIGN KEY (gender) REFERENCES genders(gender)
            ON DELETE CASCADE ON UPDATE CASCADE");
        echo "Added FK fk_item_genders_gender -> genders(gender)\n";
    } else {
        echo "FK fk_item_genders_gender already exists (ok).\n";
    }

    echo "Migration complete.\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
