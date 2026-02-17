<?php
/**
 * Room Connections API
 * Controller for navigation connections between rooms
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/RoomConnectionManager.php';

// Ensure table exists
$count = Database::queryOne("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_name = 'room_connections' AND table_schema = DATABASE()");
if (!$count || $count['cnt'] == 0) {
    require_once __DIR__ . '/init_room_connections_db.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?? $input['action'] ?? null;

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'get_all':
                    Response::success(RoomConnectionManager::getAll());
                    break;

                case 'get_for_room':
                    $room = $_GET['room'] ?? null;
                    if (!$room)
                        Response::error('room is required', null, 400);
                    Response::success(RoomConnectionManager::getForRoom($room));
                    break;

                case 'get_missing_links':
                    Response::success(['missing_links' => RoomConnectionManager::getMissingLinks()]);
                    break;

                case 'detect_connections':
                    requireAdmin();
                    Response::success(array_merge(['message' => "Detection complete"], RoomConnectionManager::detectAndSync()));
                    break;

                default:
                    Response::error('Unknown GET action', null, 400);
            }
            break;

        case 'POST':
            requireAdmin();
            if ($action === 'delete') {
                $id = $input['id'] ?? null;
                if (!$id)
                    Response::error('id is required', null, 400);
                RoomConnectionManager::delete((int) $id);
                Response::success(['message' => 'Connection deleted']);
            } else {
                $source = $input['source_room'] ?? null;
                $target = $input['target_room'] ?? null;
                $type = $input['connection_type'] ?? 'bidirectional';
                if (!$source || !$target)
                    Response::error('source_room and target_room are required', null, 400);
                $newId = RoomConnectionManager::create($source, $target, $type);
                Response::success(['id' => $newId, 'message' => 'Connection created']);
            }
            break;

        case 'PUT':
            requireAdmin();
            $id = $input['id'] ?? null;
            if (!$id)
                Response::error('id is required', null, 400);
            RoomConnectionManager::update((int) $id, $input);
            Response::success(['message' => 'Connection updated']);
            break;

        case 'DELETE':
            requireAdmin();
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id)
                Response::error('id is required', null, 400);
            RoomConnectionManager::delete((int) $id);
            Response::success(['message' => 'Connection deleted']);
            break;

        default:
            Response::error('Method not allowed', null, 405);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 500);
}
