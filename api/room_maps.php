<?php
/**
 * api/room_maps.php
 * Room Maps API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers/RoomMapHelper.php';

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
                if (Database::execute("INSERT INTO room_maps (room_number, map_name, coordinates, is_active) VALUES (?, ?, ?, FALSE)", [$rn, $input['map_name'] ?? 'Unnamed Map', $coordinates])) {
                    Response::success(['map_id' => Database::lastInsertId()]);
                } else
                    Response::error('Save failed');
            } elseif ($action === 'apply' || $action === 'activate') {
                $map_id = $input['id'] ?? $input['map_id'] ?? 0;
                Database::beginTransaction();
                try {
                    Database::execute("UPDATE room_maps SET is_active = FALSE WHERE room_number = ?", [$rn]);
                    Database::execute("UPDATE room_maps SET is_active = TRUE WHERE id = ?", [$map_id]);
                    Database::commit();
                    Response::updated();
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
                $id = $input['id'] ?? $input['map_id'] ?? 0;
                if (Database::execute("DELETE FROM room_maps WHERE id = ?", [$id]))
                    Response::success();
                else
                    Response::error('Delete failed');
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
                        $map['coordinates'] = json_decode($map['coordinates'], true);
                    Response::success(['map' => $map]);
                } else {
                    $maps = Database::queryAll("SELECT id, room_number, map_name, coordinates, is_active, created_at, updated_at FROM room_maps WHERE room_number = ? ORDER BY created_at DESC", [$rn]);
                    foreach ($maps as &$m)
                        $m['coordinates'] = json_decode($m['coordinates'], true);
                    Response::success(['maps' => $maps]);
                }
            } else {
                $maps = Database::queryAll("SELECT id, room_number, map_name, coordinates, is_active, created_at, updated_at FROM room_maps ORDER BY room_number, created_at DESC");
                foreach ($maps as &$m)
                    $m['coordinates'] = json_decode($m['coordinates'], true);
                Response::success(['maps' => $maps]);
            }
            break;

        case 'DELETE':
            $id = $input['map_id'] ?? 0;
            if (Database::execute("DELETE FROM room_maps WHERE id = ?", [$id]))
                Response::success();
            else
                Response::error('Delete failed');
            break;

        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
