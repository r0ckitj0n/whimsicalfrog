<?php

// Include centralized systems
require_once 'api/config.php';
require_once 'includes/functions.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json(null, 200);
}

// Validate HTTP method using centralized function
Response::validateMethod('POST');

try {
    // Get and validate input using centralized method
    $data = Response::getJsonInput();

    // Validate required fields
    if (!isset($data['id']) || empty($data['id'])) {
        Response::error('Item ID is required');
    }

    // Extract and sanitize ID
    $id = trim($data['id']);

    // Get database connection using centralized system
    $pdo = Database::getInstance();

    // Check if item exists
    $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE sku = ?');
    $checkStmt->execute([$id]);
    if ($checkStmt->fetchColumn() == 0) {
        Response::notFound('Item not found');
    }

    // Delete item
    $stmt = $pdo->prepare('DELETE FROM items WHERE sku = ?');
    $result = $stmt->execute([$id]);

    if ($result) {
        // Log successful deletion using centralized logging
        Logger::info("Item deleted successfully", [
            'sku' => $id
        ]);

        // Return success response using centralized method
        Response::success([
            'id' => $id
        ], 'Item deleted successfully');
    } else {
        throw new Exception('Failed to delete inventory item');
    }

} catch (PDOException $e) {
    // Log database error using centralized logging
    Logger::databaseError($e, 'Item deletion failed', ['sku' => $id ?? 'unknown']);
    Response::serverError('Database error occurred');

} catch (Exception $e) {
    // Log general error using centralized logging
    Logger::error('Item deletion error: ' . $e->getMessage(), ['sku' => $id ?? 'unknown']);
    Response::serverError('Deletion failed');
}
