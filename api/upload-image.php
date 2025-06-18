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
    $pdo = new PDO($dsn, $user, $pass, $options);
    
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
    
    // Move uploaded file
    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Set file permissions
    chmod($uploadPath, 0644);
    
    // Add to database
    $stmt = $pdo->prepare('INSERT INTO item_images (sku, image_filename, image_url, is_primary) VALUES (?, ?, ?, ?)');
    $isPrimary = $_POST['is_primary'] ?? 0;
    $result = $stmt->execute([$sku, $filename, $relativeUrl, $isPrimary]);
    
    if (!$result) {
        // If database insert fails, clean up the uploaded file
        unlink($uploadPath);
        throw new Exception('Failed to save image information to database');
    }
    
    $imageId = $pdo->lastInsertId();
    
    // If this is set as primary, update other images for this SKU
    if ($isPrimary) {
        $stmt = $pdo->prepare('UPDATE item_images SET is_primary = 0 WHERE sku = ? AND id != ?');
        $stmt->execute([$sku, $imageId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'image' => [
            'id' => $imageId,
            'filename' => $filename,
            'url' => $relativeUrl,
            'is_primary' => $isPrimary
        ]
    ]);
    
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
