<?php
/**
 * Room Settings Management API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rooms/settings_manager.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireAdmin(true);
}

try {
    Database::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Support _method override for DELETE via POST (reliable body serialization)
    if ($method === 'POST' && isset($input['_method']) && strtoupper($input['_method']) === 'DELETE') {
        $method = 'DELETE';
    }

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'get_all';
            $room_number = $_GET['room_number'] ?? null;

            if ($action === 'get_all') {
                $rooms = Database::queryAll("SELECT * FROM room_settings ORDER BY display_order, room_number");
                Response::success(['rooms' => $rooms]);
            } elseif ($action === 'get_room' && $room_number !== null) {
                $room = Database::queryOne("SELECT * FROM room_settings WHERE room_number = ?", [$room_number]);
                $room ? Response::success(['room' => $room]) : Response::notFound('Room not found');
            } elseif ($action === 'get_navigation_rooms') {
                $rooms = Database::queryAll("SELECT room_number, room_name, door_label, description FROM room_settings WHERE room_number NOT IN ('A', 'B') AND is_active = 1 ORDER BY display_order, room_number");
                Response::success(['rooms' => $rooms]);
            } else {
                Response::error('Invalid action', null, 400);
            }
            break;

        case 'POST':
            $action = $input['action'] ?? null;
            if ($action === 'create_room') {
                $required = ['room_number', 'room_name', 'door_label'];
                foreach ($required as $f) {
                    if (empty(trim($input[$f] ?? '')))
                        throw new Exception("Missing field: $f");
                }

                // Use upsert to handle re-creating previously deactivated rooms
                $sql = "INSERT INTO room_settings (room_number, room_name, door_label, description, display_order, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            room_name = VALUES(room_name),
                            door_label = VALUES(door_label),
                            description = VALUES(description),
                            display_order = VALUES(display_order),
                            is_active = VALUES(is_active)";

                Database::execute($sql, [
                    (string) $input['room_number'],
                    trim($input['room_name']),
                    trim($input['door_label']),
                    $input['description'] ?? '',
                    $input['display_order'] ?? 0,
                    isset($input['is_active']) ? (int) !!$input['is_active'] : 1
                ]);
                Response::success(['message' => 'Created', 'room_id' => Database::lastInsertId()]);

            } elseif ($action === 'update_room') {
                $room_number = $input['room_number'] ?? null;
                if ($room_number === null || $room_number === '')
                    throw new Exception('Room number required');

                $fields = [];
                $params = [];

                $allowed = ['room_name', 'door_label', 'description', 'display_order', 'show_search_bar', 'render_context', 'target_aspect_ratio', 'background_url', 'is_active', 'room_role'];
                foreach ($allowed as $f) {
                    if (array_key_exists($f, $input)) {
                        $fields[] = "`$f`=?";
                        $val = $input[$f];
                        if (in_array($f, ['room_name', 'door_label']))
                            $val = trim((string) $val);
                        if ($f === 'is_active') {
                            $val = (int) !!$val;
                            if ($val === 0 && wf_room_is_protected($room_number))
                                throw new Exception('Cannot deactivate is_protected room');
                        }
                        if ($f === 'display_order' || $f === 'show_search_bar')
                            $val = (int) $val;
                        $params[] = $val;
                    }
                }

                if (isset($input['icon_panel_color'])) {
                    $color = wf_normalize_icon_panel_color($input['icon_panel_color']);
                    if ($color !== null) {
                        $fields[] = "`icon_panel_color`=?";
                        $params[] = $color;
                        $fields[] = "`has_icons_white_background`=?";
                        $params[] = ($color === 'transparent') ? 0 : 1;
                    }
                }

                if (empty($fields)) {
                    Response::success(['message' => 'No changes']);
                    return;
                }

                $sql = "UPDATE room_settings SET " . implode(', ', $fields) . " WHERE room_number=?";
                $params[] = (string) $room_number;

                Database::execute($sql, $params);
                Response::updated();

            } else {
                Response::error('Invalid action', null, 400);
            }
            break;

        case 'PUT':
            $action = $input['action'] ?? null;
            $room_number = $input['room_number'] ?? null;
            if ($room_number === null || $room_number === '')
                throw new Exception('Room number required');

            if ($action === 'update_room') {
                $fields = [];
                $params = [];

                $allowed = ['room_name', 'door_label', 'description', 'display_order', 'show_search_bar', 'render_context', 'target_aspect_ratio', 'background_url', 'is_active', 'room_role'];
                foreach ($allowed as $f) {
                    if (array_key_exists($f, $input)) {
                        $fields[] = "`$f`=?";
                        $val = $input[$f];
                        if (in_array($f, ['room_name', 'door_label']))
                            $val = trim((string) $val);
                        if ($f === 'is_active') {
                            $val = (int) !!$val;
                            if ($val === 0 && wf_room_is_protected($room_number))
                                throw new Exception('Cannot deactivate is_protected room');
                        }
                        if ($f === 'display_order' || $f === 'show_search_bar')
                            $val = (int) $val;
                        $params[] = $val;
                    }
                }

                if (isset($input['icon_panel_color'])) {
                    $color = wf_normalize_icon_panel_color($input['icon_panel_color']);
                    if ($color !== null) {
                        $fields[] = "`icon_panel_color`=?";
                        $params[] = $color;
                        $fields[] = "`has_icons_white_background`=?";
                        $params[] = ($color === 'transparent') ? 0 : 1;
                    }
                }

                if (empty($fields)) {
                    Response::success(['message' => 'No changes']);
                    return;
                }

                $sql = "UPDATE room_settings SET " . implode(', ', $fields) . " WHERE room_number=?";
                $params[] = (string) $room_number;

                Database::execute($sql, $params);
                Response::updated();
            } elseif ($action === 'update_flags') {
                updateRoomFlags($room_number, $input);
                Response::updated();
            } elseif ($action === 'set_active') {
                $result = setRoomActiveState($room_number, isset($input['is_active']) ? (int) !!$input['is_active'] : 1);
                if (!empty($result['failed_items'])) {
                    Response::success([
                        'success' => false,
                        'room_updated' => $result['room_updated'],
                        'items_updated' => $result['items_updated'],
                        'failed_items' => $result['failed_items'],
                        'error' => 'Some items failed to update'
                    ]);
                } else {
                    Response::updated();
                }
            } else {
                Response::error('Invalid action', null, 400);
            }
            break;

        case 'DELETE':
            $room_number = $input['room_number'] ?? null;
            if ($room_number === null || $room_number === '')
                throw new Exception('Room number required');

            if (wf_room_is_protected($room_number)) {
                throw new Exception('Cannot delete protected room');
            }

            // Check if room exists and its current status
            $room = Database::queryOne("SELECT room_number, is_active FROM room_settings WHERE room_number = ?", [(string) $room_number]);
            if (!$room) {
                throw new Exception('Room not found');
            }

            if ($room['is_active']) {
                // Active room: just deactivate
                setRoomActiveState($room_number, 0);
                Response::success(['message' => 'Room deactivated', 'action' => 'deactivated']);
            } else {
                // Inactive room: permanently delete
                // 1. Get all categories assigned to this room
                $categoryIds = Database::queryAll(
                    "SELECT category_id FROM room_category_assignments WHERE room_number = ?",
                    [(string) $room_number]
                );

                $archivedCount = 0;
                if (!empty($categoryIds)) {
                    $catIds = array_column($categoryIds, 'category_id');
                    $placeholders = implode(',', array_fill(0, count($catIds), '?'));

                    // 2. Archive all items in these categories that aren't already archived
                    $archivedCount = Database::execute(
                        "UPDATE items SET is_archived = 1, archived_at = NOW(), archived_by = 'room_deletion', category_id = NULL 
                         WHERE category_id IN ($placeholders) AND is_archived = 0",
                        $catIds
                    );
                }

                // 3. Delete room category assignments
                Database::execute("DELETE FROM room_category_assignments WHERE room_number = ?", [(string) $room_number]);

                // 4. Delete room maps
                Database::execute("DELETE FROM room_maps WHERE room_number = ?", [(string) $room_number]);

                // 5. Delete room configs
                Database::execute("DELETE FROM room_configs WHERE room_number = ?", [(string) $room_number]);

                // 6. Finally delete the room itself
                Database::execute("DELETE FROM room_settings WHERE room_number = ?", [(string) $room_number]);

                Response::success([
                    'message' => 'Room permanently deleted',
                    'action' => 'deleted',
                    'items_archived' => $archivedCount
                ]);
            }
            break;


        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
