<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    // Get POST data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON', null, 400);
    }

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
        $affected = Database::execute('INSERT INTO items (sku, name, category, stockLevel, reorderPoint, costPrice, retailPrice, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$sku, $name, $category, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description]);

        if ($affected !== false) {
            Response::success(['message' => 'Item added successfully', 'id' => $sku]);
        } else {
            throw new Exception('Failed to add item');
        }
    } else {
        // Legacy format - convert for backwards compatibility
        $requiredFields = ['itemName'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                Response::error("Field '$field' is required", null, 400);
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
        $affected = Database::execute('INSERT INTO items (sku, name, category, stockLevel, reorderPoint, costPrice, retailPrice, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$sku, $name, $category, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description]);

        if ($affected !== false) {
            Response::success(['message' => 'Item added successfully', 'id' => $sku]);
        } else {
            throw new Exception('Failed to add item');
        }
    }

} catch (PDOException $e) {
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
