<?php
// Room settings management API
// Headers/CORS handled via api/config.php; use Response helpers for JSON

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

/**
 * Update lightweight room flags without requiring full room payload
 */
function updateRoomFlags($input)
{
    $roomNumber = $input['room_number'] ?? null;
    if ($roomNumber === null || $roomNumber === '') {
        Response::error('room_number is required', null, 400);
        return;
    }

    // Accept icons_white_background as boolean; ignore if not provided
    $fields = [];
    $params = [];
    if (array_key_exists('icons_white_background', $input)) {
        $fields[] = 'icons_white_background = ?';
        $params[] = (int)!!$input['icons_white_background'];
    }

    if (empty($fields)) {
        Response::noChanges(['message' => 'No changes provided']);
        return;
    }

    $params[] = $roomNumber;
    try {
        $sql = 'UPDATE room_settings SET ' . implode(', ', $fields) . ' WHERE room_number = ?';
        $affected = Database::execute($sql, $params);
        if ($affected > 0) {
            Response::updated(['message' => 'Flags updated']);
        } else {
            Response::noChanges(['message' => 'Room not found or no changes made']);
        }
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

// Include database configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
} catch (PDOException $e) {
    Response::serverError('Database connection failed: ' . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($input);
        break;
    case 'PUT':
        handlePut($pdo, $input);
        break;
    case 'DELETE':
        handleDelete($pdo, $input);
        break;
    default:
        Response::methodNotAllowed('Method not allowed');
        break;
}

function handleGet($pdo)
{
    $action = $_GET['action'] ?? 'get_all';
    $roomNumber = $_GET['room_number'] ?? null;

    try {
        if ($action === 'get_all') {
            // Get all room settings ordered by display order
            $rooms = Database::queryAll(
                "SELECT * FROM room_settings WHERE is_active = 1 ORDER BY display_order, room_number"
            );

            Response::success(['rooms' => $rooms]);

        } elseif ($action === 'get_room' && $roomNumber !== null) {
            // Get specific room settings
            $room = Database::queryOne(
                "SELECT * FROM room_settings WHERE room_number = ? AND is_active = 1",
                [$roomNumber]
            );

            if ($room) {
                Response::success(['room' => $room]);
            } else {
                Response::notFound('Room not found');
            }

        } elseif ($action === 'get_navigation_rooms') {
            // Get rooms that should appear in navigation (product rooms)
            $rooms = Database::queryAll(
                "SELECT room_number, room_name, door_label, description 
                 FROM room_settings 
                 WHERE room_number NOT IN ('A', 'B') AND is_active = 1 
                 ORDER BY display_order, room_number"
            );

            echo json_encode(['success' => true, 'rooms' => $rooms]);

        } else {
            Response::error('Invalid action', null, 400);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePost($input)
{
    $action = $input['action'] ?? null;

    if ($action === 'create_room') {
        createRoom($input);
    } else {
        Response::error('Invalid action', null, 400);
    }
}

function createRoom($input)
{
    $requiredFields = ['room_number', 'room_name', 'door_label'];

    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            Response::error("Missing required field: $field", null, 400);
            return;
        }
    }

    try {
        // Check if room number already exists
        $exists = Database::queryOne("SELECT id FROM room_settings WHERE room_number = ?", [$input['room_number']]);

        if ($exists) {
            Response::error('Room number already exists', null, 400);
            return;
        }

        Database::execute(
            "INSERT INTO room_settings (room_number, room_name, door_label, description, display_order) VALUES (?, ?, ?, ?, ?)",
            [
                $input['room_number'],
                trim($input['room_name']),
                trim($input['door_label']),
                $input['description'] ?? '',
                $input['display_order'] ?? 0
            ]
        );

        $roomId = Database::lastInsertId();

        Response::success(['message' => 'Room created successfully', 'room_id' => $roomId]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePut($input)
{
    $action = $input['action'] ?? null;

    if ($action === 'update_room') {
        updateRoom($input);
    } elseif ($action === 'update_display_order') {
        updateDisplayOrder($input);
    } elseif ($action === 'update_flags') {
        updateRoomFlags($input);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function updateRoom($input)
{
    $requiredFields = ['room_number', 'room_name', 'door_label'];

    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    try {
        $affected = Database::execute(
            "UPDATE room_settings 
             SET room_name = ?, door_label = ?, description = ?, display_order = ?, show_search_bar = ?
             WHERE room_number = ?",
            [
                trim($input['room_name']),
                trim($input['door_label']),
                $input['description'] ?? '',
                $input['display_order'] ?? 0,
                isset($input['show_search_bar']) ? (bool)$input['show_search_bar'] : true,
                $input['room_number']
            ]
        );

        if ($affected > 0) {
            Response::updated(['message' => 'Room updated successfully']);
        } else {
            Response::noChanges(['message' => 'Room not found or no changes made']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateDisplayOrder($input)
{
    if (!isset($input['rooms']) || !is_array($input['rooms'])) {
        Response::error('Invalid rooms data', null, 400);
        return;
    }

    try {
        Database::beginTransaction();

        foreach ($input['rooms'] as $room) {
            if (isset($room['room_number']) && isset($room['display_order'])) {
                Database::execute("UPDATE room_settings SET display_order = ? WHERE room_number = ?", [$room['display_order'], $room['room_number']]);
            }
        }

        Database::commit();
        Response::updated(['message' => 'Display order updated successfully']);

    } catch (PDOException $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function handleDelete($pdo, $input)
{
    $roomNumber = $input['room_number'] ?? null;

    if ($roomNumber === null) {
        Response::error('Room number is required', null, 400);
        return;
    }

    // Prevent deletion of core rooms (A, B, plus active product rooms)
    require_once __DIR__ . '/room_helpers.php';
    $coreRooms = getCoreRooms();
    if (in_array($roomNumber, $coreRooms)) {
        Response::forbidden('Core rooms cannot be deleted');
        return;
    }

    try {
        $result = Database::execute("UPDATE room_settings SET is_active = 0 WHERE room_number = ?", [$roomNumber]);

        if ($result > 0) {
            Response::success(['message' => 'Room deactivated successfully']);
        } else {
            Response::notFound('Room not found');
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 