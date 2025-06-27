<?php
// Render Detailed Modal API
header('Content-Type: text/html');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['item']) || !isset($input['images'])) {
        http_response_code(400);
        echo 'Missing required parameters';
        exit;
    }
    
    $item = $input['item'];
    $images = $input['images'];
    
    // Include the detailed product modal component
    require_once __DIR__ . '/../components/detailed_product_modal.php';
    
    // Render the modal and return the HTML
    echo renderDetailedProductModal($item, $images);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Server error: ' . $e->getMessage();
}
?> 