<?php
// API endpoint to get fully rendered room content with products
// This solves the issue of dynamically loaded rooms not having access to product data

// Include configuration
require_once '../config.php';

// Include products API to get product data
require_once 'products.php';

// Include common functions (like getImageTag)
require_once '../includes/functions.php';

// Get requested room from URL parameter
$room = isset($_GET['room']) ? $_GET['room'] : '';

// Validate room category against known values
$validRooms = ['tshirts', 'tumblers', 'artwork', 'sublimation', 'windowwraps'];

if (empty($room) || !in_array($room, $validRooms)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid room category']);
    exit;
}

// Construct path to room file (using realpath to prevent directory traversal)
$roomFilePath = realpath(__DIR__ . "/../sections/room_{$room}.php");
$sectionsDir = realpath(__DIR__ . "/../sections");

// Verify file exists and is within the sections directory
if (!$roomFilePath || strpos($roomFilePath, $sectionsDir) !== 0 || !file_exists($roomFilePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Room template not found']);
    exit;
}

// Start output buffering to capture the rendered content
ob_start();

// Make sure we have the categories data
if (!isset($categories) || empty($categories)) {
    // If products.php didn't set $categories globally, we need to fetch them
    // This assumes getProductsByCategory() is defined in products.php
    if (function_exists('getProductsByCategory')) {
        $categories = getProductsByCategory($allProducts);
    } else {
        // Fallback if function doesn't exist
        $categories = [];
    }
}

// Include the room file - it will now have access to $categories
include $roomFilePath;

// Get the buffered content
$content = ob_get_clean();

// Return the fully rendered HTML
echo $content;
?>
