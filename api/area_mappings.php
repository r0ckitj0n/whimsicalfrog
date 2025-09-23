<?php
// Area mappings management API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration (absolute)
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

// Ensure table exists with expected schema (room_number preferred)
try {
    Database::execute("CREATE TABLE IF NOT EXISTS area_mappings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_number VARCHAR(50) NOT NULL,
        area_selector VARCHAR(255) NOT NULL,
        mapping_type ENUM('item','category') NOT NULL,
        item_id INT NULL,
        item_sku VARCHAR(64) NULL,
        category_id INT NULL,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_room_number (room_number),
        INDEX idx_item_sku (item_sku),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Backfill room_number for legacy installs and ensure index exists
    try {
        Database::execute("ALTER TABLE area_mappings ADD COLUMN room_number VARCHAR(50) NOT NULL");
    } catch (Exception $e) {
    }
    try {
        Database::execute("CREATE INDEX idx_room_number ON area_mappings (room_number)");
    } catch (Exception $e) {
    }
    try {
        Database::execute("ALTER TABLE area_mappings ADD COLUMN item_sku VARCHAR(64) NULL");
    } catch (Exception $e) {
    }
    try {
        Database::execute("CREATE INDEX idx_item_sku ON area_mappings (item_sku)");
    } catch (Exception $e) {
    }
    // If legacy column room_type exists, migrate values into room_number where missing
    try {
        // This UPDATE will fail harmlessly if room_type doesn't exist
        Database::execute("UPDATE area_mappings SET room_number = SUBSTRING(room_type, 5) WHERE (room_number IS NULL OR room_number = '') AND room_type REGEXP '^room[0-9]+$'");
    } catch (Exception $e) { /* ignore */
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to ensure schema: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

function wf_normalize_room_number($value)
{
    $v = trim((string)$value);
    if ($v === '') {
        return null;
    }
    $lv = strtolower($v);
    if ($lv === 'main') {
        return '0';
    }
    if ($lv === 'landing') {
        return 'A';
    }
    if (preg_match('/^room(\d+)$/i', $v, $m)) {
        return (string)((int)$m[1]);
    }
    if (is_numeric($v)) {
        return (string)((int)$v);
    }
    return $v; // allow non-numeric room codes like 'A'
}

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost($input);
        break;
    case 'PUT':
        handlePut($input);
        break;
    case 'DELETE':
        handleDelete($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
// handlePost function moved to api_handlers_extended.php for centralization

/**
 * Check if a given table has a specific column.
 */
function wf_has_column($table, $column)
{
    try {
        $row = Database::queryOne("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
        return $row && (int)$row['c'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

function handleGet()
{
    $action = $_GET['action'] ?? '';
    $roomNumber = null;
    if (isset($_GET['room'])) {
        $roomNumber = wf_normalize_room_number($_GET['room']);
    } elseif (isset($_GET['room_number'])) {
        $roomNumber = wf_normalize_room_number($_GET['room_number']);
    }

    try {
        switch ($action) {
            case 'get_room_coordinates':
                if ($roomNumber === null) {
                    echo json_encode(['success' => false, 'message' => 'room is required']);
                    return;
                }
                $map = Database::queryOne("SELECT coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1", [$roomNumber]);
                $coords = $map ? json_decode($map['coordinates'], true) : [];
                echo json_encode(['success' => true, 'coordinates' => is_array($coords) ? $coords : []]);
                return;

            case 'get_mappings':
                if ($roomNumber === null) {
                    echo json_encode(['success' => false, 'message' => 'room is required']);
                    return;
                }
                $rows = Database::queryAll("SELECT id, room_number, area_selector, mapping_type, item_id, item_sku, category_id, display_order FROM area_mappings WHERE is_active = 1 AND room_number = ? ORDER BY display_order, id", [$roomNumber]);
                echo json_encode(['success' => true, 'mappings' => $rows]);
                return;

            case 'get_live_view':
                if ($roomNumber === null) {
                    echo json_encode(['success' => false, 'message' => 'room is required']);
                    return;
                }
                try {
                    $coordsRow = Database::queryOne("SELECT coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1", [$roomNumber]);
                    $coords = $coordsRow ? json_decode($coordsRow['coordinates'] ?? '[]', true) : [];
                    if (!is_array($coords)) {
                        $coords = [];
                    }

                    $catRow = Database::queryOne("SELECT c.id AS category_id, c.name AS category_name FROM room_category_assignments rca JOIN categories c ON rca.category_id = c.id WHERE rca.room_number = ? AND rca.is_primary = 1 LIMIT 1", [$roomNumber]);
                    $categoryId = $catRow['category_id'] ?? null;
                    $categoryName = $catRow['category_name'] ?? '';

                    if (!$categoryId) {
                        if ($roomNumber === '0') {
                            $categoryName = 'T-Shirts';
                        } elseif ($roomNumber === 'A') {
                            $categoryName = 'New Arrivals';
                        }
                        if ($categoryName) {
                            $catIdRow = Database::queryOne('SELECT id FROM categories WHERE name = ? LIMIT 1', [$categoryName]);
                            if ($catIdRow) {
                                $categoryId = $catIdRow['id'];
                            }
                        }
                    }

                    $items = [];
                    if ($categoryId) {
                        $items = Database::queryAll("SELECT sku, name, category FROM items WHERE category_id = ? AND is_active = 1 ORDER BY display_order, sku ASC", [$categoryId]);
                    }
                    if (empty($items) && $categoryName) {
                        $items = Database::queryAll("SELECT sku, name, category FROM items WHERE category = ? AND is_active = 1 ORDER BY display_order, sku ASC", [$categoryName]);
                    }

                    $derived = [];
                    foreach ($items as $i => $it) {
                        $derived[] = [
                            'id' => null, 'room_number' => $roomNumber, 'area_selector' => '.area-' . ($i + 1),
                            'mapping_type' => 'item', 'item_id' => null, 'sku' => $it['sku'] ?? null,
                            'category_id' => null, 'display_order' => $i + 1, 'derived' => true
                        ];
                    }

                    echo json_encode(['success' => true, 'mappings' => $derived, 'category' => $categoryName, 'coordinates_count' => count($coords)]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'get_live_view failed: ' . $e->getMessage()]);
                }
                return;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                return;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unhandled error: ' . $e->getMessage()]);
    }
}
function handlePost($input)
{
    $action = $input['action'] ?? '';
    switch ($action) {
        case 'add_mapping':
            addMapping($input);
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

function addMapping($input)
{
    // Prefer 'room' param, fallback to legacy 'room_type'
    $roomNumber = isset($input['room']) && $input['room'] !== ''
        ? wf_normalize_room_number($input['room'])
        : wf_normalize_room_number($input['room_number'] ?? null);
    $areaSelector = $input['area_selector'] ?? null;
    $mappingType = $input['mapping_type'] ?? null;
    $itemId = $input['item_id'] ?? null;
    $itemSku = $input['item_sku'] ?? null;
    $categoryId = $input['category_id'] ?? null;
    $displayOrder = $input['display_order'] ?? 0;

    if ($roomNumber === null || !$areaSelector || !$mappingType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Room number, area selector, and mapping type are required']);
        return;
    }

    if ($mappingType === 'item' && !$itemId && !$itemSku) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Item ID or SKU is required for item mapping']);
        return;
    }

    if ($mappingType === 'category' && !$categoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category ID is required for category mapping']);
        return;
    }

    try {
        // Check if mapping already exists for this area
        $exists = Database::queryOne("SELECT id FROM area_mappings WHERE room_number = ? AND area_selector = ? AND is_active = 1", [$roomNumber, $areaSelector]);

        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Mapping already exists for this area']);
            return;
        }

        // Insert mapping
        $result = Database::execute("INSERT INTO area_mappings (room_number, area_selector, mapping_type, item_id, item_sku, category_id, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)", [
            $roomNumber, $areaSelector, $mappingType, $itemId, $itemSku, $categoryId, $displayOrder
        ]);

        if ($result > 0) {
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
    $itemSku = $input['item_sku'] ?? null;
    $categoryId = $input['category_id'] ?? null;
    $displayOrder = $input['display_order'] ?? null;
    $areaSelector = $input['area_selector'] ?? null;

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
        if ($areaSelector !== null) {
            $updateFields[] = 'area_selector = ?';
            $updateValues[] = $areaSelector;
        }
        if ($itemId !== null) {
            $updateFields[] = 'item_id = ?';
            $updateValues[] = $itemId;
        }
        if ($itemSku !== null) {
            $updateFields[] = 'item_sku = ?';
            $updateValues[] = $itemSku;
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

function handleDelete($input)
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