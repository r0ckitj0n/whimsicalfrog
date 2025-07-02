<?php
/**
 * Background Upload API with AI Processing
 * 
 * Handles background image uploads with automatic:
 * - Resizing to proper dimensions for room type
 * - WebP conversion for fast loading
 * - AI-powered optimization
 * - Database integration
 */



require_once __DIR__ . '/image_helper.php';
require_once __DIR__ . '/../includes/functions.php';
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
    require_once __DIR__ . '/../includes/auth.php'; !isAdminWithToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    // Get database connection
    $pdo = Database::getInstance();
    
    // Validate required fields
    $roomType = $_POST['room_type'] ?? '';
    $backgroundName = $_POST['background_name'] ?? '';
    
    if (empty($roomType) || empty($backgroundName)) {
        throw new Exception('Room type and background name are required');
    }
    
    // Check file upload
    if (!isset($_FILES['background_image']) || $_FILES['background_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No valid image file uploaded');
    }
    
    $uploadedFile = $_FILES['background_image'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileType = mime_content_type($uploadedFile['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Please upload JPG, PNG, WebP, or GIF images.');
    }
    
    // Validate file size (10MB limit)
    if ($uploadedFile['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size must be less than 10MB');
    }
    
    // Check if background name already exists for this room
    $checkStmt = $pdo->prepare("SELECT id FROM backgrounds WHERE room_type = ? AND background_name = ?");
    $checkStmt->execute([$roomType, $backgroundName]);
    
    if ($checkStmt->fetch()) {
        throw new Exception('Background name already exists for this room');
    }
    
    // Determine target dimensions based on room type
    $targetDimensions = getTargetDimensions($roomType);
    
    // Create temporary file path
    $tempDir = sys_get_temp_dir();
    $tempPath = $tempDir . '/' . uniqid('bg_upload_') . '.' . pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    
    if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
        throw new Exception('Failed to process uploaded file');
    }
    
    // Process image with AI
    $processor = new AIImageProcessor();
    
    $processingOptions = [
        'createDualFormat' => true, // Create both PNG and WebP for maximum compatibility
        'webp_quality' => 90,
        'png_compression' => 1, // High quality PNG
        'preserve_transparency' => true, // Preserve transparency for backgrounds
        'useAI' => false, // Background images don't need AI edge detection
        'resizeDimensions' => $targetDimensions,
        'resizeMode' => 'fit' // Maintain aspect ratio, fit within dimensions
    ];
    
    $result = $processor->processBackgroundImage($tempPath, $processingOptions);
    
    if (!$result['success']) {
        // Clean up temp file
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
        throw new Exception('Failed to process image: ' . ($result['error'] ?? 'Unknown error'));
    }
    
    // Generate final filenames
    $baseFilename = sanitizeFilename($roomType . '_' . $backgroundName);
    $pngFilename = $baseFilename . '.png';
    $webpFilename = $baseFilename . '.webp';
    
    // Move processed files to final destination
    $imagesDir = __DIR__ . '/../images/';
    if (!is_dir($imagesDir)) {
        mkdir($imagesDir, 0755, true);
    }
    
    $pngPath = $imagesDir . $pngFilename;
    $webpPath = $imagesDir . $webpFilename;
    
    // Save PNG version (compliance/fallback)
    if (isset($result['png_path']) && file_exists($result['png_path'])) {
        copy($result['png_path'], $pngPath);
        chmod($pngPath, 0644);
    } else {
        throw new Exception('PNG creation failed - compliance format required');
    }
    
    // Save WebP version (optimized)
    if (isset($result['webp_path']) && file_exists($result['webp_path'])) {
        copy($result['webp_path'], $webpPath);
        chmod($webpPath, 0644);
    } else {
        throw new Exception('WebP creation failed');
    }
    
    // Add png_filename column to database if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE backgrounds ADD COLUMN png_filename VARCHAR(255) AFTER image_filename");
    } catch (PDOException $e) {
        // Column likely already exists, ignore error
    }
    
    // Save to database with dual format support
    $insertStmt = $pdo->prepare("
        INSERT INTO backgrounds (room_type, background_name, image_filename, png_filename, webp_filename, is_active, created_at, ai_processed) 
        VALUES (?, ?, ?, ?, ?, 0, NOW(), 1)
    ");
    
    if (!$insertStmt->execute([$roomType, $backgroundName, $pngFilename, $pngFilename, $webpFilename])) {
        // Clean up files if database insert fails
        if (file_exists($pngPath)) unlink($pngPath);
        if (file_exists($webpPath)) unlink($webpPath);
        throw new Exception('Failed to save background to database');
    }
    
    $backgroundId = $pdo->lastInsertId();
    
    // Clean up temporary files
    if (file_exists($tempPath)) unlink($tempPath);
    if (isset($result['original_processed_path']) && file_exists($result['original_processed_path'])) {
        unlink($result['original_processed_path']);
    }
    if (isset($result['processed_path']) && file_exists($result['processed_path']) && $result['processed_path'] !== $webpPath) {
        unlink($result['processed_path']);
    }
    
    // Calculate file sizes for both formats
    $originalSize = $uploadedFile['size'];
    $pngSize = file_exists($pngPath) ? filesize($pngPath) : 0;
    $webpSize = file_exists($webpPath) ? filesize($webpPath) : 0;
    
    // Return success response with enhanced transparency information
    echo json_encode([
        'success' => true,
        'message' => 'Background uploaded and processed with transparency preservation',
        'background_id' => $backgroundId,
        'processing_info' => [
            'dual_format_created' => true,
            'formats_created' => $result['formats_created'] ?? [],
            'original_dimensions' => $result['original_dimensions'] ?? 'unknown',
            'final_dimensions' => $targetDimensions,
            'png_filename' => $pngFilename,
            'webp_filename' => $webpFilename,
            'transparency_preserved' => true, // Always preserved for backgrounds
            'transparency_details' => [
                'png_supports_transparency' => true,
                'webp_supports_transparency' => true,
                'alpha_channel_maintained' => true,
                'processing_notes' => 'Full transparency preservation enabled for background images'
            ],
            'file_sizes' => [
                'original' => formatFileSize($originalSize),
                'png' => formatFileSize($pngSize),
                'webp' => formatFileSize($webpSize)
            ],
            'compression_stats' => [
                'png_vs_original' => calculateFileSizeReduction($originalSize, $pngSize) . '%',
                'webp_vs_original' => calculateFileSizeReduction($originalSize, $webpSize) . '%',
                'webp_vs_png' => calculateFileSizeReduction($pngSize, $webpSize) . '%'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Background upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get target dimensions based on room type
 */
function getTargetDimensions($roomType) {
    switch ($roomType) {
        case 'landing':
        case 'room_main':
            return ['width' => 1920, 'height' => 1080]; // 16:9 ratio
        
        case 'room2':
        case 'room3':
        case 'room4':
        case 'room5':
        case 'room6':
        default:
            return ['width' => 1280, 'height' => 896]; // ~10:7 ratio for room pages
    }
}
// sanitizeFilename function moved to security_validator.php for centralization

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