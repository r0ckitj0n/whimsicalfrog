<?php
// Include the configuration file
require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $products = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get specific products by SKUs
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
                        img.image_path as imageUrl
                    FROM items i 
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                    WHERE i.sku IN ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($skus);
            $products = $stmt->fetchAll();
            
            // Format the data
            foreach ($products as &$product) {
                // Use retailPrice as the main price field
                if (isset($product['retailPrice'])) {
                    $product['price'] = floatval($product['retailPrice']);
                }
                
                // Set the image path - use primary image from item_images table
                if (!empty($product['imageUrl'])) {
                    $product['image'] = $product['imageUrl'];
                } else {
                    $product['image'] = 'images/items/placeholder.png';
                }
            }
        }
    } else {
        // GET request - return all products or by category
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
                        img.image_path as imageUrl
                    FROM items i
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                    WHERE i.category = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['category']]);
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
                        img.image_path as imageUrl
                    FROM items i
                    LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        
        $products = $stmt->fetchAll();
        
        // Format the data
        foreach ($products as &$product) {
            // Use retailPrice as the main price field
            if (isset($product['retailPrice'])) {
                $product['price'] = floatval($product['retailPrice']);
            }
            
            // Set the image path - use primary image from item_images table
            if (!empty($product['imageUrl'])) {
                $product['image'] = $product['imageUrl'];
            } else {
                $product['image'] = 'images/items/placeholder.png';
            }
        }
    }
    
    // Return products as JSON
    echo json_encode($products);
    
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