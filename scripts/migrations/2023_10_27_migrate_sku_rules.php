<?php

// scripts/migrations/2023_10_27_migrate_sku_rules.php
require_once dirname(__DIR__, 2) . '/api/config.php';

echo "Starting SKU rule migration...\n";

$hardcoded_rules = [
    'T-Shirts' => 'TS',
    'Tumblers' => 'TU',
    'Artwork' => 'AR',
    'Sublimation' => 'SU',
    'WindowWraps' => 'WW'
];

try {
    Database::getInstance();
    $existing_rules_stmt = Database::queryAll("SELECT category_name FROM sku_rules");
    $existing_rules = array_column($existing_rules_stmt, 'category_name');

    $rules_to_add = array_diff_key($hardcoded_rules, array_flip($existing_rules));

    if (empty($rules_to_add)) {
        echo "All hardcoded SKU rules already exist in the database. No migration needed.\n";
        exit(0);
    }

    Database::beginTransaction();
    foreach ($rules_to_add as $category => $prefix) {
        Database::execute("INSERT INTO sku_rules (category_name, sku_prefix) VALUES (?, ?)", [$category, $prefix]);
        echo "Migrated: {$category} -> {$prefix}\n";
    }
    Database::commit();
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    Database::rollBack();
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
