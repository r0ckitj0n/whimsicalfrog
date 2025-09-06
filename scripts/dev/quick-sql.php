<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');
require_once __DIR__ . '/../../api/config.php';
$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
out('tables', $tables);

try {
    $rows = $pdo->query('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC')->fetchAll(PDO::FETCH_ASSOC);
    out('room_category_assignments (before)', $rows);
} catch (Throwable $e) {
    out('room_category_assignments error', $e->getMessage());
}

try {
    $desc = $pdo->query('DESCRIBE room_category_assignments')->fetchAll(PDO::FETCH_ASSOC);
    out('describe rca', $desc);
} catch (Throwable $e) {
    out('describe rca error', $e->getMessage());
}

// Apply corrections: set correct primary categories per room 1..5
// Mapping: 1=>T-Shirts(1), 2=>Tumblers(2), 3=>Sublimation(4), 4=>Artwork(3), 5=>Window Wraps(5)
try {
    $pdo->beginTransaction();
    $desired = [1=>1, 2=>2, 3=>4, 4=>3, 5=>5];
    foreach ($desired as $room => $catId) {
        $pdo->prepare('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?')->execute([$room]);
        $sel = $pdo->prepare('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1');
        $sel->execute([$room, $catId]);
        $id = $sel->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE room_category_assignments SET is_primary = 1, display_order = 1 WHERE id = ?')->execute([(int)$id]);
        } else {
            $pdo->prepare('INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (?, ?, ?, 1, 1)')
                ->execute([$room, 'Room '.$room, $catId]);
        }
    }
    $pdo->commit();
    out('fix', 'Applied primary category corrections for rooms 1..5');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    out('fix error', $e->getMessage());
}

// Show final state
$final = $pdo->query('SELECT rca.room_number, rca.category_id, rca.is_primary, c.name AS category_name FROM room_category_assignments rca LEFT JOIN categories c ON c.id = rca.category_id ORDER BY rca.room_number, rca.is_primary DESC, rca.category_id')->fetchAll(PDO::FETCH_ASSOC);
out('room_category_assignments (after)', $final);
