<?php

// Include the configuration file with correct path
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    // Create database connection using config
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Build query based on filters
    $query = "SELECT * FROM items"; // This will include costPrice and retailPrice fields
    $params = [];
    $whereConditions = [];

    // Add search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(name LIKE ? OR category LIKE ? OR sku LIKE ? OR description LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    // Add category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $whereConditions[] = "category = ?";
        $params[] = $_GET['category'];
    }

    // Add WHERE clause if conditions exist
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }

    // Add sorting
    $query .= " ORDER BY name ASC";

    // Execute query
    $inventory = Database::queryAll($query, $params);

    // Map database field names to camelCase for JavaScript compatibility
    $mappedInventory = array_map(function ($item) {
        // Map stock_level to stockLevel if it exists
        if (isset($item['stock_level'])) {
            $item['stockLevel'] = $item['stock_level'];
            unset($item['stock_level']); // Remove the underscore version
        }

        // Also ensure retailPrice and costPrice are properly mapped if needed
        // (they might already be correct in the database)

        return $item;
    }, $inventory);

    // Return inventory data as array
    Response::success($mappedInventory);

} catch (PDOException $e) {
    // Handle database errors with detailed information
    error_log("Inventory API Database Error: " . $e->getMessage());
    Response::serverError('Database error', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    error_log("Inventory API General Error: " . $e->getMessage());
    Response::serverError('General error', $e->getMessage());
}
