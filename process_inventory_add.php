<?php
// Include the configuration file
require_once 'api/config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = ['itemName', 'quantity', 'unit', 'costPerUnit'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            exit;
        }
    }
    
    // Extract data and map to database columns
    $name = $data['itemName'];
    $category = $data['category'] ?? '';
    $stockLevel = floatval($data['quantity']);
    $sku = $data['unit']; // Using unit as SKU
    $description = $data['notes'] ?? '';
    
    // Generate a unique ID if needed
    $id = 'I' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Generate a product ID if needed
    $productId = 'P' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Set reorder point to half of stock level or 5, whichever is lower
    $reorderPoint = min(floor($stockLevel / 2), 5);
    
    // Default image URL - use products folder
            $imageUrl = 'images/items/placeholder.webp';
    
    // Create database connection using config
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Insert new inventory item using the correct column names
    $stmt = $pdo->prepare('INSERT INTO inventory (id, productId, name, description, sku, stockLevel, reorderPoint, imageUrl) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $result = $stmt->execute([$id, $productId, $name, $description, $sku, $stockLevel, $reorderPoint, $imageUrl]);
    
    if ($result) {
        // If category provided, update products table
        if (!empty($category)) {
            $pdo->prepare('UPDATE products SET productType = ? WHERE id = ?')->execute([$category, $productId]);
        }
        // Return success response
        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => 'Inventory item added successfully',
            'id' => $id
        ]);
    } else {
        throw new Exception('Failed to add inventory item');
    }
    
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>
