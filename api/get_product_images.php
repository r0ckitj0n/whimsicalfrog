<?php
/**
 * Get Product Images API
 * 
 * Returns all images for a specific product with primary designation and sort order
 */

require_once '../includes/product_image_helpers.php';
header('Content-Type: application/json');

try {
    $productId = $_GET['productId'] ?? '';
    
    if (empty($productId)) {
        echo json_encode(['success' => false, 'error' => 'Product ID is required']);
        exit;
    }
    
    // Use the helper function which has fallback support
    $images = getProductImages($productId);
    
    // Add additional file information
    foreach ($images as &$image) {
        if ($image['file_exists']) {
            $fullPath = __DIR__ . '/../' . $image['image_path'];
            if (file_exists($fullPath)) {
                $image['file_size'] = filesize($fullPath);
                $imageInfo = getimagesize($fullPath);
                if ($imageInfo) {
                    $image['width'] = $imageInfo[0];
                    $image['height'] = $imageInfo[1];
                    $image['mime_type'] = $imageInfo['mime'];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'productId' => $productId,
        'images' => $images,
        'totalImages' => count($images),
        'primaryImage' => !empty($images) ? $images[0] : null
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_product_images: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve images']);
}
?> 