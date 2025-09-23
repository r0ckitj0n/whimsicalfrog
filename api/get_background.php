<?php
// CORS headers for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

// Get active background for a room

// Use centralized configuration (api/config.php) for Database::getInstance()

/**
 * Generate dynamic fallback backgrounds based on room data
 */
function generateDynamicFallbacks()
{
    $fallbacks = [
        'landing' => ['png' => 'background_home.png', 'webp' => 'background_home.webp'],
        'room_main' => ['png' => 'background_room_main.png', 'webp' => 'background_room_main.webp']
    ];

    // Get all valid rooms from database
    $validRooms = getAllValidRooms();

    foreach ($validRooms as $roomNumber) {
        if (!in_array($roomNumber, ['A', 'B'])) {
            // Generate default filenames for product rooms
            $fallbacks["room{$roomNumber}"] = [
                'png' => "background_room{$roomNumber}.png",
                'webp' => "background_room{$roomNumber}.webp"
            ];
        }
    }

    return $fallbacks;
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
} catch (PDOException $e) {
    // Fail fast: no fallback backgrounds on DB error
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable: ' . $e->getMessage()]);
    exit;
}

// New contract: use 'room' (1..5) or 'room_number'
$roomParam = $_GET['room'] ?? $_GET['room_number'] ?? '';
if ($roomParam === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room is required (use room=1..5)']);
    exit;
}
if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
    $roomNumber = (string)((int)$m[1]);
} else {
    $roomNumber = (string)((int)$roomParam);
}
if (!preg_match('/^[1-5]$/', $roomNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid room. Expected 1-5.']);
    exit;
}

try {
    // Get active background for the room by room_number
    $rn = $roomNumber;
    $background = Database::queryOne(
        "SELECT background_name, image_filename, webp_filename, created_at 
         FROM backgrounds 
         WHERE room_number = ? AND is_active = 1 
         LIMIT 1",
        [$rn]
    );

    if ($background) {
        echo json_encode(['success' => true, 'background' => $background]);
    } else {
        // Strict: no background configured for this room
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No active background found for room ' . $roomNumber]);
    }
} catch (PDOException $e) {
    // Strict: surface DB errors to caller
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 