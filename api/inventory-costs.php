<?php
/**
 * Inventory Costs API
 * 
 * This API endpoint retrieves cost breakdown data for a specific inventory item,
 * including materials, labor, and energy costs. It calculates the total suggested cost
 * based on all components.
 * 
 * GET Parameters:
 * - inventoryId: The ID of the inventory item to fetch costs for
 * 
 * Response:
 * - success: Boolean indicating if the request was successful
 * - data: Object containing cost breakdown information
 *   - materials: Array of material costs
 *   - labor: Array of labor costs
 *   - energy: Array of energy costs
 *   - totals: Object with subtotals and suggested cost
 * - error: Error message if request failed
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database configuration
require_once __DIR__ . '/config.php';

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Validate inventoryId parameter
    if (!isset($_GET['inventoryId']) || empty($_GET['inventoryId'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing inventoryId parameter'
        ]);
        exit;
    }

    $inventoryId = $_GET['inventoryId'];
    
    try {
        // Connect to database
        try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
        
        // Fetch materials costs
        $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
        $materialStmt->execute([$inventoryId]);
        $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch labor costs
        $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
        $laborStmt->execute([$inventoryId]);
        $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch energy costs
        $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
        $energyStmt->execute([$inventoryId]);
        $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $materialTotal = array_reduce($materials, function($sum, $item) {
            return $sum + floatval($item['cost']);
        }, 0);
        
        $laborTotal = array_reduce($labor, function($sum, $item) {
            return $sum + floatval($item['cost']);
        }, 0);
        
        $energyTotal = array_reduce($energy, function($sum, $item) {
            return $sum + floatval($item['cost']);
        }, 0);
        
        $suggestedCost = $materialTotal + $laborTotal + $energyTotal;
        
        // Format response
        $response = [
            'success' => true,
            'data' => [
                'materials' => $materials,
                'labor' => $labor,
                'energy' => $energy,
                'totals' => [
                    'materialTotal' => round($materialTotal, 2),
                    'laborTotal' => round($laborTotal, 2),
                    'energyTotal' => round($energyTotal, 2),
                    'suggestedCost' => round($suggestedCost, 2)
                ]
            ]
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred',
            'details' => $e->getMessage()
        ]);
    }
} else {
    // Handle non-GET requests
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
