<?php
// Version: 2.2 - room_number-only schema and queries
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

function normalize_room_number($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    $v = trim((string)$value);
    // Pattern: roomN -> N
    if (preg_match('/^room(\d+)$/i', $v, $m)) {
        return (string)((int)$m[1]);
    }
    // Single-letter rooms (e.g., 'A' for Landing)
    if (preg_match('/^[A-Za-z]$/', $v)) {
        return strtoupper($v);
    }
    // Pure numeric
    if (preg_match('/^\d+$/', $v)) {
        return (string)((int)$v);
    }
    // Fallback: return as-is (trimmed)
    return $v;
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Create room_maps table if it doesn't exist (include room_number to match queries)
    $createTableSQL = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    Database::execute($createTableSQL);
    // Ensure room_number exists (legacy installs) and index present
    try {
        Database::execute("ALTER TABLE room_maps ADD COLUMN room_number VARCHAR(50) NOT NULL");
    } catch (Exception $e) { /* ignore */
    }
    try {
        Database::execute("CREATE INDEX idx_room_number ON room_maps (room_number)");
    } catch (Exception $e) { /* ignore */
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    switch ($method) {
        case 'POST':
            $action = $input['action'] ?? '';
            if ($action === 'save') {
                // Save a new room map (populate room_number for migration compatibility)
                $rn = normalize_room_number($input['room'] ?? ($input['room_number'] ?? ''));
                $mapName = $input['map_name'] ?? 'Unnamed Map';
                $coordinates = json_encode($input['coordinates'] ?? []);

                $result = Database::execute("INSERT INTO room_maps (room_number, map_name, coordinates, is_active) VALUES (?, ?, ?, FALSE)", [$rn, $mapName, $coordinates]);

                if ($result) {
                    Response::success(['message' => 'Room map saved successfully']);
                } else {
                    Response::error('Failed to save room map');
                }
            } elseif ($action === 'apply') {
                // Apply a map to a room (set as active and deactivate others)
                Database::beginTransaction();
                try {
                    // Deactivate all maps for this room
                    $rn = normalize_room_number($input['room'] ?? ($input['room_number'] ?? ''));
                    Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ?", [$rn]);

                    // Activate the selected map
                    Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ?", [$input['map_id']]);

                    Database::commit();
                    Response::updated(['message' => 'Room map applied successfully']);
                } catch (Exception $e) {
                    Database::rollBack();
                    Response::serverError('Failed to apply room map: ' . $e->getMessage());
                }
            } elseif ($action === 'restore') {
                // Restore a historical map (create a new map based on an old one)
                Database::beginTransaction();
                try {
                    // Get the historical map data
                    $originalMap = Database::queryOne("SELECT * FROM room_maps WHERE id = ?", [$input['map_id']]);

                    if (!$originalMap) {
                        throw new Exception('Original map not found');
                    }

                    // Create a new map with restored data
                    $newMapName = $originalMap['map_name'] . ' (Restored ' . date('Y-m-d H:i') . ')';
                    $rn = $originalMap['room_number'];
                    Database::execute(
                        "INSERT INTO room_maps (room_number, map_name, coordinates) VALUES (?, ?, ?)",
                        [$rn, $newMapName, $originalMap['coordinates']]
                    );

                    $newMapId = Database::lastInsertId();

                    // Optionally apply it immediately if requested
                    if (isset($input['apply_immediately']) && $input['apply_immediately']) {
                        // Deactivate all maps for this room
                        Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ?", [$rn]);

                        // Activate the restored map
                        Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ?", [$newMapId]);
                    }

                    Database::commit();
                    Response::success([
                        'message' => 'Map restored successfully',
                        'new_map_id' => $newMapId,
                        'new_map_name' => $newMapName
                    ]);
                } catch (Exception $e) {
                    Database::rollBack();
                    Response::serverError('Failed to restore map: ' . $e->getMessage());
                }
            }
            break;

        case 'GET':
            try {
                // Prefer 'room' query param; fallback to legacy 'room_number'
                $rn = null;
                if (isset($_GET['room']) && $_GET['room'] !== '') {
                    $rn = normalize_room_number($_GET['room']);
                } elseif (isset($_GET['room_number'])) {
                    $rn = normalize_room_number($_GET['room_number']);
                }
                if ($rn !== null) {
                    if (isset($_GET['active_only']) && $_GET['active_only'] === 'true') {
                        // Get active map for a specific room
                        $map = Database::queryOne("SELECT * FROM room_maps WHERE room_number = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1", [$rn]);

                        if ($map) {
                            $map['coordinates'] = json_decode($map['coordinates'], true);
                            Response::success(['map' => $map]);
                        } else {
                            Response::notFound('No active map found');
                        }
                    } else {
                        // Get all maps for a specific room
                        $maps = Database::queryAll("SELECT * FROM room_maps WHERE room_number = ? ORDER BY created_at DESC", [$rn]);

                        foreach ($maps as &$map) {
                            $map['coordinates'] = json_decode($map['coordinates'], true);
                        }

                        Response::success(['maps' => $maps]);
                    }
                } else {
                    // Get all room maps
                    $maps = Database::queryAll("SELECT * FROM room_maps ORDER BY room_number, created_at DESC");

                    foreach ($maps as &$map) {
                        $map['coordinates'] = json_decode($map['coordinates'], true);
                    }

                    Response::success(['maps' => $maps]);
                }
            } catch (Exception $e) {
                Response::serverError('Failed to load maps: ' . $e->getMessage());
            }
            break;

        case 'DELETE':
            if (isset($input['map_id'])) {
                // Check if this is an "Original" map - these cannot be deleted
                $map = Database::queryOne("SELECT map_name FROM room_maps WHERE id = ?", [$input['map_id']]);

                if (!$map) {
                    Response::notFound('Map not found');
                    break;
                }

                if ($map['map_name'] === 'Original') {
                    Response::forbidden('Original maps cannot be deleted - they are protected');
                    break;
                }

                $result = Database::execute("DELETE FROM room_maps WHERE id = ?", [$input['map_id']]);

                if ($result > 0) {
                    Response::success(['message' => 'Room map deleted successfully']);
                } else {
                    Response::error('Failed to delete room map');
                }
            }
            break;

        default:
            Response::methodNotAllowed('Method not allowed');
    }

} catch (PDOException $e) {
    Response::serverError('Database error: ' . $e->getMessage());
}
?> 