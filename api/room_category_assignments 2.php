<?php
/**
 * Room-Category assignment management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rooms/category_manager.php';

require_once __DIR__ . '/../includes/auth.php';
requireAdmin(true);

try {
    Database::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'get_all';
            if ($action === 'get_all')
                Response::json(['success' => true, 'assignments' => getAllAssignments()]);
            elseif ($action === 'get_summary')
                Response::json(['success' => true, 'summary' => getSummary()]);
            elseif ($action === 'get_room')
                Response::json(['success' => true, 'assignments' => getRoomAssignments($_GET['room_number'] ?? null)]);
            else
                Response::error('Invalid action', null, 400);
            break;

        case 'POST':
            $action = $input['action'] ?? $_GET['action'] ?? 'add';
            if ($action === 'add') {
                addAssignment($input);
                Response::success(['message' => 'Added']);
            } elseif ($action === 'set_primary') {
                setPrimary($input);
                Response::success(['message' => 'Primary updated']);
            } elseif ($action === 'update_assignment') {
                updateAssignment($input);
                Response::success(['message' => 'Updated']);
            } else {
                Response::error('Invalid action', null, 400);
            }
            break;

        case 'DELETE':
            $id = $input['assignment_id'] ?? null;
            if ($id) {
                Database::execute("DELETE FROM room_category_assignments WHERE id = ?", [$id]);
                Response::success(['message' => 'Deleted']);
            } else {
                Response::error('ID required', null, 400);
            }
            break;

        default:
            Response::methodNotAllowed();
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
