<?php
// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit();
}

// Helper function to calculate sum of costs
function sumCost($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['cost']);
    }
    return $total;
}

// Handle GET request - Fetch costs for an inventory item
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get inventory ID from URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', $path);
    $inventoryId = end($pathParts);
    
    try {
        // Fetch materials
        $stmt = $pdo->prepare('SELECT * FROM inventory_materials WHERE inventoryId = ?');
        $stmt->execute([$inventoryId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch labor
        $stmt = $pdo->prepare('SELECT * FROM inventory_labor WHERE inventoryId = ?');
        $stmt->execute([$inventoryId]);
        $labor = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch energy
        $stmt = $pdo->prepare('SELECT * FROM inventory_energy WHERE inventoryId = ?');
        $stmt->execute([$inventoryId]);
        $energy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $materialTotal = sumCost($materials);
        $laborTotal = sumCost($labor);
        $energyTotal = sumCost($energy);
        $grandTotal = $materialTotal + $laborTotal + $energyTotal;
        
        // Return JSON response
        echo json_encode([
            'materials' => $materials,
            'labor' => $labor,
            'energy' => $energy,
            'totals' => [
                'materials' => $materialTotal,
                'labor' => $laborTotal,
                'energy' => $energyTotal,
                'grand' => $grandTotal
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to fetch costs', 'details' => $e->getMessage()]);
    }
}

// Handle POST request - Add, update, or delete costs
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Check for required action parameter
    if (!isset($data['action'])) {
        echo json_encode(['error' => 'Action parameter is required']);
        exit();
    }
    
    $action = $data['action'];
    
    // Add cost
    if ($action === 'add-cost') {
        if (!isset($data['type']) || !isset($data['inventoryId']) || !isset($data['data'])) {
            echo json_encode(['error' => 'Missing required parameters']);
            exit();
        }
        
        $type = $data['type'];
        $inventoryId = $data['inventoryId'];
        $costData = $data['data'];
        
        try {
            // Add material cost
            if ($type === 'material') {
                $stmt = $pdo->prepare('INSERT INTO inventory_materials (inventoryId, name, cost) VALUES (?, ?, ?)');
                $stmt->execute([$inventoryId, $costData['name'], $costData['cost']]);
                $id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'material cost added successfully']);
            }
            // Add labor cost
            else if ($type === 'labor') {
                $stmt = $pdo->prepare('INSERT INTO inventory_labor (inventoryId, description, cost) VALUES (?, ?, ?)');
                $stmt->execute([$inventoryId, $costData['description'], $costData['cost']]);
                $id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'labor cost added successfully']);
            }
            // Add energy cost
            else if ($type === 'energy') {
                $stmt = $pdo->prepare('INSERT INTO inventory_energy (inventoryId, description, cost) VALUES (?, ?, ?)');
                $stmt->execute([$inventoryId, $costData['description'], $costData['cost']]);
                $id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'energy cost added successfully']);
            }
            else {
                echo json_encode(['error' => 'Invalid cost type']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => "Failed to add $type cost", 'details' => $e->getMessage()]);
        }
    }
    
    // Update cost
    else if ($action === 'update-cost') {
        if (!isset($data['type']) || !isset($data['id']) || !isset($data['data'])) {
            echo json_encode(['error' => 'Missing required parameters']);
            exit();
        }
        
        $type = $data['type'];
        $id = $data['id'];
        $costData = $data['data'];
        
        try {
            // Update material cost
            if ($type === 'material') {
                $stmt = $pdo->prepare('UPDATE inventory_materials SET name = ?, cost = ? WHERE id = ?');
                $stmt->execute([$costData['name'], $costData['cost'], $id]);
                echo json_encode(['success' => true, 'message' => 'material cost updated successfully']);
            }
            // Update labor cost
            else if ($type === 'labor') {
                $stmt = $pdo->prepare('UPDATE inventory_labor SET description = ?, cost = ? WHERE id = ?');
                $stmt->execute([$costData['description'], $costData['cost'], $id]);
                echo json_encode(['success' => true, 'message' => 'labor cost updated successfully']);
            }
            // Update energy cost
            else if ($type === 'energy') {
                $stmt = $pdo->prepare('UPDATE inventory_energy SET description = ?, cost = ? WHERE id = ?');
                $stmt->execute([$costData['description'], $costData['cost'], $id]);
                echo json_encode(['success' => true, 'message' => 'energy cost updated successfully']);
            }
            else {
                echo json_encode(['error' => 'Invalid cost type']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => "Failed to update $type cost", 'details' => $e->getMessage()]);
        }
    }
    
    // Delete cost
    else if ($action === 'delete-cost') {
        if (!isset($data['type']) || !isset($data['id'])) {
            echo json_encode(['error' => 'Missing required parameters']);
            exit();
        }
        
        $type = $data['type'];
        $id = $data['id'];
        
        try {
            // Delete material cost
            if ($type === 'material') {
                $stmt = $pdo->prepare('DELETE FROM inventory_materials WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'material cost deleted successfully']);
            }
            // Delete labor cost
            else if ($type === 'labor') {
                $stmt = $pdo->prepare('DELETE FROM inventory_labor WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'labor cost deleted successfully']);
            }
            // Delete energy cost
            else if ($type === 'energy') {
                $stmt = $pdo->prepare('DELETE FROM inventory_energy WHERE id = ?');
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'energy cost deleted successfully']);
            }
            else {
                echo json_encode(['error' => 'Invalid cost type']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => "Failed to delete $type cost", 'details' => $e->getMessage()]);
        }
    }
    
    else {
        echo json_encode(['error' => 'Invalid action']);
    }
}

else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>