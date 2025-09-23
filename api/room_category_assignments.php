<?php
// Room-Category assignment management API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration
require_once __DIR__ . '/config.php';

// Authentication check
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Please log in.']);
    exit;
}

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
        handleGet(Database::getInstance());
        break;
    case 'POST':
        handlePost(Database::getInstance(), $input);
        break;
    case 'PUT':
        handlePut(Database::getInstance(), $input);
        break;
    case 'DELETE':
        handleDelete(Database::getInstance(), $input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
function handleGet($pdo)
{
    $action = $_GET['action'] ?? 'get_all';

    switch ($action) {
        case 'get_all':
            getAllAssignments($pdo);
            break;
        case 'get_summary':
            getSummary($pdo);
            break;
        case 'get_room':
            getRoomAssignments($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function getAllAssignments($pdo)
{
    try {
        $assignments = Database::queryAll(
            "SELECT rca.*, c.name as category_name, c.description as category_description
             FROM room_category_assignments rca 
             JOIN categories c ON rca.category_id = c.id 
             ORDER BY rca.room_number, rca.display_order"
        );
        echo json_encode(['success' => true, 'assignments' => $assignments]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSummary($pdo)
{
    try {
        $summary = Database::queryAll(
            "SELECT 
                rca.room_number,
                rca.room_name,
                GROUP_CONCAT(c.name ORDER BY rca.display_order SEPARATOR ', ') as categories,
                COUNT(*) as category_count,
                MAX(CASE WHEN rca.is_primary = 1 THEN c.name END) as primary_category
             FROM room_category_assignments rca 
             JOIN categories c ON rca.category_id = c.id 
             GROUP BY rca.room_number, rca.room_name
             ORDER BY rca.room_number"
        );
        echo json_encode(['success' => true, 'summary' => $summary]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getRoomAssignments($pdo)
{
    $roomNumber = $_GET['room_number'] ?? null;

    if ($roomNumber === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room number is required']);
        return;
    }

    try {
        $assignments = Database::queryAll(
            "SELECT rca.*, c.name as category_name, c.description as category_description
             FROM room_category_assignments rca 
             JOIN categories c ON rca.category_id = c.id 
             WHERE rca.room_number = ?
             ORDER BY rca.display_order",
            [$roomNumber]
        );
        echo json_encode(['success' => true, 'assignments' => $assignments]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePost($pdo, $input)
{
    $action = $input['action'] ?? $_GET['action'] ?? 'add';

    switch ($action) {
        case 'add':
            addAssignment($pdo, $input);
            break;
        case 'set_primary':
            setPrimary($pdo, $input);
            break;
        case 'update_order':
            updateOrder($pdo, $input);
            break;
        case 'update_single_order':
            updateSingleOrder($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function handlePut($pdo, $input)
{
    handlePost($pdo, $input);
}

function addAssignment($pdo, $input)
{
    $roomNumber = $input['room_number'] ?? null;
    $roomName = $input['room_name'] ?? '';
    $categoryId = $input['category_id'] ?? null;
    $isPrimary = $input['is_primary'] ?? 0;
    $displayOrder = $input['display_order'] ?? 0;

    if ($roomNumber === null || $categoryId === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room number and category ID are required']);
        return;
    }

    try {
        // Check if assignment already exists
        $exists = Database::queryOne("SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ?", [$roomNumber, $categoryId]);

        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'This room-category assignment already exists']);
            return;
        }

        // If setting as primary, remove primary status from other categories in this room
        if ($isPrimary) {
            Database::execute("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?", [$roomNumber]);
        }

        // Add new assignment
        $rows = Database::execute(
            "INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (?, ?, ?, ?, ?)",
            [$roomNumber, $roomName, $categoryId, $isPrimary, $displayOrder]
        );

        if ($rows > 0) {
            $assignmentId = Database::lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Room-category assignment added successfully', 'id' => $assignmentId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add assignment']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function setPrimary($pdo, $input)
{
    $roomNumber = $input['room_number'] ?? null;
    $categoryId = $input['category_id'] ?? null;

    if ($roomNumber === null || $categoryId === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room number and category ID are required']);
        return;
    }

    try {
        Database::beginTransaction();

        // Remove primary status from all categories in this room
        Database::execute("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?", [$roomNumber]);

        // Set the specified category as primary
        $affected = Database::execute("UPDATE room_category_assignments SET is_primary = 1 WHERE room_number = ? AND category_id = ?", [$roomNumber, $categoryId]);

        if ($affected > 0) {
            Database::commit();
            echo json_encode(['success' => true, 'message' => 'Primary category updated successfully']);
        } else {
            Database::rollBack();
            echo json_encode(['success' => false, 'message' => 'Assignment not found']);
        }
    } catch (PDOException $e) {
        Database::rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateOrder($pdo, $input)
{
    $assignments = $input['assignments'] ?? [];

    if (empty($assignments)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Assignments array is required']);
        return;
    }

    try {
        Database::beginTransaction();

        foreach ($assignments as $assignment) {
            Database::execute("UPDATE room_category_assignments SET display_order = ? WHERE id = ?", [$assignment['display_order'], $assignment['id']]);
        }

        Database::commit();
        echo json_encode(['success' => true, 'message' => 'Display order updated successfully']);
    } catch (PDOException $e) {
        Database::rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateSingleOrder($pdo, $input)
{
    $assignmentId = $input['assignment_id'] ?? null;
    $displayOrder = $input['display_order'] ?? null;

    if ($assignmentId === null || $displayOrder === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Assignment ID and display order are required']);
        return;
    }

    try {
        $result = Database::execute("UPDATE room_category_assignments SET display_order = ? WHERE id = ?", [$displayOrder, $assignmentId]);

        if ($result > 0) {
            echo json_encode(['success' => true, 'message' => 'Display order updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Assignment not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo, $input)
{
    $assignmentId = $input['assignment_id'] ?? null;
    $roomNumber = $input['room_number'] ?? null;
    $categoryId = $input['category_id'] ?? null;

    if ($assignmentId === null && ($roomNumber === null || $categoryId === null)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Assignment ID or room number and category ID are required']);
        return;
    }

    try {
        if ($assignmentId !== null) {
            $result = Database::execute("DELETE FROM room_category_assignments WHERE id = ?", [$assignmentId]);
        } else {
            $result = Database::execute("DELETE FROM room_category_assignments WHERE room_number = ? AND category_id = ?", [$roomNumber, $categoryId]);
        }

        if ($result > 0) {
            echo json_encode(['success' => true, 'message' => 'Room-category assignment deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Assignment not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 