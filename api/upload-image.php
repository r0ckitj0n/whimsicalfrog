<?php
// Include the configuration file
require_once __DIR__ . '/config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if this is a multipart/form-data request
if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image file uploaded or invalid upload']);
    exit;
}

try {
    // Create database connection
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Get the uploaded file
    $uploadedFile = $_FILES['image'];
    $sku = $_POST['sku'] ?? '';
    $category = $_POST['category'] ?? 'items';
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($uploadedFile['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Only image files are allowed']);
        exit;
    }
    
    // Check file size (10MB limit)
    if ($uploadedFile['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File size exceeds 10MB limit']);
        exit;
    }
    
    // Generate filename
    $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    $unique = substr(md5(uniqid()), 0, 6);
    $filename = $sku . '-' . $unique . '.' . $ext;
    
    // Set destination path
    $uploadDir = __DIR__ . '/../images/items/';
    $uploadPath = $uploadDir . $filename;
    $relativeUrl = 'images/items/' . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
        chmod($uploadDir, 0777);
    }
    
    // Move uploaded file to temporary location first
    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Set file permissions
    chmod($uploadPath, 0644);
    
    $dualFormatCreated = false;
    $pngFilename = null;
    $webpFilename = null;
    
    // Create dual format (PNG + WebP) for browser compatibility
    try {
        require_once __DIR__ . '/ai_image_processor.php';
        $processor = new AIImageProcessor();
        
        $dualFormatOptions = [
            'webp_quality' => 90,
            'png_compression' => 1,
            'preserve_transparency' => true,
            'force_png' => true
        ];
        
        $formatResult = $processor->convertToDualFormat($uploadPath, $dualFormatOptions);
        
        if ($formatResult['success']) {
            $dualFormatCreated = true;
            
            // Generate dual format filenames
            $baseFilename = $sku . '-' . $unique;
            $pngFilename = $baseFilename . '.png';
            $webpFilename = $baseFilename . '.webp';
            
            $pngPath = $uploadDir . $pngFilename;
            $webpPath = $uploadDir . $webpFilename;
            
            // Save PNG version (compliance)
            if ($formatResult['png_path'] && file_exists($formatResult['png_path'])) {
                copy($formatResult['png_path'], $pngPath);
                chmod($pngPath, 0644);
            }
            
            // Save WebP version (optimized)
            if ($formatResult['webp_path'] && file_exists($formatResult['webp_path'])) {
                copy($formatResult['webp_path'], $webpPath);
                chmod($webpPath, 0644);
            }
            
            // Use WebP as primary filename, but keep original for compatibility
            $filename = $webpFilename;
            $relativeUrl = 'images/items/' . $webpFilename;
            
            // Clean up temporary processing files
            if (file_exists($formatResult['png_path']) && $formatResult['png_path'] !== $pngPath) {
                unlink($formatResult['png_path']);
            }
            if (file_exists($formatResult['webp_path']) && $formatResult['webp_path'] !== $webpPath) {
                unlink($formatResult['webp_path']);
            }
        }
    } catch (Exception $e) {
        error_log("Dual format conversion failed: " . $e->getMessage());
        // Continue with original image if dual format conversion fails
    }
    
    // Add to database
    $stmt = $pdo->prepare('INSERT INTO item_images (sku, image_filename, image_url, is_primary) VALUES (?, ?, ?, ?)');
    $isPrimary = $_POST['is_primary'] ?? 0;
    $result = $stmt->execute([$sku, $filename, $relativeUrl, $isPrimary]);
    
    if (!$result) {
        // If database insert fails, clean up all uploaded files
        if (file_exists($uploadPath)) unlink($uploadPath);
        if ($pngFilename && file_exists($uploadDir . $pngFilename)) unlink($uploadDir . $pngFilename);
        if ($webpFilename && file_exists($uploadDir . $webpFilename)) unlink($uploadDir . $webpFilename);
        throw new Exception('Failed to save image information to database');
    }
    
    $imageId = $pdo->lastInsertId();
    
    // If this is set as primary, update other images for this SKU
    if ($isPrimary) {
        $stmt = $pdo->prepare('UPDATE item_images SET is_primary = 0 WHERE sku = ? AND id != ?');
        $stmt->execute([$sku, $imageId]);
    }
    
    // Calculate file sizes
    $originalSize = $uploadedFile['size'];
    $pngSize = $pngFilename && file_exists($uploadDir . $pngFilename) ? filesize($uploadDir . $pngFilename) : 0;
    $webpSize = $webpFilename && file_exists($uploadDir . $webpFilename) ? filesize($uploadDir . $webpFilename) : 0;
    
    $response = [
        'success' => true,
        'message' => 'Image uploaded successfully',
        'image' => [
            'id' => $imageId,
            'filename' => $filename,
            'url' => $relativeUrl,
            'is_primary' => $isPrimary
        ]
    ];
    
    // Add dual format information if created
    if ($dualFormatCreated) {
        $response['dual_format'] = [
            'created' => true,
            'png_filename' => $pngFilename,
            'webp_filename' => $webpFilename,
            'file_sizes' => [
                'original' => formatFileSize($originalSize),
                'png' => formatFileSize($pngSize),
                'webp' => formatFileSize($webpSize)
            ],
            'compression_savings' => [
                'webp_vs_original' => calculateFileSizeReduction($originalSize, $webpSize) . '%',
                'webp_vs_png' => calculateFileSizeReduction($pngSize, $webpSize) . '%'
            ]
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Upload failed',
        'details' => $e->getMessage()
    ]);
}

/**
 * Calculate file size reduction percentage
 */
function calculateFileSizeReduction($originalSize, $newSize) {
    if ($originalSize <= 0) return 0;
    return round((($originalSize - $newSize) / $originalSize) * 100, 1);
}

/**
 * Format file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes <= 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor(log($bytes, 1024));
    $factor = min($factor, count($units) - 1);
    
    return round($bytes / (1024 ** $factor), 1) . ' ' . $units[$factor];
}
