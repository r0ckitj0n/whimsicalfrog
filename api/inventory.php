<?php

// Include the configuration file with correct path
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/inventory_helper.php';

try {
    // Create database connection using config
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Build query based on filters
    $filters = [
        'search' => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? '',
        'stock' => $_GET['stock'] ?? '',
        'status' => $_GET['status'] ?? WF_Constants::ITEM_STATUS_ACTIVE
    ];
    $sortBy = $_GET['sort'] ?? 'sku';
    $sortDir = $_GET['dir'] ?? 'asc';

    // Use InventoryHelper to get filtered and sorted items
    $items = InventoryHelper::getInventoryItems($filters, $sortBy, $sortDir);

    // Return inventory data
    Response::success($items);

} catch (PDOException $e) {
    // Handle database errors with detailed information
    error_log("Inventory API Database Error: " . $e->getMessage());
    Response::serverError('Database error', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    error_log("Inventory API General Error: " . $e->getMessage());
    Response::serverError('General error', $e->getMessage());
}
