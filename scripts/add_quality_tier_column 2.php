<?php
// Quick migration script to add quality_tier column
// Run with: php scripts/add_quality_tier_column.php

require_once __DIR__ . '/../api/config.php';

try {
    Database::execute("ALTER TABLE items ADD COLUMN quality_tier VARCHAR(20) DEFAULT 'standard' AFTER locked_words");
    echo "SUCCESS: quality_tier column added to items table\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ALREADY EXISTS: quality_tier column already exists in items table\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}
