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

    // New contract: use 'room' (alphanumeric or '0'). Legacy 'room_type' is deprecated.
    $roomParam = $_GET['room'] ?? '';
    $roomParam = is_string($roomParam) ? trim($roomParam) : $roomParam;

    if ($roomParam === '' || $roomParam === null) {
        Response::error('Room is required', null, 400);
    }

    // Normalize: roomX -> X (letters or numbers), leave others as-is
    if (preg_match('/^room([A-Za-z0-9]+)$/i', (string)$roomParam, $m)) {
        $roomKey = (string)$m[1];
    } else {
        $roomKey = (string)$roomParam;
    }
    // Validate: allow 0 or alphanumeric token
    if (!preg_match('/^(0|[A-Za-z0-9]+)$/', $roomKey)) {
        Response::error('Invalid room. Expected 0 or alphanumeric (letters/numbers).', null, 400);
    }

    // room_number-only lookup (supports '0' for main room)
    $roomNumberStr = (string)$roomKey;
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
