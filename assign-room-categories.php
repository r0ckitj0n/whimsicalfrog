<?php

// scripts/dev/assign-room-categories.php
// Force-correct primary category assignments for rooms 1..5.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/api/config.php';

function tableExists(string $table): bool
{
    try {
        $row = Database::queryOne("SHOW TABLES LIKE ?", [$table]);
        // queryOne returns associative array for the first row; if any row exists, table exists
        return is_array($row) && !empty($row);
    } catch (Throwable $e) {
        return false;
    }
}

try {
    Database::getInstance();

    if (!tableExists('room_category_assignments')) {
        throw new RuntimeException('Table room_category_assignments does not exist');
    }

    // Verify categories table
    if (!tableExists('categories')) {
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
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    $ts = date('Ymd_His');
    $backupPath = rtrim($backupDir, '/')."/room_category_assignments_pre_fix_{$ts}.json";
    $data = Database::queryAll('SELECT * FROM room_category_assignments ORDER BY room_number, is_primary DESC');
    file_put_contents($backupPath, json_encode($data, JSON_PRETTY_PRINT));

    Database::beginTransaction();
    $changes = [];

    foreach ($desired as $room => $catId) {
        // Set all to non-primary first
        Database::execute('UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?', [$room]);

        // Upsert desired category as primary
        $row = Database::queryOne('SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? LIMIT 1', [$room, $catId]);
        $id = $row['id'] ?? null;
        if ($id) {
            Database::execute('UPDATE room_category_assignments SET is_primary = 1 WHERE id = ?', [(int)$id]);
            $changes[] = ['room' => $room, 'category_id' => (int)$catId, 'action' => 'updated_existing_to_primary'];
        } else {
            Database::execute('INSERT INTO room_category_assignments (room_number, category_id, is_primary) VALUES (?, ?, 1)', [$room, $catId]);
            $changes[] = ['room' => $room, 'category_id' => (int)$catId, 'action' => 'inserted_primary'];
        }
    }

    Database::commit();

    echo json_encode(['ok' => true, 'backup' => $backupPath, 'changes' => $changes], JSON_PRETTY_PRINT);
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
