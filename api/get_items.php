<?php
// Include the configuration file
require_once __DIR__ . '/config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Suppress warnings/notices from leaking into JSON and buffer early output
ini_set('display_errors', 0);
ob_start();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['item_ids']) && is_array($input['item_ids']) && !empty($input['item_ids'])) {
            $skus = $input['item_ids'];  // Now expecting SKUs instead of IDs

            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($skus) - 1) . '?';

            // Query to get specific items by SKU with primary image from item_images table
            $sql = "SELECT 
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

            $items = Database::queryAll($sql, $skus);

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
            $sql = "SELECT 
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
            $items = Database::queryAll($sql, [$_GET['category']]);
        } else {
            // Return all items if no category specified
            $sql = "SELECT 
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
            $items = Database::queryAll($sql);
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

    // Discard any accidental output captured earlier and return JSON
    if (ob_get_length() !== false) {
        ob_end_clean();
    }
    echo json_encode($items);

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>