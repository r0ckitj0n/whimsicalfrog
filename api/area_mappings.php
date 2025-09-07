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
if (!is_array($input)) { $input = []; }

function wf_normalize_room_type($value) {
    if ($value === null || $value === '') return '';
    if (preg_match('/^room(\d+)$/i', (string)$value, $m)) return 'room' . (int)$m[1];
    return 'room' . (int)$value;
}

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

function handleGet($pdo) {
    $action = $_GET['action'] ?? '';
    // Prefer 'room', fallback to 'room_type'
    $roomType = null;
    if (isset($_GET['room']) && $_GET['room'] !== '') {
        $roomType = wf_normalize_room_type($_GET['room']);
    } elseif (isset($_GET['room_type'])) {
        $roomType = $_GET['room_type'];
    }

    try {
        switch ($action) {
            case 'get_room_coordinates':
                if (!$roomType) {
                    echo json_encode(['success' => false, 'message' => 'room is required']);
                    return;
                }
                $map = Database::queryOne("SELECT coordinates FROM room_maps WHERE room_type = ? AND is_active = 1 LIMIT 1", [$roomType]);
                $coords = $map ? json_decode($map['coordinates'], true) : [];
                echo json_encode(['success' => true, 'coordinates' => is_array($coords) ? $coords : []]);
                return;
            case 'get_mappings':
                if (!$roomType) {
                    echo json_encode(['success' => false, 'message' => 'room is required']);
                    return;
                }
                $rows = Database::queryAll("SELECT id, room_type, area_selector, mapping_type, item_id, category_id, display_order FROM area_mappings WHERE room_type = ? AND is_active = 1 ORDER BY display_order, id", [$roomType]);
                echo json_encode(['success' => true, 'mappings' => $rows]);
                return;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    switch ($action) {
        case 'add_mapping':
            addMapping($pdo, $input);
            return;
        case 'swap':
            swapMappings($input);
            return;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            return;
    }
}

function addMapping($pdo, $input)
{
    // Prefer 'room' param, fallback to legacy 'room_type'
    $roomType = isset($input['room']) && $input['room'] !== ''
        ? wf_normalize_room_type($input['room'])
        : ($input['room_type'] ?? null);
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
        $exists = Database::queryOne("SELECT id FROM area_mappings WHERE room_type = ? AND area_selector = ? AND is_active = 1", [$roomType, $areaSelector]);

        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'This area already has a mapping. Use swap or update instead.']);
            return;
        }

        // Add new mapping
        $rows = Database::execute(
            "INSERT INTO area_mappings (room_type, area_selector, mapping_type, item_id, category_id, display_order) VALUES (?, ?, ?, ?, ?, ?)",
            [$roomType, $areaSelector, $mappingType, $itemId, $categoryId, $displayOrder]
        );

        if ($rows > 0) {
            $mappingId = Database::lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Area mapping added successfully', 'id' => $mappingId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add mapping']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function swapMappings($input)
{
    $area1Id = $input['area1_id'] ?? null;
    $area2Id = $input['area2_id'] ?? null;

    if (!$area1Id || !$area2Id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Both area mapping IDs are required for swapping']);
        return;
    }

    try {
        Database::beginTransaction();

        // Get both mappings
        $mappings = Database::queryAll("SELECT * FROM area_mappings WHERE id IN (?, ?) AND is_active = 1", [$area1Id, $area2Id]);

        if (count($mappings) !== 2) {
            Database::rollBack();
            echo json_encode(['success' => false, 'message' => 'One or both mappings not found']);
            return;
        }

        $mapping1 = $mappings[0];
        $mapping2 = $mappings[1];

        // Swap the mappings
        // Update first mapping with second mapping's data
        Database::execute(
            "UPDATE area_mappings SET mapping_type = ?, item_id = ?, category_id = ? WHERE id = ?",
            [$mapping2['mapping_type'], $mapping2['item_id'], $mapping2['category_id'], $mapping1['id']]
        );

        // Update second mapping with first mapping's data
        Database::execute(
            "UPDATE area_mappings SET mapping_type = ?, item_id = ?, category_id = ? WHERE id = ?",
            [$mapping1['mapping_type'], $mapping1['item_id'], $mapping1['category_id'], $mapping2['id']]
        );

        Database::commit();
        echo json_encode(['success' => true, 'message' => 'Area mappings swapped successfully']);

    } catch (PDOException $e) {
        Database::rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePut($input)
{
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

        $result = Database::execute("UPDATE area_mappings SET " . implode(', ', $updateFields) . " WHERE id = ?", $updateValues);

        if ($result > 0) {
            echo json_encode(['success' => true, 'message' => 'Area mapping updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mapping not found or no changes made']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo, $input)
{
    $mappingId = $input['id'] ?? null;

    if (!$mappingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mapping ID is required']);
        return;
    }

    try {
        $result = Database::execute("UPDATE area_mappings SET is_active = 0 WHERE id = ?", [$mappingId]);

        if ($result > 0) {
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