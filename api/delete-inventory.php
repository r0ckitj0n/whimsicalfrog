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

    // Validate SKU field
    if (!isset($data['sku']) || empty($data['sku'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Item SKU is required']);
        exit;
    }

    // Extract SKU
    $sku = $data['sku'];

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if item exists
    $row = Database::queryOne('SELECT COUNT(*) AS c FROM items WHERE sku = ?', [$sku]);
    if ((int)($row['c'] ?? 0) === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        exit;
    }

    // Delete item
    $affected = Database::execute('DELETE FROM items WHERE sku = ?', [$sku]);

    if ($affected !== false) {
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Item deleted successfully',
            'sku' => $sku
        ]);
    } else {
        throw new Exception('Failed to delete item');
    }

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
}
