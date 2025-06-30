<?php
/**
 * AI Image Processing API
 * 
 * Handles requests to automatically crop images to object edges
 * Integrates with Add Item wizard and admin inventory system
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_image_processor.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check authentication
session_start();
if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || 
    !isset($_SESSION['user']['role']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'process_image':
            handleImageProcessing($input);
            break;
            
        case 'process_uploaded_image':
            handleUploadedImageProcessing($input);
            break;
            
        case 'get_processing_status':
            handleProcessingStatus($input);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("AI Image Processing Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle image processing request
 */
function handleImageProcessing($input) {
    $imagePath = $input['imagePath'] ?? '';
    $sku = $input['sku'] ?? '';
    $options = $input['options'] ?? [];
    
    if (empty($imagePath)) {
        throw new Exception('Image path is required');
    }
    
    // Convert relative path to absolute
    if (!file_exists($imagePath)) {
        $absolutePath = __DIR__ . '/../' . $imagePath;
        if (file_exists($absolutePath)) {
            $imagePath = $absolutePath;
        } else {
            throw new Exception('Image file not found: ' . $imagePath);
        }
    }
    
    // Initialize processor
    $processor = new AIImageProcessor();
    
    // Set default options
    $defaultOptions = [
        'convertToWebP' => true,
        'quality' => 90,
        'preserveTransparency' => true,
        'useAI' => true,
        'fallbackTrimPercent' => 0.05
    ];
    
    $processingOptions = array_merge($defaultOptions, $options);
    
    // Process the image
    $result = $processor->processImage($imagePath, $processingOptions);
    
    if ($result['success']) {
        // Update database if SKU provided
        if (!empty($sku) && !empty($result['processed_path'])) {
            updateImageDatabase($sku, $imagePath, $result['processed_path'], $result);
        }
        
        // Convert absolute paths back to relative for response
        if (!empty($result['processed_path'])) {
            $result['processed_path'] = str_replace(__DIR__ . '/../', '', $result['processed_path']);
        }
        if (!empty($result['original_path'])) {
            $result['original_path'] = str_replace(__DIR__ . '/../', '', $result['original_path']);
        }
    }
    
    echo json_encode($result);
}

/**
 * Handle processing of recently uploaded images
 */
function handleUploadedImageProcessing($input) {
    $sku = $input['sku'] ?? '';
    $imageId = $input['imageId'] ?? '';
    $options = $input['options'] ?? [];
    
    if (empty($sku)) {
        throw new Exception('SKU is required');
    }
    
    // Get image information from database
    global $dsn, $user, $pass, $options;
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    if (!empty($imageId)) {
        // Process specific image
        $stmt = $pdo->prepare("SELECT * FROM item_images WHERE id = ? AND sku = ?");
        $stmt->execute([$imageId, $sku]);
        $images = [$stmt->fetch(PDO::FETCH_ASSOC)];
    } else {
        // Process all images for the SKU
        $stmt = $pdo->prepare("SELECT * FROM item_images WHERE sku = ? ORDER BY sort_order ASC");
        $stmt->execute([$sku]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($images) || !$images[0]) {
        throw new Exception('No images found for processing');
    }
    
    $processor = new AIImageProcessor();
    $results = [];
    
    foreach ($images as $image) {
        $imagePath = __DIR__ . '/../' . $image['image_path'];
        
        if (!file_exists($imagePath)) {
            $results[] = [
                'success' => false,
                'image_id' => $image['id'],
                'error' => 'Image file not found: ' . $image['image_path']
            ];
            continue;
        }
        
        try {
            // Set default options
            $defaultOptions = [
                'convertToWebP' => true,
                'quality' => 90,
                'preserveTransparency' => true,
                'useAI' => true,
                'fallbackTrimPercent' => 0.05
            ];
            
            $processingOptions = array_merge($defaultOptions, $options);
            
            // Process the image
            $result = $processor->processImage($imagePath, $processingOptions);
            $result['image_id'] = $image['id'];
            $result['original_image_path'] = $image['image_path'];
            
            if ($result['success']) {
                // Update database record
                updateImageDatabaseRecord($image['id'], $sku, $imagePath, $result['processed_path'], $result);
                
                // Convert absolute paths back to relative
                if (!empty($result['processed_path'])) {
                    $result['processed_path'] = str_replace(__DIR__ . '/../', '', $result['processed_path']);
                }
            }
            
            $results[] = $result;
            
        } catch (Exception $e) {
            $results[] = [
                'success' => false,
                'image_id' => $image['id'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed_images' => count($results),
        'results' => $results
    ]);
}

/**
 * Handle processing status requests
 */
function handleProcessingStatus($input) {
    $sku = $input['sku'] ?? '';
    
    if (empty($sku)) {
        throw new Exception('SKU is required');
    }
    
    global $dsn, $user, $pass, $options;
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $stmt = $pdo->prepare("
        SELECT id, image_path, processed_with_ai, processing_date, ai_trim_data 
        FROM item_images 
        WHERE sku = ? 
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$sku]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $status = [
        'total_images' => count($images),
        'processed_images' => 0,
        'ai_processed' => 0,
        'images' => []
    ];
    
    foreach ($images as $image) {
        $imageStatus = [
            'id' => $image['id'],
            'image_path' => $image['image_path'],
            'processed_with_ai' => (bool)$image['processed_with_ai'],
            'processing_date' => $image['processing_date'],
            'has_ai_data' => !empty($image['ai_trim_data'])
        ];
        
        if ($image['processed_with_ai']) {
            $status['ai_processed']++;
        }
        
        if ($image['processed_with_ai'] || !empty($image['ai_trim_data'])) {
            $status['processed_images']++;
        }
        
        $status['images'][] = $imageStatus;
    }
    
    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
}

/**
 * Update image database record with processing information
 */
function updateImageDatabase($sku, $originalPath, $processedPath, $processingData) {
    global $dsn, $user, $pass, $options;
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Convert paths to relative
    $relativeOriginal = str_replace(__DIR__ . '/../', '', $originalPath);
    $relativeProcessed = str_replace(__DIR__ . '/../', '', $processedPath);
    
    // Update the image record
    $stmt = $pdo->prepare("
        UPDATE item_images 
        SET image_path = ?, processed_with_ai = 1, original_path = ?, processing_date = NOW(), ai_trim_data = ?
        WHERE sku = ? AND image_path = ?
    ");
    
    $stmt->execute([
        $relativeProcessed,
        $relativeOriginal,
        json_encode($processingData),
        $sku,
        $relativeOriginal
    ]);
    
    // If no rows were updated, try to find by processed path
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("
            UPDATE item_images 
            SET processed_with_ai = 1, original_path = ?, processing_date = NOW(), ai_trim_data = ?
            WHERE sku = ? AND image_path = ?
        ");
        
        $stmt->execute([
            $relativeOriginal,
            json_encode($processingData),
            $sku,
            $relativeProcessed
        ]);
    }
}

/**
 * Update specific image database record
 */
function updateImageDatabaseRecord($imageId, $sku, $originalPath, $processedPath, $processingData) {
    global $dsn, $user, $pass, $options;
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Convert paths to relative
    $relativeOriginal = str_replace(__DIR__ . '/../', '', $originalPath);
    $relativeProcessed = str_replace(__DIR__ . '/../', '', $processedPath);
    
    $stmt = $pdo->prepare("
        UPDATE item_images 
        SET image_path = ?, processed_with_ai = 1, original_path = ?, processing_date = NOW(), ai_trim_data = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $relativeProcessed,
        $relativeOriginal,
        json_encode($processingData),
        $imageId
    ]);
}
?> 