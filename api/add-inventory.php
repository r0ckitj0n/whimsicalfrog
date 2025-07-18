<?php

// Include the configuration file
require_once __DIR__ . '/config.php';

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

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Handle different data formats - check if it's the new format with sku, name, etc.
    if (isset($data['sku']) && isset($data['name'])) {
        // New format from admin interface
        $sku = $data['sku'];
        $name = $data['name'];
        $category = $data['category'] ?? '';
        $stockLevel = intval($data['stockLevel'] ?? 0);
        $reorderPoint = intval($data['reorderPoint'] ?? 5);
        $costPrice = floatval($data['costPrice'] ?? 0);
        $retailPrice = floatval($data['retailPrice'] ?? 0);
        $description = $data['description'] ?? '';

        // Insert using items table with sku as primary key
        $stmt = $pdo->prepare('INSERT INTO items (sku, name, category, stockLevel, reorderPoint, costPrice, retailPrice, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $result = $stmt->execute([$sku, $name, $category, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Item added successfully',
                'id' => $sku
            ]);
        } else {
            throw new Exception('Failed to add item');
        }
    } else {
        // Legacy format - convert for backwards compatibility
        $requiredFields = ['itemName'];
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
        $stockLevel = intval($data['quantity'] ?? 0);
        $sku = $data['unit'] ?? 'WF-GEN-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $description = $data['notes'] ?? '';
        $reorderPoint = min(floor($stockLevel / 2), 5);
        $costPrice = floatval($data['costPerUnit'] ?? 0);
        $retailPrice = $costPrice * 1.5; // Default markup

        // Insert using items table
        $stmt = $pdo->prepare('INSERT INTO items (sku, name, category, stockLevel, reorderPoint, costPrice, retailPrice, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $result = $stmt->execute([$sku, $name, $category, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description]);

        if ($result) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Item added successfully',
                'id' => $sku
            ]);
        } else {
            throw new Exception('Failed to add item');
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
