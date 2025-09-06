<?php
// scripts/dev/assign-room-categories.php
// Force-correct primary category assignments for rooms 1..5.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../api/config.php';

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

try {
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!tableExists($pdo, 'room_category_assignments')) {
        throw new RuntimeException('Table room_category_assignments does not exist');
    }

    // Verify categories table
    if (!tableExists($pdo, 'categories')) {
        throw new RuntimeException('Table categories does not exist');
    }

    // Desired mapping based on confirmed category IDs
    // 1=T-Shirts, 2=Tumblers, 3=Artwork, 4=Sublimation, 5=Window Wraps
    $desired = [
        1 => 1, // room1 -> T-Shirts
        2 => 2, // room2 -> Tumblers
        3 => 4, // room3 -> Sublimation
        4 => 3, // room4 -> Artwork
        5 => 5, // room5 -> Window Wraps
    ];

    // Backup
    $backupDir = __DIR__ . '/../../backups/sql_migrations';
    if (!is_dir($backupDir)) { @mkdir($backupDir, 0775, true); }
    $ts = date('Ymd_His');
    $backupPath = rtrim($backupDir,'/')."/room_category_assignments_pre_fix_{$ts}.json";
    $data = $pdo->query('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC')->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents($backupPath, json_encode($data, JSON_PRETTY_PRINT));

    $pdo->beginTransaction();
    $changes = [];

    foreach ($desired as $room => $catId) {
        // Set all to non-primary first
        $pdo->prepare('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?')->execute([$room]);

        // Upsert desired category as primary
        $sel = $pdo->prepare('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1');
        $sel->execute([$room, $catId]);
        $id = $sel->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE room_category_assignments SET is_primary = 1 WHERE id = ?')->execute([(int)$id]);
            $changes[] = ['room' => $room, 'category_id' => (int)$catId, 'action' => 'updated_existing_to_primary'];
        } else {
            $pdo->prepare('INSERT INTO room_category_assignments (room_number, category_id, is_primary) VALUES (?, ?, 1)')->execute([$room, $catId]);
            $changes[] = ['room' => $room, 'category_id' => (int)$catId, 'action' => 'inserted_primary'];
        }
    }

    $pdo->commit();

    echo json_encode(['ok' => true, 'backup' => $backupPath, 'changes' => $changes], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
