<?php

// Lightweight CORS/headers are handled by config; use Response helpers
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure absolute include and clean JSON output
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // New contract: use 'room' (number 1..5). Legacy 'room_type' is deprecated.
    $roomParam = $_GET['room'] ?? '';
    $roomParam = is_string($roomParam) ? trim($roomParam) : $roomParam;

    if ($roomParam === '' || $roomParam === null) {
        Response::error('Room is required', null, 400);
    }

    // Normalize to integer 1..5 if possible, otherwise accept strings like 'room1'
    if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
        $roomNumber = (int)$m[1];
    } else {
        $roomNumber = (int)$roomParam;
    }
    if ($roomNumber < 1 || $roomNumber > 5) {
        Response::error('Invalid room. Expected 1-5.', null, 400);
    }

    // room_number-only lookup
    $roomNumberStr = (string)$roomNumber;
    $map = Database::queryOne("SELECT * FROM room_maps WHERE room_number = ? AND is_active = TRUE ORDER BY updated_at DESC LIMIT 1", [$roomNumberStr]);

    if ($map) {
        $coordinates = json_decode($map['coordinates'], true);
        Response::success([
            'coordinates' => is_array($coordinates) ? $coordinates : [],
            'map_name' => $map['map_name'],
            'updated_at' => $map['updated_at']
        ]);
    } else {
        // Return empty coordinates if no active map found
        Response::success([
            'coordinates' => [],
            'message' => 'No active map found for this room'
        ]);
    }

} catch (Exception $e) {
    // Log error but don't expose sensitive database info
    error_log("Room coordinates API error: " . $e->getMessage());
    Response::serverError('Database error: ' . $e->getMessage());
}
