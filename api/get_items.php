<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $items = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get specific items by SKUs
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            Response::error('Invalid JSON', null, 400);
        }

        if (isset($input['item_ids']) && is_array($input['item_ids']) && !empty($input['item_ids'])) {
            $skus = $input['item_ids'];  // Now expecting SKUs instead of IDs

            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($skus) - 1) . '?';

            // Query to get specific items by SKU with primary image from item_images table
            $sqlDims = "SELECT 
                        i.sku,
                        i.name,
                        i.category,
                        i.description,
                        i.stockLevel,
                        i.reorderPoint,
                        i.costPrice,
                        i.retailPrice,
                        i.weight_oz,
                        i.package_length_in,
                        i.package_width_in,
                        i.package_height_in,
                        COALESCE(img.image_path, i.imageUrl) as imageUrl
                    FROM items i 
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                    WHERE i.sku IN ($placeholders)";
            $sqlBase = "SELECT 
                        i.sku,
                        i.name,
                        i.category,
                        i.description,
                        i.stockLevel,
                        i.reorderPoint,
                        i.costPrice,
                        i.retailPrice,
                        COALESCE(img.image_path, i.imageUrl) as imageUrl
                    FROM items i 
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                    WHERE i.sku IN ($placeholders)";

            try {
                $items = Database::queryAll($sqlDims, $skus);
            } catch (Exception $e) {
                $items = Database::queryAll($sqlBase, $skus);
            }

            // Format the data
            foreach ($items as &$item) {
                // Use retailPrice as the main price field
                if (isset($item['retailPrice'])) {
                    $item['price'] = floatval($item['retailPrice']);
                }

                // Set the image path - use primary image from item_images table
                if (!empty($item['imageUrl'])) {
                    $item['image'] = $item['imageUrl'];
                } else {
                    $item['image'] = 'images/items/placeholder.webp';
                }
            }
        }
    } else {
        // GET request - return all items or by category
        if (isset($_GET['category']) && !empty($_GET['category'])) {
            $sqlDims = "SELECT 
                        i.sku,
                        i.name,
                        i.category,
                        i.description,
                        i.stockLevel,
                        i.reorderPoint,
                        i.costPrice,
                        i.retailPrice,
                        i.weight_oz,
                        i.package_length_in,
                        i.package_width_in,
                        i.package_height_in,
                        COALESCE(img.image_path, i.imageUrl) as imageUrl
                    FROM items i
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                    WHERE i.category = ?";
            $sqlBase = "SELECT 
                        i.sku,
                        i.name,
                        i.category,
                        i.description,
                        i.stockLevel,
                        i.reorderPoint,
                        i.costPrice,
                        i.retailPrice,
                        COALESCE(img.image_path, i.imageUrl) as imageUrl
                    FROM items i
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                    WHERE i.category = ?";
            try {
                $items = Database::queryAll($sqlDims, [$_GET['category']]);
            } catch (Exception $e) {
                $items = Database::queryAll($sqlBase, [$_GET['category']]);
            }
        } else {
            // Return all items if no category specified
            $sqlDims = "SELECT 
                        i.sku,
                        i.name,
                        i.category,
                        i.description,
                        i.stockLevel,
                        i.reorderPoint,
                        i.costPrice,
                        i.retailPrice,
                        i.weight_oz,
                        i.package_length_in,
                        i.package_width_in,
                        i.package_height_in,
                        COALESCE(img.image_path, i.imageUrl) as imageUrl
                    FROM items i
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1";
            $sqlBase = "SELECT 
                        i.sku,
                        i.name,
                        i.category,
                        i.description,
                        i.stockLevel,
                        i.reorderPoint,
                        i.costPrice,
                        i.retailPrice,
                        COALESCE(img.image_path, i.imageUrl) as imageUrl
                    FROM items i
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1";
            try {
                $items = Database::queryAll($sqlDims);
            } catch (Exception $e) {
                $items = Database::queryAll($sqlBase);
            }
        }


        // Format the data
        foreach ($items as &$item) {
            // Use retailPrice as the main price field
            if (isset($item['retailPrice'])) {
                $item['price'] = floatval($item['retailPrice']);
            }

            // Set the image path - use primary image from item_images table
            if (!empty($item['imageUrl'])) {
                $item['image'] = $item['imageUrl'];
            } else {
                $item['image'] = 'images/items/placeholder.webp';
            }
        }
    }

    Response::success($items);

} catch (PDOException $e) {
    // Handle database errors
    Response::serverError('Database error occurred', $e->getMessage());
} catch (Exception $e) {
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
