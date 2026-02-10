<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

try {
    // Get POST data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON', null, 400);
    }

    // Validate SKU field
    if (!isset($data['sku']) || empty($data['sku'])) {
        Response::error('Item SKU is required', null, 400);
    }

    // Extract SKU
    $sku = trim((string)$data['sku']);
    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        Response::error('Invalid SKU format', null, 422);
    }

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
        Response::notFound('Item not found');
    }

    // Delete item
    $affected = Database::execute('DELETE FROM items WHERE sku = ?', [$sku]);

    if ($affected !== false) {
        // Return success response
        Response::success(['message' => 'Item deleted successfully', 'sku' => $sku]);
    } else {
        throw new Exception('Failed to delete item');
    }

} catch (PDOException $e) {
    // Handle database errors
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
