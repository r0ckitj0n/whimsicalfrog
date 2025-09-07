<?php
// Version: 2.0 - With Original map protection
require_once 'config.php';

function normalize_room_type($value) {
    if ($value === null || $value === '') return '';
    if (preg_match('/^room(\d+)$/i', (string)$value, $m)) return 'room' . (int)$m[1];
    return 'room' . (int)$value;
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Create room_maps table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS room_maps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_type VARCHAR(50) NOT NULL,
        map_name VARCHAR(100) NOT NULL,
        coordinates TEXT,
        is_active BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_room_type (room_type),
        INDEX idx_active (is_active),
        INDEX idx_room_active (room_type, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    Database::execute($createTableSQL);

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    switch ($method) {
        case 'POST':
            $action = $input['action'] ?? '';
            if ($action === 'save') {
                // Save a new room map (populate room_number for migration compatibility)
                $rt = normalize_room_type($input['room'] ?? ($input['room_type'] ?? ''));
                $rn = preg_match('/^room(\w+)$/i', (string)$rt, $m) ? (string)$m[1] : '';
                $result = Database::execute(
                    "INSERT INTO room_maps (room_type, room_number, map_name, coordinates) VALUES (?, ?, ?, ?)",
                    [$rt, $rn, $input['map_name'], json_encode($input['coordinates'])]
                ) > 0;

                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Room map saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save room map']);
                }
            } elseif ($action === 'apply') {
                // Apply a map to a room (set as active and deactivate others)
                Database::beginTransaction();
                try {
                    // Deactivate all maps for this room type
                    $rt = normalize_room_type($input['room'] ?? ($input['room_type'] ?? ''));
                    $rn = preg_match('/^room(\w+)$/i', (string)$rt, $m) ? (string)$m[1] : '';
                    Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ? OR room_type = ?", [$rn, $rt]);

                    // Activate the selected map
                    Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ?", [$input['map_id']]);

                    Database::commit();
                    echo json_encode(['success' => true, 'message' => 'Room map applied successfully']);
                } catch (Exception $e) {
                    Database::rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to apply room map: ' . $e->getMessage()]);
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
                    $origRt = $originalMap['room_type'] ?? '';
                    $origRn = $originalMap['room_number'] ?? (preg_match('/^room(\w+)$/i', (string)$origRt, $m) ? (string)$m[1] : '');
                    Database::execute(
                        "INSERT INTO room_maps (room_type, room_number, map_name, coordinates) VALUES (?, ?, ?, ?)",
                        [$origRt, $origRn, $newMapName, $originalMap['coordinates']]
                    );

                    $newMapId = Database::lastInsertId();

                    // Optionally apply it immediately if requested
                    if (isset($input['apply_immediately']) && $input['apply_immediately']) {
                        // Deactivate all maps for this room type
                        Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ? OR room_type = ?", [$origRn, $originalMap['room_type']]);

                        // Activate the restored map
                        Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ?", [$newMapId]);
                    }

                    Database::commit();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Map restored successfully',
                        'new_map_id' => $newMapId,
                        'new_map_name' => $newMapName
                    ]);
                } catch (Exception $e) {
                    Database::rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to restore map: ' . $e->getMessage()]);
                }
            }
            break;

        case 'GET':
            // Prefer 'room' query param; fallback to legacy 'room_type'
            $roomType = null;
            if (isset($_GET['room']) && $_GET['room'] !== '') {
                $roomType = normalize_room_type($_GET['room']);
            } elseif (isset($_GET['room_type'])) {
                $roomType = $_GET['room_type'];
            }
            if ($roomType !== null) {
                if (isset($_GET['active_only']) && $_GET['active_only'] === 'true') {
                    // Get active map for a specific room
                    $rn = preg_match('/^room(\w+)$/i', (string)$roomType, $m) ? (string)$m[1] : '';
                    $map = Database::queryOne("SELECT * FROM room_maps WHERE room_number = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1", [$rn]);
                    if (!$map) {
                        $map = Database::queryOne("SELECT * FROM room_maps WHERE room_type = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1", [$roomType]);
                    }

                    if ($map) {
                        $map['coordinates'] = json_decode($map['coordinates'], true);
                        echo json_encode(['success' => true, 'map' => $map]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No active map found']);
                    }
                } else {
                    // Get all maps for a specific room
                    $rn = preg_match('/^room(\w+)$/i', (string)$roomType, $m) ? (string)$m[1] : '';
                    $maps = Database::queryAll("SELECT * FROM room_maps WHERE (room_number = ? OR room_type = ?) ORDER BY created_at DESC", [$rn, $roomType]);

                    foreach ($maps as &$map) {
                        $map['coordinates'] = json_decode($map['coordinates'], true);
                    }

                    echo json_encode(['success' => true, 'maps' => $maps]);
                }
            } else {
                // Get all room maps
                $maps = Database::queryAll("SELECT * FROM room_maps ORDER BY COALESCE(room_number, room_type), created_at DESC");

                foreach ($maps as &$map) {
                    $map['coordinates'] = json_decode($map['coordinates'], true);
                }

                echo json_encode(['success' => true, 'maps' => $maps]);
            }
            break;

        case 'DELETE':
            if (isset($input['map_id'])) {
                // Check if this is an "Original" map - these cannot be deleted
                $map = Database::queryOne("SELECT map_name FROM room_maps WHERE id = ?", [$input['map_id']]);

                if (!$map) {
                    echo json_encode(['success' => false, 'message' => 'Map not found']);
                    break;
                }

                if ($map['map_name'] === 'Original') {
                    echo json_encode(['success' => false, 'message' => 'Original maps cannot be deleted - they are protected']);
                    break;
                }

                $result = Database::execute("DELETE FROM room_maps WHERE id = ?", [$input['map_id']]);

                if ($result > 0) {
                    echo json_encode(['success' => true, 'message' => 'Room map deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete room map']);
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 