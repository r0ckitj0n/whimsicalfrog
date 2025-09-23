<?php

// scripts/dev/rename-categories.php
// Force-rename categories to office 5 display names.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../api/config.php';

try {
    Database::getInstance();

    $map = [
        'T-Shirts' => 'T-Shirts & Apparel',
        'Tumblers' => 'Tumblers & Drinkware',
        'Artwork' => 'Custom Artwork',
        'Fluid Art' => 'Sublimation Items',
        'Decor' => 'Window Wraps',
    ];

    Database::beginTransaction();
    $changes = [];
    foreach ($map as $from => $to) {
        $affected = Database::execute('UPDATE categories SET name = ? WHERE name = ?', [$to, $from]);
        if ($affected > 0) {
            $changes[] = [ 'from' => $from, 'to' => $to, 'rows' => $affected ];
        }
    }
    Database::commit();

    echo json_encode([
        'ok' => true,
        'changes' => $changes,
        'categories' => Database::queryAll('SELECT id, name FROM categories ORDER BY id')
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    try {
        if (Database::inTransaction()) {
            Database::rollBack();
        }
    } catch (Throwable $t) {
    }
    http_response_code(500);
    echo json_encode([ 'ok' => false, 'error' => $e->getMessage() ]);
}
