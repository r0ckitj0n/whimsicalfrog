<?php

// Render Detailed Modal API
// Use shared config to align DB, sessions, and environment
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

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
        echo '<!-- Missing required parameters -->';
        exit;
    }

    $item = $input['item'];
    $images = $input['images'];

    // Debug: Log received data to see what fields are present
    error_log('MODAL DEBUG - Received item data: ' . json_encode($item));
    error_log('MODAL DEBUG - Item stockLevel: ' . ($item['stockLevel'] ?? 'NOT SET'));
    error_log('MODAL DEBUG - Item fields: ' . implode(', ', array_keys($item)));

    // Include the detailed item modal component
    require_once __DIR__ . '/../components/detailed_item_modal.php';

    // Render the modal and return the HTML
    echo renderDetailedItemModal($item, $images);

} catch (Exception $e) {
    http_response_code(500);
    echo '<!-- Server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
}
