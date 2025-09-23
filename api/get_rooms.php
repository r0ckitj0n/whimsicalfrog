<?php

// API endpoint: return list of rooms (primary assignments)
header('Content-Type: application/json');
// CORS for local dev previews
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/config.php';

try {
    // Fetch primary room assignments
    Database::getInstance();
    $rooms = Database::queryAll(
        "SELECT room_number AS id, room_name AS name, category_id FROM room_category_assignments WHERE is_primary = 1 ORDER BY room_number"
    );
    echo json_encode($rooms);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([ 'error' => $e->getMessage() ]);
}
