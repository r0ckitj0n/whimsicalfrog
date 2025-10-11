<?php

// scripts/dev/normalize-shop-categories.php
// Normalize categories to the 5 office categories and remap items accordingly.
// Also ensure room -> primary category mapping matches office layout.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../api/config.php';

function tableExists(string $table): bool
{
    try {
        $row = Database::queryOne("SHOW TABLES LIKE ?", [$table]);
        return is_array($row) && !empty($row);
    } catch (Throwable $e) {
        return false;
    }
}

try {
    Database::getInstance();

    if (!tableExists('categories')) {
        throw new RuntimeException('Table categories does not exist');
    }
    if (!tableExists('items')) {
        throw new RuntimeException('Table items does not exist');
    }
    if (!tableExists('room_category_assignments')) {
        throw new RuntimeException('Table room_category_assignments does not exist');
    }

    // Backup current state
    $backupDir = __DIR__ . '/../../backups/sql_migrations';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    $ts = date('Ymd_His');
    $b1 = $backupDir . "/categories_pre_normalize_{$ts}.json";
    $b2 = $backupDir . "/items_categories_pre_normalize_{$ts}.json";
    $b3 = $backupDir . "/room_category_assignments_pre_normalize_{$ts}.json";
    file_put_contents($b1, json_encode(Database::queryAll('SELECT * FROM categories ORDER BY id'), JSON_PRETTY_PRINT));
    file_put_contents($b2, json_encode(Database::queryAll('SELECT sku, name, category FROM items ORDER BY sku'), JSON_PRETTY_PRINT));
    file_put_contents($b3, json_encode(Database::queryAll('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC'), JSON_PRETTY_PRINT));

    // Target office 5 categories (IDs are desired but we will upsert by name if IDs differ)
    $targetCategories = [
        // desired_id => [name, description]
        1 => ['T-Shirts & Apparel', 'Custom t-shirts, hoodies, and apparel'],
        2 => ['Tumblers & Drinkware', 'Custom tumblers, mugs, and drinkware'],
        3 => ['Custom Artwork', 'Personalized artwork and designs'],
        4 => ['Sublimation Items', 'Sublimation printing on various items'],
        5 => ['Window Wraps', 'Custom window wraps and vehicle graphics'],
    ];

    // Map existing simple names -> target names
    $nameMap = [
        'T-Shirts' => 'T-Shirts & Apparel',
        'Tumblers' => 'Tumblers & Drinkware',
        'Artwork' => 'Custom Artwork',
        'Fluid Art' => 'Sublimation Items',
        'Decor' => 'Window Wraps',
    ];

    Database::beginTransaction();

    // Ensure categories exist/are renamed
    // Strategy: try to find by current name; if not found, try by desired id; then insert/update.
    $nameToId = [];
    foreach ($targetCategories as $desiredId => [$name, $desc]) {
        $row = Database::queryOne('SELECT id FROM categories WHERE name = ? LIMIT 1', [$name]);
        if ($row && isset($row['id'])) {
            $cid = (int)$row['id'];
            // Update description to match target
            Database::execute('UPDATE categories SET description = ? WHERE id = ?', [$desc, $cid]);
            $nameToId[$name] = $cid;
            continue;
        }
        // Not found by name: see if desired id exists; if so, update it; else insert new
        $row = Database::queryOne('SELECT id FROM categories WHERE id = ? LIMIT 1', [$desiredId]);
        if ($row && isset($row['id'])) {
            Database::execute('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$name, $desc, $desiredId]);
            $nameToId[$name] = $desiredId;
        } else {
            Database::execute('INSERT INTO categories (id, name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)', [$desiredId, $name, $desc]);
            $nameToId[$name] = $desiredId;
        }
    }

    // Remap existing items.category values
    foreach ($nameMap as $from => $to) {
        Database::execute('UPDATE items SET category = ? WHERE category = ?', [$to, $from]);
    }

    // Any items not in the five categories should be moved into a reasonable bucket.
    // Default strategy: if category not in target names, move to 'Custom Artwork' (3) for now.
    $targetNames = array_keys($nameToId);
    $placeHolder = 'Custom Artwork';
    Database::execute('UPDATE items SET category = ? WHERE category NOT IN (' . str_repeat('?,', count($targetNames) - 1) . '?)', array_merge([$placeHolder], $targetNames));

    // Enforce room -> primary category mapping (office layout)
    // Desired mapping (room_number => category name)
    $roomToCategoryName = [
        1 => 'T-Shirts & Apparel',
        2 => 'Tumblers & Drinkware',
        3 => 'Sublimation Items',
        4 => 'Custom Artwork',
        5 => 'Window Wraps',
    ];
    foreach ($roomToCategoryName as $room => $catName) {
        $catId = $nameToId[$catName] ?? null;
        if (!$catId) {
            continue;
        }
        // Set all to non-primary first
        Database::execute('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?', [$room]);
        $row = Database::queryOne('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1', [$room, $catId]);
        $id = $row['id'] ?? null;
        if ($id) {
            Database::execute('UPDATE room_category_assignments SET is_primary = 1 WHERE id = ?', [(int)$id]);
        } else {
            Database::execute('INSERT INTO room_category_assignments (room_number, category_id, is_primary) VALUES (?, ?, 1)', [$room, $catId]);
        }
    }

    Database::commit();

    echo json_encode([
        'ok' => true,
        'backups' => [$b1, $b2, $b3],
        'categories' => Database::queryAll('SELECT * FROM categories ORDER BY id'),
        'room_primary' => Database::queryAll('SELECT room_number, category_id, is_primary FROM room_category_assignments WHERE is_primary = 1 ORDER BY room_number'),
        'items_summary' => Database::queryAll('SELECT category, COUNT(*) as cnt FROM items GROUP BY category ORDER BY cnt DESC'),
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    try {
        if (Database::inTransaction()) {
            Database::rollBack();
        }
    } catch (Throwable $t) {
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
