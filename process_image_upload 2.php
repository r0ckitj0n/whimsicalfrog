<?php
<<<<<<< HEAD
require_once __DIR__ . '/api/config.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD']!=='POST'|| !isset($_FILES['image'])){
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

$productId = $_POST['productId'] ?? '';
$itemId = $_POST['itemId'] ?? '';
if($productId!==''){
    $baseName = $productId;
} elseif($itemId!=='') {
    $baseName = $itemId;
} else {
    $baseName = 'tmp'.time();
}

$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
$allowed=['png','jpg','jpeg','webp'];
if(!in_array($ext,$allowed)){
    echo json_encode(['success'=>false,'error'=>'Unsupported file type']);
    exit;
}

// generate filename unique
$filename = $baseName.'-'.substr(md5(uniqid()),0,6).'.'.$ext;
$relPath = 'images/products/'.$filename;
$absPath = dirname(__FILE__).'/'.$relPath;
$dir = dirname($absPath);
if(!is_dir($dir)){
    mkdir($dir,0777,true);
}
if(move_uploaded_file($_FILES['image']['tmp_name'],$absPath)){
    chmod($absPath,0644);
    // TEMP LOG
    error_log("[image_upload] saved $relPath\n", 3, __DIR__ . '/inventory_errors.log');
    echo json_encode(['success'=>true,'imageUrl'=>$relPath]);
}else{
    echo json_encode(['success'=>false,'error'=>'Failed to move file']);
}

=======
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Debug log function - only logs in development environment
function debugLog($message, $data = null) {
    global $isLocalhost;
    if ($isLocalhost) {
        error_log("IMAGE UPLOAD: " . $message . ($data ? " - " . json_encode($data) : ""));
    }
}

// Helper function to return JSON response
function returnJson($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Check if this is a POST request with file upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJson(false, 'Invalid request method', null, 405);
}

// Check if productId is provided
if (!isset($_POST['productId']) || empty($_POST['productId'])) {
    returnJson(false, 'Product ID is required', null, 400);
}

// Check if itemId is provided
if (!isset($_POST['itemId']) || empty($_POST['itemId'])) {
    returnJson(false, 'Item ID is required', null, 400);
}

// Check if file is uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    returnJson(false, 'No image file uploaded', null, 400);
}

// Get productId and itemId
$productId = trim($_POST['productId']);
$itemId = trim($_POST['itemId']);

// Validate file upload
if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    $errorMessage = isset($uploadErrors[$_FILES['image']['error']]) 
        ? $uploadErrors[$_FILES['image']['error']] 
        : 'Unknown upload error';
    returnJson(false, $errorMessage, null, 400);
}

// Get file information
$fileName = $_FILES['image']['name'];
$fileTmpPath = $_FILES['image']['tmp_name'];
$fileSize = $_FILES['image']['size'];
$fileType = $_FILES['image']['type'];

// Extract file extension
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validate file type
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($fileExtension, $allowedExtensions)) {
    returnJson(false, 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions), null, 400);
}

// Validate file size (max 5MB)
$maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
if ($fileSize > $maxFileSize) {
    returnJson(false, 'File size exceeds the limit of 5MB', null, 400);
}

// Create destination directory if it doesn't exist
$uploadDir = __DIR__ . '/images/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        returnJson(false, 'Failed to create upload directory', null, 500);
    }
}

// Create new filename based on productId
$newFileName = $productId . '.' . $fileExtension;
$uploadPath = $uploadDir . $newFileName;

// Check if we need to resize the image
$maxWidth = 1200;
$maxHeight = 1200;
$shouldResize = false;

// Get image dimensions
list($width, $height) = getimagesize($fileTmpPath);
if ($width > $maxWidth || $height > $maxHeight) {
    $shouldResize = true;
}

try {
    // If resize is needed
    if ($shouldResize) {
        // Calculate new dimensions maintaining aspect ratio
        if ($width > $height) {
            $newWidth = $maxWidth;
            $newHeight = intval($height * $maxWidth / $width);
        } else {
            $newHeight = $maxHeight;
            $newWidth = intval($width * $maxHeight / $height);
        }
        
        // Create image resource based on file type
        $sourceImage = null;
        switch ($fileExtension) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = imagecreatefromjpeg($fileTmpPath);
                break;
            case 'png':
                $sourceImage = imagecreatefrompng($fileTmpPath);
                break;
            case 'gif':
                $sourceImage = imagecreatefromgif($fileTmpPath);
                break;
            case 'webp':
                $sourceImage = imagecreatefromwebp($fileTmpPath);
                break;
        }
        
        if (!$sourceImage) {
            returnJson(false, 'Failed to process image file', null, 500);
        }
        
        // Create a new true color image with new dimensions
        $destinationImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($fileExtension === 'png' || $fileExtension === 'gif') {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
            $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
            imagefilledrectangle($destinationImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize the image
        imagecopyresampled(
            $destinationImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight, $width, $height
        );
        
        // Save the resized image
        switch ($fileExtension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($destinationImage, $uploadPath, 90); // 90% quality
                break;
            case 'png':
                imagepng($destinationImage, $uploadPath, 9); // 0-9 compression level
                break;
            case 'gif':
                imagegif($destinationImage, $uploadPath);
                break;
            case 'webp':
                imagewebp($destinationImage, $uploadPath, 90); // 90% quality
                break;
        }
        
        // Free up memory
        imagedestroy($sourceImage);
        imagedestroy($destinationImage);
    } else {
        // No resize needed, just move the file
        if (!move_uploaded_file($fileTmpPath, $uploadPath)) {
            returnJson(false, 'Failed to move uploaded file', null, 500);
        }
    }
    
    // Create database connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Update image URL in the inventory table
    $imageUrl = 'images/' . $newFileName;
    $stmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE id = ?");
    
    if (!$stmt->execute([$imageUrl, $itemId])) {
        returnJson(false, 'Failed to update database with new image URL', null, 500);
    }
    
    // Return success response
    returnJson(true, 'Image uploaded successfully', [
        'imageUrl' => $imageUrl,
        'fileName' => $newFileName
    ]);
    
} catch (Exception $e) {
    debugLog("Exception", ["message" => $e->getMessage()]);
    returnJson(false, 'Error: ' . $e->getMessage(), null, 500);
}
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
?>
