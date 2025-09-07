<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');
require_once __DIR__ . '/../../api/config.php';
Database::getInstance();

echo "Starting RCA fix...\n";

// Desired mapping: room -> category_id
$desired = [
    1 => 1, // T-Shirts
    2 => 2, // Tumblers
    3 => 4, // Sublimation
    4 => 3, // Artwork
    5 => 5, // Window Wraps
];

// Backup
$backupDir = __DIR__ . '/../../backups/sql_migrations';
if (!is_dir($backupDir)) { @mkdir($backupDir, 0775, true); }
$ts = date('Ymd_His');
$backupPath = rtrim($backupDir,'/')."/room_category_assignments_pre_fixnow_{$ts}.json";
$rows = Database::queryAll('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC');
file_put_contents($backupPath, json_encode($rows, JSON_PRETTY_PRINT));
echo "Backed up to: $backupPath\n";

Database::beginTransaction();
try {
    foreach ($desired as $room => $catId) {
        Database::execute('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?', [$room]);
        $row = Database::queryOne('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1', [$room, $catId]);
        $id = $row ? $row['id'] : null;
        if ($id) {
            Database::execute('UPDATE room_category_assignments SET is_primary = 1 WHERE id = ?', [(int)$id]);
            echo "Room $room -> category $catId set primary (updated)\n";
        } else {
            Database::execute('INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (?, ?, ?, 1, 1)', [$room, 'Room '.$room, $catId]);
            echo "Room $room -> category $catId inserted as primary\n";
        }
    }
    Database::commit();
    echo "Committed changes.\n";
} catch (Throwable $e) {
    Database::rollBack();
    echo "Error: ".$e->getMessage()."\n";
    exit(1);
}

// Show final state
$rows = Database::queryAll('SELECT rca.room_number, rca.category_id, rca.is_primary, c.name AS category_name FROM room_category_assignments rca LEFT JOIN categories c ON c.id = rca.category_id ORDER BY rca.room_number, rca.is_primary DESC, rca.category_id');
foreach ($rows as $r) {
    echo sprintf("room %s -> %s (id=%d) primary=%d\n", $r['room_number'], $r['category_name'], $r['category_id'], $r['is_primary']);
}
