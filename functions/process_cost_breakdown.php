<?php

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow access from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database configuration (project root /api/config.php)
require_once dirname(__DIR__) . '/api/config.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];
$input = null; // To store parsed JSON body

// Parse JSON input for POST and PUT requests
if ($method === 'POST' || $method === 'PUT') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    // Check for JSON parsing errors, but allow empty body for PUT if it's not invalid JSON
    if ($input === null && json_last_error() !== JSON_ERROR_NONE && !($method === 'PUT' && empty($inputJSON))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input: ' . json_last_error_msg()]);
        exit;
    }
    // Ensure $input is an array if JSON was valid but empty or resulted in non-array (e.g. "null" string)
    $input = is_array($input) ? $input : [];
}


// Determine parameters based on method and source priority
$inventoryId = null;
$costType = null;
$id = null;

if ($method === 'POST' || $method === 'PUT') {
    $inventoryId = isset($input['inventoryId']) ? $input['inventoryId'] : (isset($_GET['inventoryId']) ? $_GET['inventoryId'] : null);
    $costType    = isset($input['costType']) ? $input['costType'] : (isset($_GET['costType']) ? $_GET['costType'] : null);
    if ($method === 'PUT') {
        $id = isset($input['id']) ? $input['id'] : (isset($_GET['id']) ? $_GET['id'] : null);
    }
} else { // For GET, DELETE, OPTIONS
    $inventoryId = isset($_GET['inventoryId']) ? $_GET['inventoryId'] : null;
    $costType    = isset($_GET['costType']) ? $_GET['costType'] : null;
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? $_GET['id'] : null;
    }
}

// Centralized inventoryId validation
if ($method !== 'OPTIONS' && empty($inventoryId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing or invalid inventoryId parameter'
    ]);
    exit;
}

// Helper function to validate cost type
function validateCostType($type)
{
    $validTypes = ['materials', 'labor', 'energy', 'equipment', 'all'];
    return in_array($type, $validTypes);
}

// Helper function to get table name from cost type
function getTableName($type)
{
    switch ($type) {
        case 'materials':
            return 'inventory_materials';
        case 'labor':
            return 'inventory_labor';
        case 'energy':
            return 'inventory_energy';
        case 'equipment':
            return 'inventory_equipment';
        default:
            return null;
    }
}

// Helper function to validate input data
function validateCostData($data, $type)
{
    // Common validation
    if (!isset($data['cost']) || !is_numeric($data['cost']) || floatval($data['cost']) < 0) {
        return 'Cost must be a valid non-negative number';
    }

    // Type-specific validation
    switch ($type) {
        case 'materials':
            if (!isset($data['name']) || empty(trim($data['name']))) {
                return 'Material name is required';
            }
            break;

        case 'labor':
        case 'energy':
        case 'equipment':
            if (!isset($data['description']) || empty(trim($data['description']))) {
                return 'Description is required';
            }
            break;

        default:
            // This case should ideally not be reached if validateCostType is called first
            return 'Invalid cost type for data validation';
    }

    return true;
}

try {
    // Create database connection
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Handle different HTTP methods
    switch ($method) {
        case 'GET':
            // Get cost breakdown data for an inventory item
            if ($costType === 'all' || empty($costType)) { // Modified to check empty for $costType
                // Get all cost types

                // Get materials costs
                $materials = Database::queryAll("SELECT * FROM inventory_materials WHERE sku = ?", [$inventoryId]);

                // Get labor costs
                $labor = Database::queryAll("SELECT * FROM inventory_labor WHERE sku = ?", [$inventoryId]);

                // Get energy costs
                $energy = Database::queryAll("SELECT * FROM inventory_energy WHERE sku = ?", [$inventoryId]);

                // Get equipment costs
                $equipment = Database::queryAll("SELECT * FROM inventory_equipment WHERE sku = ?", [$inventoryId]);

                // Calculate totals
                $materialTotal = 0;
                foreach ($materials as $material) {
                    $materialTotal += floatval($material['cost']);
                }

                $laborTotal = 0;
                foreach ($labor as $laborItem) {
                    $laborTotal += floatval($laborItem['cost']);
                }

                $energyTotal = 0;
                foreach ($energy as $energyItem) {
                    $energyTotal += floatval($energyItem['cost']);
                }

                $equipmentTotal = 0;
                foreach ($equipment as $equipmentItem) {
                    $equipmentTotal += floatval($equipmentItem['cost']);
                }

                $suggestedCost = $materialTotal + $laborTotal + $energyTotal + $equipmentTotal;

                // Return all cost data
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'materials' => $materials,
                        'labor' => $labor,
                        'energy' => $energy,
                        'equipment' => $equipment,
                        'totals' => [
                            'materialTotal' => $materialTotal,
                            'laborTotal' => $laborTotal,
                            'energyTotal' => $energyTotal,
                            'equipmentTotal' => $equipmentTotal,
                            'suggestedCost' => $suggestedCost
                        ]
                    ]
                ]);

            } else {
                // Get specific cost type
                if (!validateCostType($costType)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid cost type'
                    ]);
                    exit;
                }

                $tableName = getTableName($costType);
                if (!$tableName) { // Should not happen if validateCostType is used
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Internal error: Invalid table for cost type']);
                    exit;
                }
                $items = Database::queryAll("SELECT * FROM $tableName WHERE sku = ?", [$inventoryId]);

                // Calculate total
                $total = 0;
                foreach ($items as $item) {
                    $total += floatval($item['cost']);
                }

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'items' => $items,
                        'total' => $total
                    ]
                ]);
            }
            break;

        case 'POST':
            // Check if this is a clear_all action
            if (isset($input['action']) && $input['action'] === 'clear_all') {
                // Clear all cost breakdown data for this inventory item
                try {
                    Database::beginTransaction();

                    // Delete from all cost tables
                    $tables = ['inventory_materials', 'inventory_labor', 'inventory_energy', 'inventory_equipment'];
                    $deletedCount = 0;

                    foreach ($tables as $table) {
                        $affected = Database::execute("DELETE FROM $table WHERE sku = ?", [$inventoryId]);
                        if ($affected > 0) {
                            $deletedCount += $affected;
                        }
                    }

                    Database::commit();

                    echo json_encode([
                        'success' => true,
                        'message' => "All cost breakdown data cleared successfully ($deletedCount items removed)",
                        'deletedCount' => $deletedCount
                    ]);
                } catch (Exception $e) {
                    Database::rollBack();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to clear cost breakdown data: ' . $e->getMessage()
                    ]);
                }
                break;
            }

            // Add a new cost item
            // $input is already parsed and validated for JSON structure
            // $inventoryId and $costType are determined globally

            if (empty($costType) || !validateCostType($costType)) { // Use globally set $costType
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid or missing cost type in request'
                ]);
                exit;
            }

            $tableName = getTableName($costType);
            if (!$tableName) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Internal error: Invalid table for cost type']);
                exit;
            }

            // Validate input data from $input
            $validationResult = validateCostData($input, $costType);
            if ($validationResult !== true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $validationResult
                ]);
                exit;
            }

            // Insert new cost item
            if ($costType === 'materials') {
                $affected = Database::execute("INSERT INTO $tableName (sku, name, cost) VALUES (?, ?, ?)", [$inventoryId, $input['name'], $input['cost']]);
            } else {
                $affected = Database::execute("INSERT INTO $tableName (sku, description, cost) VALUES (?, ?, ?)", [$inventoryId, $input['description'], $input['cost']]);
            }

            if ($affected > 0) {
                $newId = Database::lastInsertId();

                // Get the newly created item
                $newItem = Database::queryOne("SELECT * FROM $tableName WHERE id = ?", [$newId]);

                echo json_encode([
                    'success' => true,
                    'message' => ucfirst(rtrim($costType, 's')) . ' cost added successfully', // Make singular
                    'data' => $newItem
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to add ' . rtrim($costType, 's') . ' cost'
                ]);
            }
            break;

        case 'PUT':
            // Update an existing cost item
            // $input is already parsed
            // $inventoryId, $costType, $id are determined globally

            if (empty($id)) { // Use globally set $id
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing id parameter for update'
                ]);
                exit;
            }

            if (empty($costType) || !validateCostType($costType)) { // Use globally set $costType
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid or missing cost type for update'
                ]);
                exit;
            }

            $tableName = getTableName($costType);
            if (!$tableName) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Internal error: Invalid table for cost type']);
                exit;
            }

            // Validate input data from $input
            $validationResult = validateCostData($input, $costType);
            if ($validationResult !== true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $validationResult
                ]);
                exit;
            }

            // Verify the item exists and belongs to the specified inventory
            $exists = Database::queryOne("SELECT id FROM $tableName WHERE id = ? AND sku = ?", [$id, $inventoryId]);
            if (!$exists) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Item not found or does not belong to the specified inventory'
                ]);
                exit;
            }

            // Update the item
            if ($costType === 'materials') {
                $affected = Database::execute("UPDATE $tableName SET name = ?, cost = ? WHERE id = ?", [$input['name'], $input['cost'], $id]);
            } else {
                $affected = Database::execute("UPDATE $tableName SET description = ?, cost = ? WHERE id = ?", [$input['description'], $input['cost'], $id]);
            }

            if ($affected > 0) {
                // Get the updated item
                $updatedItem = Database::queryOne("SELECT * FROM $tableName WHERE id = ?", [$id]);

                echo json_encode([
                    'success' => true,
                    'message' => ucfirst(rtrim($costType, 's')) . ' cost updated successfully',
                    'data' => $updatedItem
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update ' . rtrim($costType, 's') . ' cost'
                ]);
            }
            break;

        case 'DELETE':
            // Delete a cost item
            // $inventoryId, $costType, $id are determined globally (from $_GET for DELETE)
            if (empty($id)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing id parameter for deletion'
                ]);
                exit;
            }

            if (empty($costType) || !validateCostType($costType)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid or missing cost type for deletion'
                ]);
                exit;
            }

            $tableName = getTableName($costType);
            if (!$tableName) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Internal error: Invalid table for cost type']);
                exit;
            }

            // Verify the item exists and belongs to the specified inventory
            $exists = Database::queryOne("SELECT id FROM $tableName WHERE id = ? AND sku = ?", [$id, $inventoryId]);
            if (!$exists) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Item not found or does not belong to the specified inventory for deletion'
                ]);
                exit;
            }

            // Delete the item
            $affected = Database::execute("DELETE FROM $tableName WHERE id = ?", [$id]);

            if ($affected > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst(rtrim($costType, 's')) . ' cost deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to delete ' . rtrim($costType, 's') . ' cost'
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    error_log("Database Error in process_cost_breakdown.php: " . $e->getMessage()); // Log detailed error
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred. Please check logs.',
        // 'message' => $e->getMessage() // Optionally include for client, but be cautious in production
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    error_log("General Error in process_cost_breakdown.php: " . $e->getMessage()); // Log detailed error
    echo json_encode([
        'success' => false,
        'error' => 'A general error occurred. Please check logs.',
        // 'message' => $e->getMessage() // Optionally include for client
    ]);
    exit;
}
