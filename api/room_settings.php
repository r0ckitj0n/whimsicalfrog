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
    $action = $_GET['action'] ?? 'get_all';
    $roomNumber = $_GET['room_number'] ?? null;
    
    try {
        if ($action === 'get_all') {
            // Get all room settings ordered by display order
            $stmt = $pdo->prepare("
                SELECT * FROM room_settings 
                WHERE is_active = 1 
                ORDER BY display_order, room_number
            ");
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            
        } elseif ($action === 'get_room' && $roomNumber !== null) {
            // Get specific room settings
            $stmt = $pdo->prepare("
                SELECT * FROM room_settings 
                WHERE room_number = ? AND is_active = 1
            ");
            $stmt->execute([$roomNumber]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($room) {
                echo json_encode(['success' => true, 'room' => $room]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
            }
            
        } elseif ($action === 'get_navigation_rooms') {
            // Get rooms that should appear in navigation (rooms 2-6)
            $stmt = $pdo->prepare("
                SELECT room_number, room_name, door_label, description 
                FROM room_settings 
                WHERE room_number BETWEEN 2 AND 6 AND is_active = 1 
                ORDER BY display_order, room_number
            ");
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? null;
    
    if ($action === 'create_room') {
        createRoom($pdo, $input);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function createRoom($pdo, $input) {
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
        $checkStmt = $pdo->prepare("SELECT id FROM room_settings WHERE room_number = ?");
        $checkStmt->execute([$input['room_number']]);
        
        if ($checkStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Room number already exists']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO room_settings (room_number, room_name, door_label, description, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['room_number'],
            trim($input['room_name']),
            trim($input['door_label']),
            $input['description'] ?? '',
            $input['display_order'] ?? 0
        ]);
        
        $roomId = $pdo->lastInsertId();
        
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

function handlePut($pdo, $input) {
    $action = $input['action'] ?? null;
    
    if ($action === 'update_room') {
        updateRoom($pdo, $input);
    } elseif ($action === 'update_display_order') {
        updateDisplayOrder($pdo, $input);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function updateRoom($pdo, $input) {
    $requiredFields = ['room_number', 'room_name', 'door_label'];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE room_settings 
            SET room_name = ?, door_label = ?, description = ?, display_order = ?
            WHERE room_number = ?
        ");
        
        $result = $stmt->execute([
            trim($input['room_name']),
            trim($input['door_label']),
            $input['description'] ?? '',
            $input['display_order'] ?? 0,
            $input['room_number']
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Room updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Room not found or no changes made']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateDisplayOrder($pdo, $input) {
    if (!isset($input['rooms']) || !is_array($input['rooms'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid rooms data']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE room_settings SET display_order = ? WHERE room_number = ?");
        
        foreach ($input['rooms'] as $room) {
            if (isset($room['room_number']) && isset($room['display_order'])) {
                $stmt->execute([$room['display_order'], $room['room_number']]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Display order updated successfully']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo, $input) {
    $roomNumber = $input['room_number'] ?? null;
    
    if ($roomNumber === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room number is required']);
        return;
    }
    
    // Prevent deletion of core rooms (0-6)
    if ($roomNumber >= 0 && $roomNumber <= 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Core rooms (0-6) cannot be deleted']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE room_settings SET is_active = 0 WHERE room_number = ?");
        $result = $stmt->execute([$roomNumber]);
        
        if ($result && $stmt->rowCount() > 0) {
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