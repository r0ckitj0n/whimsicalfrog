<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');
require_once __DIR__ . '/../../api/config.php';
$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
$rows = $pdo->query('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC')->fetchAll(PDO::FETCH_ASSOC);
file_put_contents($backupPath, json_encode($rows, JSON_PRETTY_PRINT));
echo "Backed up to: $backupPath\n";

$pdo->beginTransaction();
try {
    foreach ($desired as $room => $catId) {
        $pdo->prepare('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?')->execute([$room]);
        $sel = $pdo->prepare('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1');
        $sel->execute([$room, $catId]);
        $id = $sel->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE room_category_assignments SET is_primary = 1 WHERE id = ?')->execute([(int)$id]);
            echo "Room $room -> category $catId set primary (updated)\n";
        } else {
            $pdo->prepare('INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (?, ?, ?, 1, 1)')
                ->execute([$room, 'Room '.$room, $catId]);
            echo "Room $room -> category $catId inserted as primary\n";
        }
    }
    $pdo->commit();
    echo "Committed changes.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Error: ".$e->getMessage()."\n";
    exit(1);
}

// Show final state
$stmt = $pdo->query('SELECT rca.room_number, rca.category_id, rca.is_primary, c.name AS category_name FROM room_category_assignments rca LEFT JOIN categories c ON c.id = rca.category_id ORDER BY rca.room_number, rca.is_primary DESC, rca.category_id');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo sprintf("room %s -> %s (id=%d) primary=%d\n", $r['room_number'], $r['category_name'], $r['category_id'], $r['is_primary']);
}
