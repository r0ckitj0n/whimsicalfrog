<?php
// scripts/dev/fix-room-category-assignments.php
// Backs up and fixes primary category assignments for rooms 1..5.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../api/config.php';
    Database::getInstance();

    // Backup current assignments and categories
    $backupDir = realpath(__DIR__ . '/../../backups/sql_migrations');
    if ($backupDir === false) {
        $backupDir = __DIR__ . '/../../backups/sql_migrations';
        @mkdir($backupDir, 0775, true);
    }
    $ts = date('Ymd_His');
    $rcaPath = rtrim($backupDir, '/')."/room_category_assignments_backup_{$ts}.json";
    $catPath = rtrim($backupDir, '/')."/categories_backup_{$ts}.json";

    $allRca = Database::queryAll('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC');
    $allCats = Database::queryAll('SELECT * FROM categories ORDER BY id');
    file_put_contents($rcaPath, json_encode($allRca, JSON_PRETTY_PRINT));
    file_put_contents($catPath, json_encode($allCats, JSON_PRETTY_PRINT));

    // Helper: resolve category by aliases
    $resolveCategory = function(array $aliases): ?int {
        // Try exact name match first
        foreach ($aliases as $alias) {
            $row = Database::queryOne('SELECT id FROM categories WHERE name = ? LIMIT 1', [$alias]);
            $id = $row['id'] ?? null;
            if ($id) return (int)$id;
        }
        // Try LIKE match
        foreach ($aliases as $alias) {
            $row = Database::queryOne('SELECT id FROM categories WHERE name LIKE ? ORDER BY LENGTH(name) ASC LIMIT 1', ['%'.$alias.'%']);
            $id = $row['id'] ?? null;
            if ($id) return (int)$id;
        }
        return null;
    };

    // Target mapping: room_number => category aliases (in priority order)
    $targets = [
        1 => ['T-Shirts & Apparel','T-Shirts','Shirts','Apparel','TShirts'],
        2 => ['Tumblers','Drinkware','Cups','Mugs'],
        3 => ['Sublimation','Sublimation Blanks','Heat Press','Sublimation Items'],
        4 => ['Custom Artwork','Artwork','Art','Custom Art'],
        5 => ['Window Wraps','Windowwraps','Window Graphics','Window Wrap'],
    ];

    Database::beginTransaction();
    $result = [];

    foreach ($targets as $roomNumber => $aliases) {
        $catId = $resolveCategory($aliases);
        if (!$catId) {
            $result[] = [
                'room_number' => $roomNumber,
                'status' => 'skipped',
                'reason' => 'category_not_found',
                'aliases' => $aliases,
            ];
            continue;
        }

        // Ensure only one primary per room: set others to 0
        Database::execute('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?', [$roomNumber]);

        // If assignment exists for this category, set primary; else insert
        $row = Database::queryOne('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1', [$roomNumber, $catId]);
        $existingId = $row['id'] ?? null;
        if ($existingId) {
            Database::execute('UPDATE room_category_assignments SET is_primary = 1 WHERE id = ?', [(int)$existingId]);
            $result[] = ['room_number' => $roomNumber, 'category_id' => $catId, 'action' => 'updated_primary'];
        } else {
            Database::execute('INSERT INTO room_category_assignments (room_number, category_id, is_primary) VALUES (?, ?, 1)', [$roomNumber, $catId]);
            $result[] = ['room_number' => $roomNumber, 'category_id' => $catId, 'action' => 'inserted_primary'];
        }
    }

    Database::commit();

    echo json_encode(['ok' => true, 'result' => $result, 'backup' => ['rca' => $rcaPath, 'categories' => $catPath]], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    try { if (Database::inTransaction()) { Database::rollBack(); } } catch (Throwable $t) {}
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
