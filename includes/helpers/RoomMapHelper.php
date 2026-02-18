<?php
/**
 * includes/helpers/RoomMapHelper.php
 * Helper class for room map management
 */

class RoomMapHelper {
    public static function normalizeRoomNumber($value): string {
        if ($value === null || $value === '') return '';
        $v = trim((string)$value);
        $lv = strtolower($v);
        if (in_array($lv, ['main', 'room_main', 'room-main', 'roommain'], true)) return '0';
        if (in_array($lv, ['landing', 'room_landing', 'room-landing'], true)) return 'A';
        if (preg_match('/^room(\d+)$/i', $v, $m)) return (string)((int)$m[1]);
        if (preg_match('/^room([A-Za-z])$/', $v, $m)) return strtoupper($m[1]);
        if (preg_match('/^[A-Za-z]$/', $v)) return strtoupper($v);
        if (preg_match('/^\d+$/', $v)) return (string)((int)$v);
        return $v;
    }

    public static function ensureTable(): void {
        Database::getInstance()->exec("
            CREATE TABLE IF NOT EXISTS room_maps (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_number VARCHAR(50) NOT NULL,
                map_name VARCHAR(255) NOT NULL,
                coordinates TEXT,
                is_active BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_room_number (room_number),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Heal legacy/corrupted schemas in place so map APIs keep working.
        self::ensureColumn('room_maps', 'id', "ALTER TABLE room_maps ADD COLUMN id INT NOT NULL");
        self::ensureColumn('room_maps', 'room_number', "ALTER TABLE room_maps ADD COLUMN room_number VARCHAR(50) NOT NULL DEFAULT '0'");
        self::ensureColumn('room_maps', 'map_name', "ALTER TABLE room_maps ADD COLUMN map_name VARCHAR(255) NOT NULL DEFAULT 'Original'");
        self::ensureColumn('room_maps', 'coordinates', "ALTER TABLE room_maps ADD COLUMN coordinates TEXT NULL");
        self::ensureColumn('room_maps', 'is_active', "ALTER TABLE room_maps ADD COLUMN is_active BOOLEAN DEFAULT FALSE");
        self::ensureColumn('room_maps', 'created_at', "ALTER TABLE room_maps ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        self::ensureColumn('room_maps', 'updated_at', "ALTER TABLE room_maps ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        $pk = Database::queryOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'room_maps'
               AND CONSTRAINT_TYPE = 'PRIMARY KEY'"
        );
        if ((int)($pk['c'] ?? 0) === 0) {
            Database::execute("ALTER TABLE room_maps ADD PRIMARY KEY (id)");
        }

        Database::execute("ALTER TABLE room_maps MODIFY id INT NOT NULL AUTO_INCREMENT");

        self::ensureIndex('room_maps', 'idx_room_number', "ALTER TABLE room_maps ADD INDEX idx_room_number (room_number)");
        self::ensureIndex('room_maps', 'idx_active', "ALTER TABLE room_maps ADD INDEX idx_active (is_active)");
        self::ensureIndex('room_maps', 'idx_room_maps_room_active_updated', "ALTER TABLE room_maps ADD INDEX idx_room_maps_room_active_updated (room_number, is_active, updated_at)");
    }

    private static function ensureColumn(string $table, string $column, string $ddl): void {
        $row = Database::queryOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?",
            [$table, $column]
        );
        if ((int)($row['c'] ?? 0) === 0) {
            Database::execute($ddl);
        }
    }

    private static function ensureIndex(string $table, string $indexName, string $ddl): void {
        $row = Database::queryOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?",
            [$table, $indexName]
        );
        if ((int)($row['c'] ?? 0) === 0) {
            Database::execute($ddl);
        }
    }

    public static function promoteToOriginal($room_number, $mapId = 0): int {
        Database::beginTransaction();
        try {
            if ($mapId > 0 && empty($room_number)) {
                $row = Database::queryOne("SELECT room_number FROM room_maps WHERE id = ?", [$mapId]);
                if (!$row) throw new Exception('Map not found');
                $room_number = $row['room_number'];
            }
            if (empty($room_number)) throw new Exception('Room is required');

            if ($mapId <= 0) {
                $active = Database::queryOne("SELECT id FROM room_maps WHERE room_number = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1", [$room_number]);
                if (!$active) throw new Exception('No active map to promote');
                $mapId = (int)$active['id'];
            }

            Database::execute("UPDATE room_maps SET map_name = 'Original', is_active = TRUE WHERE id = ?", [$mapId]);
            Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ? AND id <> ?", [$room_number, $mapId]);
            Database::execute("DELETE FROM room_maps WHERE room_number = ? AND map_name = 'Original' AND id <> ?", [$room_number, $mapId]);
            Database::commit();
            return $mapId;
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
