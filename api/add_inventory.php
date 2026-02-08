<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
header('Content-Type: application/json');

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
        $sku = trim((string)$data['sku']);
        $name = trim((string)$data['name']);
        if ($sku === '' || $name === '') {
            Response::error('Both sku and name are required', null, 422);
        }
        $category = $data['category'] ?? '';
        $stock_quantity = intval($data['stock_quantity'] ?? 0);
        $reorder_point = intval($data['reorder_point'] ?? 5);
        $cost_price = floatval($data['cost_price'] ?? 0);
        $retail_price = floatval($data['retail_price'] ?? 0);
        $description = $data['description'] ?? '';
        $status = $data['status'] ?? WF_Constants::ITEM_STATUS_DRAFT;
        $weight_oz = floatval($data['weight_oz'] ?? 0);
        $package_length_in = floatval($data['package_length_in'] ?? 0);
        $package_width_in = floatval($data['package_width_in'] ?? 0);
        $package_height_in = floatval($data['package_height_in'] ?? 0);

        $existing = Database::queryOne('SELECT sku FROM items WHERE sku = ? LIMIT 1', [$sku]);
        $alreadyExists = !empty($existing);

        // Upsert allows image-first flows where an item shell may already exist.
        $affected = Database::execute(
            'INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, status, weight_oz, package_length_in, package_width_in, package_height_in)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                category = VALUES(category),
                stock_quantity = VALUES(stock_quantity),
                reorder_point = VALUES(reorder_point),
                cost_price = VALUES(cost_price),
                retail_price = VALUES(retail_price),
                description = VALUES(description),
                status = VALUES(status),
                weight_oz = VALUES(weight_oz),
                package_length_in = VALUES(package_length_in),
                package_width_in = VALUES(package_width_in),
                package_height_in = VALUES(package_height_in)',
            [$sku, $name, $category, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status, $weight_oz, $package_length_in, $package_width_in, $package_height_in]
        );

        if ($affected !== false) {
            Response::success([
                'message' => $alreadyExists ? 'Item updated successfully' : 'Item added successfully',
                'id' => $sku,
                'sku' => $sku,
                'created' => !$alreadyExists,
                'updated' => $alreadyExists
            ]);
        } else {
            throw new Exception('Failed to add item');
        }
    } else {
        // Legacy format - convert for backwards compatibility
        $requiredFields = ['item_name'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                Response::error("Field '$field' is required", null, 400);
            }
        }

        // Extract data and map to database columns
        $name = $data['item_name'];
        $category = $data['category'] ?? '';
        $stock_quantity = intval($data['quantity'] ?? 0);
        $sku = $data['unit'] ?? 'WF-GEN-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $description = $data['notes'] ?? '';
        $reorder_point = min(floor($stock_quantity / 2), 5);
        $cost_price = floatval($data['costPerUnit'] ?? 0);
        $retail_price = $cost_price * 1.5; // Default markup
        $status = $data['status'] ?? WF_Constants::ITEM_STATUS_DRAFT;

        // Insert using items table
        $affected = Database::execute('INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$sku, $name, $category, $stock_quantity, $reorder_point, $cost_price, $retail_price, $description, $status]);

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
