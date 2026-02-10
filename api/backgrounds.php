<?php
/**
 * Backgrounds API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backgrounds/manager.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($method) {
        case 'GET':
            $room = normalizeRoomNumber($_GET['room'] ?? $_GET['room_number'] ?? '');
            $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
            
            if ($room !== '') {
                $rows = getBackgrounds($room, $activeOnly);
                if ($activeOnly && count($rows) > 0) Response::success(['background' => $rows[0]]);
                else Response::success(['backgrounds' => $rows]);
            } else {
                $summary = Database::queryAll("
                    SELECT room_number AS room_key, COUNT(*) AS total_count, SUM(is_active) AS active_count
                    FROM backgrounds GROUP BY room_number ORDER BY room_number
                ");
                Response::success(['summary' => $summary]);
            }
            break;

        case 'POST':
            requireAdmin(true);
            $action = $input['action'] ?? $_POST['action'] ?? '';
            if (!in_array($action, ['save', 'apply', 'rename'], true)) {
                Response::error('Invalid action', null, 400);
            }
            if ($action === 'save') {
                saveBackground($input);
                Response::success(null, 'Saved');
            } elseif ($action === 'apply') {
                $room = normalizeRoomNumber($input['room'] ?? $input['room_number'] ?? '');
                applyBackground($room, $input['background_id'] ?? '');
                Response::success(null, 'Applied');
            } elseif ($action === 'rename') {
                $id = $input['id'] ?? '';
                $name = trim($input['name'] ?? '');
                if (!ctype_digit((string)$id) || !$name) throw new Exception('Missing ID or name');
                Database::execute("UPDATE backgrounds SET name = ? WHERE id = ?", [$name, $id]);
                Response::success(null, 'Renamed');
            }
            break;

        case 'DELETE':
            requireAdmin(true);
            $id = $input['background_id'] ?? $_GET['background_id'] ?? '';
            if (!ctype_digit((string)$id)) throw new Exception('Missing ID');
            $bg = Database::queryOne("SELECT name FROM backgrounds WHERE id = ?", [$id]);
            if (!$bg) Response::notFound();
            if ($bg['name'] === 'Original') throw new Exception('Protected');
            Database::execute("DELETE FROM backgrounds WHERE id = ?", [$id]);
            Response::success(null, 'Deleted');
            break;

        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
