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
    $pdo = new PDO($dsn, $user, $pass, $options);
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

function handleGet($pdo) {
    $action = $_GET['action'] ?? '';
    $roomNumber = $_GET['room_number'] ?? null;
    $roomId = $_GET['room_id'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    
    try {
        if ($action === 'get_primary_category') {
            // Get primary category for a specific room
            $room = $roomNumber ?? $roomId;
            if ($room === null) {
                echo json_encode(['success' => false, 'message' => 'Room number or room_id is required']);
                return;
            }
            
            $stmt = $pdo->prepare("
                SELECT rca.*, c.name, c.description, c.id as category_id
                FROM room_category_assignments rca 
                JOIN categories c ON rca.category_id = c.id 
                WHERE rca.room_number = ? AND rca.is_primary = 1
                LIMIT 1
            ");
            $stmt->execute([$room]);
            $primaryCategory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($primaryCategory) {
                echo json_encode([
                    'success' => true, 
                    'category' => [
                        'id' => $primaryCategory['category_id'],
                        'name' => $primaryCategory['name'],
                        'description' => $primaryCategory['description']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No primary category found for this room']);
            }
        } elseif ($roomNumber !== null) {
            // Get categories for specific room
            $stmt = $pdo->prepare("
                SELECT rca.*, c.name as category_name, c.description as category_description
                FROM room_category_assignments rca 
                JOIN categories c ON rca.category_id = c.id 
                WHERE rca.room_number = ? 
                ORDER BY rca.is_primary DESC, rca.display_order ASC, c.name ASC
            ");
            $stmt->execute([$roomNumber]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'assignments' => $assignments]);
        } elseif ($categoryId !== null) {
            // Get rooms for specific category
            $stmt = $pdo->prepare("
                SELECT rca.*, c.name as category_name 
                FROM room_category_assignments rca 
                JOIN categories c ON rca.category_id = c.id 
                WHERE rca.category_id = ? 
                ORDER BY rca.is_primary DESC, rca.room_number ASC
            ");
            $stmt->execute([$categoryId]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'assignments' => $assignments]);
        } else {
            // Get all assignments with summary
            $stmt = $pdo->prepare("
                SELECT 
                    rca.room_number,
                    rca.room_name,
                    COUNT(*) as total_categories,
                    SUM(rca.is_primary) as primary_categories,
                    GROUP_CONCAT(
                        CASE WHEN rca.is_primary = 1 THEN c.name END 
                        ORDER BY rca.display_order 
                        SEPARATOR ', '
                    ) as primary_category_names,
                    GROUP_CONCAT(
                        c.name 
                        ORDER BY rca.is_primary DESC, rca.display_order ASC 
                        SEPARATOR ', '
                    ) as all_categories
                FROM room_category_assignments rca 
                JOIN categories c ON rca.category_id = c.id
                GROUP BY rca.room_number, rca.room_name 
                ORDER BY rca.room_number
            ");
            $stmt->execute();
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Also get available categories
            $categoriesStmt = $pdo->prepare("SELECT id, name, description FROM categories WHERE is_active = 1 ORDER BY display_order, name");
            $categoriesStmt->execute();
            $availableCategories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'summary' => $summary,
                'available_categories' => $availableCategories
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add_assignment':
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

function addAssignment($pdo, $input) {
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

function setPrimary($pdo, $input) {
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

function updateOrder($pdo, $input) {
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

function updateSingleOrder($pdo, $input) {
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

function handleDelete($pdo, $input) {
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