<?php
/**
 * List Product Images Script
 * 
 * This script lists all existing product images to help understand what needs to be renamed
 */

header('Content-Type: application/json');

try {
    $results = [];
    $results[] = "Listing all product images...";
    
    // Check the images/products directory
    $imageDir = __DIR__ . '/../images/products/';
    
    if (!is_dir($imageDir)) {
        $results[] = "Images directory does not exist: " . $imageDir;
        echo json_encode(['success' => false, 'error' => 'Images directory not found', 'details' => $results]);
        exit;
    }
    
    $results[] = "Scanning directory: " . $imageDir;
    
    // Get all image files
    $imageExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $allFiles = scandir($imageDir);
    $imageFiles = [];
    
    foreach ($allFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, $imageExtensions)) {
            $imageFiles[] = $file;
        }
    }
    
    $results[] = "Found " . count($imageFiles) . " image files:";
    
    // Categorize images
    $productIdImages = [];
    $skuImages = [];
    $otherImages = [];
    
    foreach ($imageFiles as $file) {
        if (preg_match('/^P\d{3}[A-Z]?\.(png|jpg|jpeg|webp|gif)$/i', $file)) {
            $productIdImages[] = $file;
        } elseif (preg_match('/^WF-[A-Z]{2}-\d{3}[A-Z]?\.(png|jpg|jpeg|webp|gif)$/i', $file)) {
            $skuImages[] = $file;
        } else {
            $otherImages[] = $file;
        }
    }
    
    $results[] = "\n=== Product ID Format Images (P###[A].ext) ===";
    if (empty($productIdImages)) {
        $results[] = "No Product ID format images found";
    } else {
        foreach ($productIdImages as $file) {
            $results[] = "  " . $file;
        }
    }
    
    $results[] = "\n=== SKU Format Images (WF-XX-###[A].ext) ===";
    if (empty($skuImages)) {
        $results[] = "No SKU format images found";
    } else {
        foreach ($skuImages as $file) {
            $results[] = "  " . $file;
        }
    }
    
    $results[] = "\n=== Other Images ===";
    if (empty($otherImages)) {
        $results[] = "No other images found";
    } else {
        foreach ($otherImages as $file) {
            $results[] = "  " . $file;
        }
    }
    
    // Check file sizes and permissions
    $results[] = "\n=== File Details ===";
    foreach ($imageFiles as $file) {
        $filePath = $imageDir . $file;
        $size = filesize($filePath);
        $readable = is_readable($filePath) ? 'readable' : 'not readable';
        $writable = is_writable($filePath) ? 'writable' : 'not writable';
        $results[] = "{$file}: {$size} bytes, {$readable}, {$writable}";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Image listing completed',
        'details' => $results,
        'stats' => [
            'total_images' => count($imageFiles),
            'product_id_format' => count($productIdImages),
            'sku_format' => count($skuImages),
            'other_format' => count($otherImages)
        ],
        'images' => [
            'product_id_format' => $productIdImages,
            'sku_format' => $skuImages,
            'other_format' => $otherImages
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Image listing failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?> 