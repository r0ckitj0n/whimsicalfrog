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
        // Get specific products by IDs
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['product_ids']) && is_array($input['product_ids']) && !empty($input['product_ids'])) {
            $productIds = $input['product_ids'];
            
            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            // Query to get specific products with their primary image
            $sql = "SELECT p.*, 
                           (SELECT pi.filename 
                            FROM product_images pi 
                            WHERE pi.product_id = p.id AND pi.is_primary = 1 
                            LIMIT 1) as primary_image
                    FROM products p 
                    WHERE p.id IN ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($productIds);
            $products = $stmt->fetchAll();
            
            // Format the data
            foreach ($products as &$product) {
                if (isset($product['basePrice'])) {
                    $product['price'] = floatval($product['basePrice']);
                }
                
                // Set the image path - prefer primary_image, fallback to old image field
                if (!empty($product['primary_image'])) {
                    $product['image'] = 'images/products/' . $product['primary_image'];
                } elseif (!empty($product['image'])) {
                    // Keep existing image path if no primary image found
                    $product['image'] = $product['image'];
                } else {
                    $product['image'] = 'images/products/placeholder.png';
                }
            }
        }
    } else {
        // GET request - return all products (for backward compatibility)
        $sql = "SELECT p.*, 
                       (SELECT pi.filename 
                        FROM product_images pi 
                        WHERE pi.product_id = p.id AND pi.is_primary = 1 
                        LIMIT 1) as primary_image
                FROM products p";
        
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
        
        // Format the data
        foreach ($products as &$product) {
            if (isset($product['basePrice'])) {
                $product['price'] = floatval($product['basePrice']);
            }
            
            // Set the image path
            if (!empty($product['primary_image'])) {
                $product['image'] = 'images/products/' . $product['primary_image'];
            } elseif (!empty($product['image'])) {
                $product['image'] = $product['image'];
            } else {
                $product['image'] = 'images/products/placeholder.png';
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