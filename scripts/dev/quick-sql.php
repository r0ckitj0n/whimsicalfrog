<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');
require_once __DIR__ . '/../../api/config.php';
Database::getInstance();

function out($label, $data) {
    echo "\n=== $label ===\n";
    if (is_array($data)) {
        foreach ($data as $row) {
            echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
        }
    } else {
        echo $data . "\n";
    }
}

$tablesRows = Database::queryAll('SHOW TABLES');
// Map first column values into a simple list
$tables = array_map(static function($row){ return array_values($row)[0]; }, $tablesRows);
out('tables', $tables);

try {
    $rows = Database::queryAll('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC');
    out('room_category_assignments (before)', $rows);
} catch (Throwable $e) {
    out('room_category_assignments error', $e->getMessage());
}

try {
    $desc = Database::queryAll('DESCRIBE room_category_assignments');
    out('describe rca', $desc);
} catch (Throwable $e) {
    out('describe rca error', $e->getMessage());
}

// Apply corrections: set correct primary categories per room 1..5
// Mapping: 1=>T-Shirts(1), 2=>Tumblers(2), 3=>Sublimation(4), 4=>Artwork(3), 5=>Window Wraps(5)
try {
    Database::beginTransaction();
    $desired = [1=>1, 2=>2, 3=>4, 4=>3, 5=>5];
    foreach ($desired as $room => $catId) {
        Database::execute('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?', [$room]);
        $row = Database::queryOne('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1', [$room, $catId]);
        $id = $row['id'] ?? null;
        if ($id) {
            Database::execute('UPDATE room_category_assignments SET is_primary = 1, display_order = 1 WHERE id = ?', [(int)$id]);
        } else {
            Database::execute('INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (?, ?, ?, 1, 1)', [$room, 'Room '.$room, $catId]);
        }
    }
    Database::commit();
    out('fix', 'Applied primary category corrections for rooms 1..5');
} catch (Throwable $e) {
    try { if (Database::inTransaction()) { Database::rollBack(); } } catch (Throwable $t) {}
    out('fix error', $e->getMessage());
}

// Show final state
$final = Database::queryAll('SELECT rca.room_number, rca.category_id, rca.is_primary, c.name AS category_name FROM room_category_assignments rca LEFT JOIN categories c ON c.id = rca.category_id ORDER BY rca.room_number, rca.is_primary DESC, rca.category_id');
out('room_category_assignments (after)', $final);
