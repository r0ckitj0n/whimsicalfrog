<?php
/**
 * includes/helpers/RoomMapHelper.php
 * Helper class for room map management
 */

class RoomMapHelper {
    public static function normalizeRoomNumber($value): string {
        if ($value === null || $value === '') return '';
        $v = trim((string)$value);
        if (preg_match('/^room(\d+)$/i', $v, $m)) return (string)((int)$m[1]);
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
