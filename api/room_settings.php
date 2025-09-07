<?php
// Room settings management API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo, $input);
        break;
    case 'PUT':
        handlePut($pdo, $input);
        break;
    case 'DELETE':
        handleDelete($pdo, $input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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

            echo json_encode(['success' => true, 'rooms' => $rooms]);

        } elseif ($action === 'get_room' && $roomNumber !== null) {
            // Get specific room settings
            $room = Database::queryOne(
                "SELECT * FROM room_settings WHERE room_number = ? AND is_active = 1",
                [$roomNumber]
            );

            if ($room) {
                echo json_encode(['success' => true, 'room' => $room]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
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
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
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
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function createRoom($input)
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
        // Check if room number already exists
        $exists = Database::queryOne("SELECT id FROM room_settings WHERE room_number = ?", [$input['room_number']]);

        if ($exists) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Room number already exists']);
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

        echo json_encode([
            'success' => true,
            'message' => 'Room created successfully',
            'room_id' => $roomId
        ]);

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
            echo json_encode(['success' => true, 'message' => 'Room updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Room not found or no changes made']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateDisplayOrder($pdo, $input)
{
    if (!isset($input['rooms']) || !is_array($input['rooms'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid rooms data']);
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
        echo json_encode(['success' => true, 'message' => 'Display order updated successfully']);

    } catch (PDOException $e) {
        Database::rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo, $input)
{
    $roomNumber = $input['room_number'] ?? null;

    if ($roomNumber === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room number is required']);
        return;
    }

    // Prevent deletion of core rooms (A, B, plus active product rooms)
    require_once __DIR__ . '/room_helpers.php';
    $coreRooms = getCoreRooms();
    if (in_array($roomNumber, $coreRooms)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Core rooms cannot be deleted']);
        return;
    }

    try {
        $result = Database::execute("UPDATE room_settings SET is_active = 0 WHERE room_number = ?", [$roomNumber]);

        if ($result > 0) {
            echo json_encode(['success' => true, 'message' => 'Room deactivated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Room not found']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 