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

try {
    try {
        $pdo = Database::getInstance();
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
        $stmt = $pdo->query("
            SELECT rca.*, c.name as category_name, c.description as category_description
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            ORDER BY rca.room_number, rca.display_order
        ");
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'assignments' => $assignments]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getSummary($pdo)
{
    try {
        $stmt = $pdo->query("
            SELECT 
                rca.room_number,
                rca.room_name,
                GROUP_CONCAT(c.name ORDER BY rca.display_order SEPARATOR ', ') as categories,
                COUNT(*) as category_count,
                MAX(CASE WHEN rca.is_primary = 1 THEN c.name END) as primary_category
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            GROUP BY rca.room_number, rca.room_name
            ORDER BY rca.room_number
        ");
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $pdo->prepare("
            SELECT rca.*, c.name as category_name, c.description as category_description
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            WHERE rca.room_number = ?
            ORDER BY rca.display_order
        ");
        $stmt->execute([$roomNumber]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $checkStmt = $pdo->prepare("SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ?");
        $checkStmt->execute([$roomNumber, $categoryId]);

        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This room-category assignment already exists']);
            return;
        }

        // If setting as primary, remove primary status from other categories in this room
        if ($isPrimary) {
            $updateStmt = $pdo->prepare("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?");
            $updateStmt->execute([$roomNumber]);
        }

        // Add new assignment
        $stmt = $pdo->prepare("
            INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$roomNumber, $roomName, $categoryId, $isPrimary, $displayOrder])) {
            $assignmentId = $pdo->lastInsertId();
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
        $pdo->beginTransaction();

        // Remove primary status from all categories in this room
        $clearStmt = $pdo->prepare("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?");
        $clearStmt->execute([$roomNumber]);

        // Set the specified category as primary
        $setPrimaryStmt = $pdo->prepare("UPDATE room_category_assignments SET is_primary = 1 WHERE room_number = ? AND category_id = ?");
        $setPrimaryStmt->execute([$roomNumber, $categoryId]);

        if ($setPrimaryStmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Primary category updated successfully']);
        } else {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'Assignment not found']);
        }
    } catch (PDOException $e) {
        $pdo->rollback();
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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE room_category_assignments SET display_order = ? WHERE id = ?");

        foreach ($assignments as $assignment) {
            $stmt->execute([$assignment['display_order'], $assignment['id']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Display order updated successfully']);
    } catch (PDOException $e) {
        $pdo->rollback();
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
        $stmt = $pdo->prepare("UPDATE room_category_assignments SET display_order = ? WHERE id = ?");
        $result = $stmt->execute([$displayOrder, $assignmentId]);

        if ($result && $stmt->rowCount() > 0) {
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
            $stmt = $pdo->prepare("DELETE FROM room_category_assignments WHERE id = ?");
            $result = $stmt->execute([$assignmentId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM room_category_assignments WHERE room_number = ? AND category_id = ?");
            $result = $stmt->execute([$roomNumber, $categoryId]);
        }

        if ($result && $stmt->rowCount() > 0) {
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