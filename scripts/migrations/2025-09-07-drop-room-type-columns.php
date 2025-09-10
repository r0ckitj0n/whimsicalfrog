<?php
// scripts/migrations/2025-09-07-drop-room-type-columns.php
// Drops legacy room_type columns after successful migration to room_number.
// Usage:
//   php scripts/migrations/2025-09-07-drop-room-type-columns.php --dry-run
//   php scripts/migrations/2025-09-07-drop-room-type-columns.php --execute

require_once __DIR__ . '/../../api/config.php';

function tableExists($table) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) { throw new Exception("Invalid table: $table"); }
    $row = Database::queryOne("SHOW TABLES LIKE '".$table."'");
    return (bool)$row;
}
function colExists($table, $col) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) { throw new Exception("Invalid table: $table"); }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $col)) { throw new Exception("Invalid col: $col"); }
    $row = Database::queryOne("SHOW COLUMNS FROM `".$table."` LIKE '".$col."'");
    return (bool)$row;
}

function main($argv){
    $dry = true;
    if (in_array('--execute', $argv, true)) $dry = false;
    if (in_array('--dry-run', $argv, true)) $dry = true;
    Database::getInstance();

    $targets = ['backgrounds','room_maps','area_mappings'];

    echo "=== Drop room_type columns ===\n";
    foreach ($targets as $t) {
        if (!tableExists($t)) { echo "[SKIP] $t does not exist.\n"; continue; }
        if (!colExists($t, 'room_type')) { echo "[SKIP] $t.room_type already dropped.\n"; continue; }
        // Guard: ensure room_number exists
        if (!colExists($t, 'room_number')) { echo "[WARN] $t.room_number missing; not dropping room_type to avoid breakage.\n"; continue; }
        // Handle table-specific index migrations before dropping column
        if ($t === 'backgrounds') {
            // Drop legacy unique/indexes referencing room_type, then create replacements
            $sqls = [
                // Drop old unique if exists
                "ALTER TABLE `backgrounds` DROP INDEX `unique_active_per_room`",
                // Drop old simple index if exists
                "ALTER TABLE `backgrounds` DROP INDEX `idx_room_type`",
                // Create new unique on room_number + is_active + background_name
                "ALTER TABLE `backgrounds` ADD UNIQUE KEY `unique_active_per_room_num` (`room_number`,`is_active`,`background_name`)"
            ];
            foreach ($sqls as $sql) {
                try { echo ($dry ? "[DRY] " : "[EXEC] ") . $sql . "\n"; if (!$dry) Database::execute($sql); } catch (Throwable $e) { echo "[INFO] skipped: ".$e->getMessage()."\n"; }
            }
        }
        if ($t === 'room_maps') {
            $sqls = [
                // Drop old indexes on room_type
                "ALTER TABLE `room_maps` DROP INDEX `idx_room_active`",
                "ALTER TABLE `room_maps` DROP INDEX `idx_room_type`",
                // Create new index on room_number + is_active
                "ALTER TABLE `room_maps` ADD INDEX `idx_roomnum_active` (`room_number`,`is_active`)"
            ];
            foreach ($sqls as $sql) {
                try { echo ($dry ? "[DRY] " : "[EXEC] ") . $sql . "\n"; if (!$dry) Database::execute($sql); } catch (Throwable $e) { echo "[INFO] skipped: ".$e->getMessage()."\n"; }
            }
        }
        $sql = "ALTER TABLE `".$t."` DROP COLUMN `room_type`";
        echo ($dry ? "[DRY] " : "[EXEC] ").$sql."\n";
        if (!$dry) Database::execute($sql);
    }

    echo "\n=== Post-drop suggestions ===\n";
    echo "- Run quick smokes for backgrounds, room maps, and area mappings.\n";
    echo "- Consider dropping indexes using room_type if any remain.\n";
    echo "Done.\n";
}

main($argv);
