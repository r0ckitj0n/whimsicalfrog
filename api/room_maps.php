<?php
/**
 * api/room_maps.php
 * Room Maps API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers/RoomMapHelper.php';

/**
 * Decode coordinate payloads that may be nested JSON strings from legacy rows.
 *
 * @param mixed $raw
 * @return mixed
 */
function decodeRoomMapCoordinates($raw) {
    $coords = is_string($raw) ? json_decode($raw, true) : $raw;

    for ($i = 0; $i < 3; $i++) {
        if (!is_string($coords)) {
            break;
        }
        $decoded = json_decode($coords, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            break;
        }
        $coords = $decoded;
    }

    if (is_array($coords)) {
        if (isset($coords['rectangles']) && is_string($coords['rectangles'])) {
            $decodedRects = json_decode($coords['rectangles'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coords['rectangles'] = $decodedRects;
            }
        }

        if (isset($coords['polygons']) && is_string($coords['polygons'])) {
            $decodedPolygons = json_decode($coords['polygons'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coords['polygons'] = $decodedPolygons;
            }
        }
    }

    return $coords;
}

/**
 * Ensure a room has exactly one active map when possible.
 * If no active map exists, activate the most recently updated map.
 *
 * @param string $roomNumber
 * @return void
 */
function ensureRoomHasActiveMap(string $roomNumber): void {
    if ($roomNumber === '') {
        return;
    }

    $active = Database::queryOne(
        "SELECT id FROM room_maps WHERE room_number = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1",
        [$roomNumber]
    );
    if ($active && !empty($active['id'])) {
        return;
    }

    $fallback = Database::queryOne(
        "SELECT id FROM room_maps WHERE room_number = ? ORDER BY updated_at DESC LIMIT 1",
        [$roomNumber]
    );
    if ($fallback && !empty($fallback['id'])) {
        Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ?", [$roomNumber]);
        Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ? AND room_number = ?", [(int)$fallback['id'], $roomNumber]);
    }
}

try {
    Database::getInstance();
    RoomMapHelper::ensureTable();

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($method) {
        case 'POST':
            $action = $input['action'] ?? '';
            $rn = RoomMapHelper::normalizeRoomNumber($input['room'] ?? $input['room_number'] ?? '');

            if ($action === 'save') {
                // Handle both pre-encoded JSON string and array
                $coords = $input['coordinates'] ?? [];
                $coordinates = is_string($coords) ? $coords : json_encode($coords);
                $mapName = trim((string)($input['map_name'] ?? 'Unnamed Map'));
                if ($mapName === '') {
                    $mapName = 'Unnamed Map';
                }

                $existing = Database::queryOne(
                    "SELECT id FROM room_maps WHERE room_number = ? AND map_name = ? ORDER BY updated_at DESC LIMIT 1",
                    [$rn, $mapName]
                );

                if ($existing && !empty($existing['id'])) {
                    $existingId = (int)$existing['id'];
                    $ok = Database::execute(
                        "UPDATE room_maps SET coordinates = ?, updated_at = NOW() WHERE id = ?",
                        [$coordinates, $existingId]
                    );
                    if ($ok) {
                        Database::execute(
                            "DELETE FROM room_maps WHERE room_number = ? AND map_name = ? AND id <> ?",
                            [$rn, $mapName, $existingId]
                        );
                        ensureRoomHasActiveMap($rn);
                        Response::success(['map_id' => $existingId, 'updated_existing' => true]);
                    }
                    Response::error('Save failed');
                } else {
                    if (Database::execute("INSERT INTO room_maps (room_number, map_name, coordinates, is_active) VALUES (?, ?, ?, FALSE)", [$rn, $mapName, $coordinates])) {
                        $newId = (int)Database::lastInsertId();
                        Database::execute(
                            "DELETE FROM room_maps WHERE room_number = ? AND map_name = ? AND id <> ?",
                            [$rn, $mapName, $newId]
                        );
                        ensureRoomHasActiveMap($rn);
                        Response::success(['map_id' => $newId, 'updated_existing' => false]);
                    } else {
                        Response::error('Save failed');
                    }
                }
            } elseif ($action === 'apply' || $action === 'activate') {
                $map_id = (int)($input['id'] ?? $input['map_id'] ?? 0);
                if ($map_id <= 0) {
                    Response::error('Invalid map id');
                }

                $targetMap = Database::queryOne("SELECT id, room_number FROM room_maps WHERE id = ? LIMIT 1", [$map_id]);
                if (!$targetMap) {
                    Response::error('Map not found');
                }

                $mapRoom = RoomMapHelper::normalizeRoomNumber($targetMap['room_number'] ?? '');
                if ($rn === '') {
                    $rn = $mapRoom;
                }

                if ($rn === '' || $mapRoom === '') {
                    Response::error('Room is required to activate map');
                }

                if ($rn !== $mapRoom) {
                    Response::error('Selected room does not match map room');
                }

                Database::beginTransaction();
                try {
                    Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ?", [$rn]);
                    $rows = Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ? AND room_number = ?", [$map_id, $rn]);
                    if ($rows < 1) {
                        throw new Exception('Activation update failed');
                    }
                    Database::commit();
                    Response::updated(['map_id' => $map_id, 'room' => $rn]);
                } catch (Exception $e) {
                    Database::rollBack();
                    Response::serverError($e->getMessage());
                }
            } elseif ($action === 'rename') {
                $id = $input['id'] ?? $input['map_id'] ?? 0;
                $new_name = $input['map_name'] ?? $input['name'] ?? '';
                if ($id > 0 && !empty($new_name)) {
                    if (Database::execute("UPDATE room_maps SET map_name = ? WHERE id = ?", [$new_name, $id])) {
                        Response::success();
                    } else {
                        Response::error('Rename failed');
                    }
                } else {
                    Response::error('Invalid parameters for rename');
                }
            } elseif ($action === 'delete') {
                $id = (int)($input['id'] ?? $input['map_id'] ?? 0);
                if ($id <= 0) {
                    Response::error('Invalid map id');
                }

                $target = Database::queryOne("SELECT room_number FROM room_maps WHERE id = ? LIMIT 1", [$id]);
                if (!$target) {
                    Response::error('Map not found');
                }

                $targetRoom = RoomMapHelper::normalizeRoomNumber($target['room_number'] ?? '');
                Database::beginTransaction();
                try {
                    $deleted = Database::execute("DELETE FROM room_maps WHERE id = ?", [$id]);
                    if (!$deleted) {
                        throw new Exception('Delete failed');
                    }
                    ensureRoomHasActiveMap($targetRoom);
                    Database::commit();
                    Response::success();
                } catch (Exception $e) {
                    Database::rollBack();
                    Response::serverError($e->getMessage());
                }
            } elseif ($action === 'promote_active_to_original') {
                try {
                    $id = RoomMapHelper::promoteToOriginal($rn, (int) ($input['id'] ?? $input['map_id'] ?? 0));
                    Response::success(['map_id' => $id]);
                } catch (Exception $e) {
                    Response::serverError($e->getMessage());
                }
            }
            break;

        case 'GET':
            $rn = isset($_GET['room']) ? RoomMapHelper::normalizeRoomNumber($_GET['room']) : (isset($_GET['room_number']) ? RoomMapHelper::normalizeRoomNumber($_GET['room_number']) : null);
            if ($rn !== null) {
                $action = $_GET['action'] ?? '';
                if ($action === 'get_active' || ($_GET['active_only'] ?? '') === 'true') {
                    $map = Database::queryOne("SELECT id, room_number, map_name, coordinates, is_active, created_at, updated_at FROM room_maps WHERE room_number = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1", [$rn]);
                    if ($map)
                        $map['coordinates'] = decodeRoomMapCoordinates($map['coordinates']);
                    Response::success(['map' => $map]);
                } else {
                    $maps = Database::queryAll("SELECT id, room_number, map_name, coordinates, is_active, created_at, updated_at FROM room_maps WHERE room_number = ? ORDER BY created_at ASC, id ASC", [$rn]);
                    foreach ($maps as &$m)
                        $m['coordinates'] = decodeRoomMapCoordinates($m['coordinates']);
                    Response::success(['maps' => $maps]);
                }
            } else {
                $maps = Database::queryAll("SELECT id, room_number, map_name, coordinates, is_active, created_at, updated_at FROM room_maps ORDER BY room_number, created_at ASC, id ASC");
                foreach ($maps as &$m)
                    $m['coordinates'] = decodeRoomMapCoordinates($m['coordinates']);
                Response::success(['maps' => $maps]);
            }
            break;

        case 'DELETE':
            $id = (int)($input['map_id'] ?? 0);
            if ($id <= 0) {
                Response::error('Invalid map id');
            }
            $target = Database::queryOne("SELECT room_number FROM room_maps WHERE id = ? LIMIT 1", [$id]);
            if (!$target) {
                Response::error('Map not found');
            }
            $targetRoom = RoomMapHelper::normalizeRoomNumber($target['room_number'] ?? '');
            Database::beginTransaction();
            try {
                $deleted = Database::execute("DELETE FROM room_maps WHERE id = ?", [$id]);
                if (!$deleted) {
                    throw new Exception('Delete failed');
                }
                ensureRoomHasActiveMap($targetRoom);
                Database::commit();
                Response::success();
            } catch (Exception $e) {
                Database::rollBack();
                Response::serverError($e->getMessage());
            }
            break;

        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
