<?php
// Room-Category assignment management API

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Authentication check
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';

if (!(class_exists('AuthHelper') ? AuthHelper::isLoggedIn() : (function_exists('isLoggedIn') && isLoggedIn()))) {
    Response::forbidden('Access denied. Please log in.');
}

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
        Response::methodNotAllowed('Method not allowed');
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
            Response::error('Invalid action', null, 400);
            break;
    }

}

function getAllAssignments($pdo)
{
    try {
        $assignments = Database::queryAll(
             "SELECT 
                rca.*, 
                COALESCE(c.name, CONCAT('Category #', rca.category_id)) as category_name, 
                c.description as category_description
              FROM room_category_assignments rca 
              LEFT JOIN categories c ON rca.category_id = c.id 
              ORDER BY rca.room_number, rca.display_order"
        );
        Response::json(['success' => true, 'assignments' => $assignments]);
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function updateAssignment($pdo, $input)
{
    $id = $input['id'] ?? null;
    if ($id === null) {
        Response::error('Assignment id is required', null, 400);
        return;
    }
    try {
        $existing = Database::queryOne("SELECT * FROM room_category_assignments WHERE id = ?", [$id]);
        if (!$existing) {
            Response::notFound('Assignment not found');
            return;
        }
        $roomNumber = $input['room_number'] ?? $existing['room_number'];
        $roomName = $input['room_name'] ?? $existing['room_name'];
        $categoryId = $input['category_id'] ?? $existing['category_id'];
        $isPrimary = isset($input['is_primary']) ? (int)$input['is_primary'] : (int)$existing['is_primary'];
        $displayOrder = isset($input['display_order']) ? (int)$input['display_order'] : (int)$existing['display_order'];

        // Prevent duplicates
        $dup = Database::queryOne(
            "SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ? AND id <> ?",
            [$roomNumber, $categoryId, $id]
        );
        if ($dup && isset($dup['id'])) {
            Response::error('This room-category assignment already exists', null, 409);
            return;
        }

        Database::beginTransaction();
        if ($isPrimary === 1) {
            Database::execute("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?", [$roomNumber]);
        }
        Database::execute(
            "UPDATE room_category_assignments SET room_number = ?, room_name = ?, category_id = ?, is_primary = ?, display_order = ? WHERE id = ?",
            [$roomNumber, $roomName, $categoryId, $isPrimary, $displayOrder, $id]
        );
        Database::commit();
        Response::updated(['message' => 'Assignment updated successfully']);
    } catch (PDOException $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function getSummary($pdo)
{
    try {
        $summary = Database::queryAll(
            "SELECT 
                rca.room_number,
                rca.room_name,
                GROUP_CONCAT(COALESCE(c.name, CONCAT('Category #', rca.category_id)) ORDER BY rca.display_order SEPARATOR ', ') as categories,
                COUNT(*) as category_count,
                MAX(CASE WHEN rca.is_primary = 1 THEN COALESCE(c.name, CONCAT('Category #', rca.category_id)) END) as primary_category
             FROM room_category_assignments rca 
             LEFT JOIN categories c ON rca.category_id = c.id 
             GROUP BY rca.room_number, rca.room_name
             ORDER BY rca.room_number"
        );
        Response::json(['success' => true, 'summary' => $summary]);
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function getRoomAssignments($pdo)
{
    $roomNumber = $_GET['room_number'] ?? null;

    if ($roomNumber === null) {
        Response::error('Room number is required', null, 400);
        return;
    }

    try {
        $assignments = Database::queryAll(
            "SELECT 
                rca.*, 
                COALESCE(c.name, CONCAT('Category #', rca.category_id)) as category_name, 
                c.description as category_description
             FROM room_category_assignments rca 
             LEFT JOIN categories c ON rca.category_id = c.id 
             WHERE rca.room_number = ?
             ORDER BY rca.display_order",
            [$roomNumber]
        );
        Response::success(['assignments' => $assignments]);
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
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
        case 'update_assignment':
            updateAssignment($pdo, $input);
            break;
        default:
            Response::error('Invalid action', null, 400);
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
        Response::error('Room number and category ID are required', null, 400);
        return;
    }

    try {
        // Check if assignment already exists
        $exists = Database::queryOne("SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ?", [$roomNumber, $categoryId]);

        if ($exists) {
            Response::error('This room-category assignment already exists', null, 400);
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
            Response::success(['message' => 'Room-category assignment added successfully', 'id' => $assignmentId]);
        } else {
            Response::error('Failed to add assignment');
        }
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
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
            Response::updated(['message' => 'Primary category updated successfully']);
        } else {
            Database::rollBack();
            Response::notFound('Assignment not found');
        }
    } catch (PDOException $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function updateOrder($pdo, $input)
{
    $assignments = $input['assignments'] ?? [];

    if (empty($assignments)) {
        Response::error('Assignments array is required', null, 400);
        return;
    }

    try {
        Database::beginTransaction();

        foreach ($assignments as $assignment) {
            Database::execute("UPDATE room_category_assignments SET display_order = ? WHERE id = ?", [$assignment['display_order'], $assignment['id']]);
        }

        Database::commit();
        Response::updated(['message' => 'Display order updated successfully']);
    } catch (PDOException $e) {
        Database::rollBack();
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function updateSingleOrder($pdo, $input)
{
    $assignmentId = $input['assignment_id'] ?? null;
    $displayOrder = $input['display_order'] ?? null;

    if ($assignmentId === null || $displayOrder === null) {
        Response::error('Assignment ID and display order are required', null, 400);
        return;
    }

    try {
        $result = Database::execute("UPDATE room_category_assignments SET display_order = ? WHERE id = ?", [$displayOrder, $assignmentId]);

        if ($result > 0) {
            Response::updated(['message' => 'Display order updated successfully']);
        } else {
            Response::notFound('Assignment not found');
        }
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }
}

function handleDelete($pdo, $input)
{
    $assignmentId = $input['assignment_id'] ?? null;
    $roomNumber = $input['room_number'] ?? null;
    $categoryId = $input['category_id'] ?? null;

    if ($assignmentId === null && ($roomNumber === null || $categoryId === null)) {
        Response::error('Assignment ID or room number and category ID are required', null, 400);
        return;
    }

    try {
        if ($assignmentId !== null) {
            $result = Database::execute("DELETE FROM room_category_assignments WHERE id = ?", [$assignmentId]);
        } else {
            $result = Database::execute("DELETE FROM room_category_assignments WHERE room_number = ? AND category_id = ?", [$roomNumber, $categoryId]);
        }

        if ($result > 0) {
            Response::success(['message' => 'Room-category assignment deleted successfully']);
        } else {
            Response::notFound('Assignment not found');
        }
    } catch (PDOException $e) {
        Response::serverError('Database error: ' . $e->getMessage());
    }
}
?> 