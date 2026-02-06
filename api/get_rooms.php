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
    // Fetch descriptive room names from room_settings
    // Filter out internal system rooms (X=Settings, S=Shop) 
    // And filter out A/0 as they are often handled specially/pinned in frontend
    Database::getInstance();
    $rooms = Database::queryAll(
        "SELECT 
            room_number, 
            room_name,
            room_number AS id, 
            room_name AS name
        FROM room_settings 
        WHERE room_number NOT IN ('X', 'S', 'A', '0')
        ORDER BY CASE WHEN room_number REGEXP '^[0-9]+$' THEN CAST(room_number AS UNSIGNED) ELSE 999 END, room_number"
    );
    echo json_encode($rooms);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
