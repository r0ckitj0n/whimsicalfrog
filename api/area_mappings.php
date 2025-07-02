<?php
// Area mappings management API
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
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
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
// handlePost function moved to api_handlers_extended.php for centralization

function addMapping($pdo, $input) {
    $roomType = $input['room_type'] ?? null;
    $areaSelector = $input['area_selector'] ?? null;
    $mappingType = $input['mapping_type'] ?? null;
    $itemId = $input['item_id'] ?? null;
    $categoryId = $input['category_id'] ?? null;
    $displayOrder = $input['display_order'] ?? 0;
    
    if (!$roomType || !$areaSelector || !$mappingType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room type, area selector, and mapping type are required']);
        return;
    }
    
    if ($mappingType === 'item' && !$itemId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Item ID is required for item mapping']);
        return;
    }
    
    if ($mappingType === 'category' && !$categoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category ID is required for category mapping']);
        return;
    }
    
    try {
        // Check if mapping already exists for this area
        $checkStmt = $pdo->prepare("SELECT id FROM area_mappings WHERE room_type = ? AND area_selector = ? AND is_active = 1");
        $checkStmt->execute([$roomType, $areaSelector]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This area already has a mapping. Use swap or update instead.']);
            return;
        }
        
        // Add new mapping
        $stmt = $pdo->prepare("
            INSERT INTO area_mappings (room_type, area_selector, mapping_type, item_id, category_id, display_order) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$roomType, $areaSelector, $mappingType, $itemId, $categoryId, $displayOrder])) {
            $mappingId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Area mapping added successfully', 'id' => $mappingId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add mapping']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function swapMappings($pdo, $input) {
    $area1Id = $input['area1_id'] ?? null;
    $area2Id = $input['area2_id'] ?? null;
    
    if (!$area1Id || !$area2Id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Both area mapping IDs are required for swapping']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get both mappings
        $stmt = $pdo->prepare("SELECT * FROM area_mappings WHERE id IN (?, ?) AND is_active = 1");
        $stmt->execute([$area1Id, $area2Id]);
        $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($mappings) !== 2) {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => 'One or both mappings not found']);
            return;
        }
        
        $mapping1 = $mappings[0];
        $mapping2 = $mappings[1];
        
        // Swap the mappings
        $updateStmt = $pdo->prepare("
            UPDATE area_mappings 
            SET mapping_type = ?, item_id = ?, category_id = ? 
            WHERE id = ?
        ");
        
        // Update first mapping with second mapping's data
        $updateStmt->execute([
            $mapping2['mapping_type'], 
            $mapping2['item_id'], 
            $mapping2['category_id'], 
            $mapping1['id']
        ]);
        
        // Update second mapping with first mapping's data
        $updateStmt->execute([
            $mapping1['mapping_type'], 
            $mapping1['item_id'], 
            $mapping1['category_id'], 
            $mapping2['id']
        ]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Area mappings swapped successfully']);
        
    } catch (PDOException $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePut($pdo, $input) {
    $mappingId = $input['id'] ?? null;
    $mappingType = $input['mapping_type'] ?? null;
    $itemId = $input['item_id'] ?? null;
    $categoryId = $input['category_id'] ?? null;
    $displayOrder = $input['display_order'] ?? null;
    
    if (!$mappingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mapping ID is required']);
        return;
    }
    
    try {
        $updateFields = [];
        $updateValues = [];
        
        if ($mappingType !== null) {
            $updateFields[] = 'mapping_type = ?';
            $updateValues[] = $mappingType;
        }
        if ($itemId !== null) {
            $updateFields[] = 'item_id = ?';
            $updateValues[] = $itemId;
        }
        if ($categoryId !== null) {
            $updateFields[] = 'category_id = ?';
            $updateValues[] = $categoryId;
        }
        if ($displayOrder !== null) {
            $updateFields[] = 'display_order = ?';
            $updateValues[] = $displayOrder;
        }
        
        if (empty($updateFields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        
        $updateValues[] = $mappingId;
        
        $stmt = $pdo->prepare("UPDATE area_mappings SET " . implode(', ', $updateFields) . " WHERE id = ?");
        $result = $stmt->execute($updateValues);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Area mapping updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mapping not found or no changes made']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo, $input) {
    $mappingId = $input['id'] ?? null;
    
    if (!$mappingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mapping ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE area_mappings SET is_active = 0 WHERE id = ?");
        $result = $stmt->execute([$mappingId]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Area mapping removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mapping not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 