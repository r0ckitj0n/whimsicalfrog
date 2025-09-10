<?php
// scripts/migrations/2025-09-07-room-number-migration.php
// Adds room_number columns, backfills from existing data, and verifies integrity.
// Usage:
//   php scripts/migrations/2025-09-07-room-number-migration.php --dry-run
//   php scripts/migrations/2025-09-07-room-number-migration.php --execute
//
// Notes:
// - This script is idempotent. It checks for existing columns before adding.
// - Backfill uses regex for roomN and consults room_settings to resolve special rooms (letters like 'A','B').
// - It will output a summary and any unresolved rows.

require_once __DIR__ . '/../../api/config.php';

function colExists($table, $col) {
    $row = Database::queryOne("SHOW COLUMNS FROM `$table` LIKE ?", [$col]);
    return (bool)$row;
}

function addColumnIfMissing($table, $defSql, $dry) {
    $parts = explode(' ', trim($defSql));
    $col = trim($parts[0], '`');
    if (colExists($table, $col)) {
        echo "[SKIP] $table.$col already exists\n";
        return;
    }
    $sql = "ALTER TABLE `$table` ADD COLUMN $defSql";
    echo ($dry ? "[DRY] " : "[EXEC] ") . $sql . "\n";
    if (!$dry) Database::execute($sql);
}

function fetchRoomSettingsMap() {
    // Returns [room_number => room_number] for quick existence checks
    $rows = Database::queryAll("SELECT room_number FROM room_settings WHERE is_active = 1");
    $map = [];
    foreach ($rows as $r) { $map[(string)$r['room_number']] = (string)$r['room_number']; }
    return $map;
}

function normalizeRoomNumberFromType($roomType, $settingsMap) {
    // roomType like 'room1', 'room_main', 'landing'
    if (preg_match('/^room(\d+)$/i', (string)$roomType, $m)) return (string)((int)$m[1]);
    // Fallbacks via room_settings: try to find letters for landing / room_main
    // Heuristic: Prefer 'A' if present, else 'B', else return null
    $candidates = ['A', 'B'];
    foreach ($candidates as $c) if (isset($settingsMap[$c])) return $c;
    return null;
}

function backfillTable($table, $dry) {
    $settingsMap = fetchRoomSettingsMap();
    $rows = Database::queryAll("SELECT id, room_type FROM `$table`");
    $unresolved = [];
    $updated = 0;
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $rt = $row['room_type'];
        $rn = normalizeRoomNumberFromType($rt, $settingsMap);
        if ($rn === null || $rn === '') { $unresolved[] = $row; continue; }
        $sql = "UPDATE `$table` SET room_number = ? WHERE id = ?";
        if ($dry) {
            echo "[DRY] $table id=$id room_type=$rt -> room_number=$rn\n";
        } else {
            Database::execute($sql, [$rn, $id]);
        }
        $updated++;
    }
    echo "[INFO] $table backfill updated=$updated unresolved=" . count($unresolved) . "\n";
    if ($unresolved) {
        echo "[WARN] Unresolved rows for $table (showing up to 10):\n";
        foreach (array_slice($unresolved, 0, 10) as $r) {
            echo "  id=" . $r['id'] . " room_type=" . $r['room_type'] . "\n";
        }
    }
}

function verify($table) {
    $row = Database::queryOne("SELECT COUNT(*) AS c FROM `$table` WHERE room_number IS NULL OR room_number = ''");
    $nulls = (int)($row['c'] ?? 0);
    echo "[VERIFY] $table null/empty room_number: $nulls\n";
}

function main($argv) {
    $dry = true;
    if (in_array('--execute', $argv, true)) $dry = false;
    if (in_array('--dry-run', $argv, true)) $dry = true;

    Database::getInstance();

    echo "=== Room Number Migration ===\n";

    // 1) Add columns if missing
    addColumnIfMissing('backgrounds', "`room_number` VARCHAR(8) NOT NULL DEFAULT '' AFTER `room_type`", $dry);
    addColumnIfMissing('room_maps',   "`room_number` VARCHAR(8) NOT NULL DEFAULT '' AFTER `room_type`", $dry);
    addColumnIfMissing('area_mappings',"`room_number` VARCHAR(8) NOT NULL DEFAULT '' AFTER `room_type`", $dry);

    // 2) Backfill
    echo "\n=== Backfilling room_number from room_type ===\n";
    backfillTable('backgrounds', $dry);
    backfillTable('room_maps', $dry);
    backfillTable('area_mappings', $dry);

    // 3) Verify
    echo "\n=== Verification ===\n";
    verify('backgrounds');
    verify('room_maps');
    verify('area_mappings');

    echo "\nDone. Re-run with --execute to apply changes.\n";
}

main($argv);
