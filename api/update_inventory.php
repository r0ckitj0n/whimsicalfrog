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

    // Handle field updates
    if (isset($data['sku']) && isset($data['field']) && isset($data['value'])) {
        $sku = $data['sku'];
        $field = $data['field'];
        $value = $data['value'];

        // Validate field
        $allowedFields = ['name', 'category', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description'];
        if (!in_array($field, $allowedFields)) {
            Response::error('Invalid field', null, 400);
        }

        // Update the field
        $affected = Database::execute("UPDATE items SET `$field` = ? WHERE sku = ?", [$value, $sku]);

        if ($affected > 0) {
            Response::updated();
        } else {
            // No rows affected: either no change needed or item missing
            $exists = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
            if ($exists) {
                Response::noChanges();
            } else {
                Response::notFound('Item not found');
            }
        }
    } else {
        // Handle full item updates
        $requiredFields = ['sku', 'name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                Response::error(ucfirst($field) . ' is required', null, 400);
            }
        }

        $sku = $data['sku'];
        $name = $data['name'];
        $category = $data['category'] ?? '';
        $stockLevel = intval($data['stockLevel'] ?? 0);
        $reorderPoint = intval($data['reorderPoint'] ?? 5);
        $costPrice = floatval($data['costPrice'] ?? 0);
        $retailPrice = floatval($data['retailPrice'] ?? 0);
        $description = $data['description'] ?? '';

        // Update the item (full update)
        $affected = Database::execute('UPDATE items SET name = ?, category = ?, stockLevel = ?, reorderPoint = ?, costPrice = ?, retailPrice = ?, description = ? WHERE sku = ?', [$name, $category, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description, $sku]);

        if ($affected > 0) {
            Response::updated();
        } else {
            // No-op update or item missing
            $exists = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
            if ($exists) {
                Response::noChanges();
            } else {
                Response::notFound('Item not found');
            }
        }
    }

} catch (PDOException $e) {
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
