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

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

// Get active background for a room

// Database connection globals for Database class
global $host, $db, $user, $pass, $port, $socket;
$host = 'localhost';
$db = 'whimsicalfrog';
$user = 'root';
$pass = 'Palz2516';
$port = 3306;
$socket = '';

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
    // Return dynamic fallback backgrounds if database fails
    $roomType = $_GET['room_type'] ?? '';
    $fallbacks = generateDynamicFallbacks();

    if (isset($fallbacks[$roomType])) {
        echo json_encode([
            'success' => true,
            'background' => [
                'image_filename' => $fallbacks[$roomType]['png'],
                'webp_filename' => $fallbacks[$roomType]['webp'],
                'background_name' => 'Original (Fallback)'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Room type not found and database unavailable'
        ]);
    }
    exit;
}

// New contract: use 'room' (1..5). Fallback to legacy 'room_type' (room1..room5) if present.
$roomParam = $_GET['room'] ?? null;
$legacyRoomType = $_GET['room_type'] ?? null;
if ($roomParam !== null && $roomParam !== '') {
    if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
        $roomType = 'room' . (int)$m[1];
    } else {
        $roomType = 'room' . (int)$roomParam;
    }
} else {
    $roomType = $legacyRoomType ?? '';
}

if ($roomType === '' || !preg_match('/^room[1-5]$/', $roomType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room is required (use room=1..5)']);
    exit;
}

try {
    // Get active background for the room (prefer room_number, fallback to room_type)
    $rn = preg_match('/^room(\w+)$/i', (string)$roomType, $m) ? (string)$m[1] : '';
    $background = Database::queryOne(
        "SELECT background_name, image_filename, webp_filename, created_at 
         FROM backgrounds 
         WHERE room_number = ? AND is_active = 1 
         LIMIT 1",
        [$rn]
    );
    if (!$background) {
        $background = Database::queryOne(
            "SELECT background_name, image_filename, webp_filename, created_at 
             FROM backgrounds 
             WHERE room_type = ? AND is_active = 1 
             LIMIT 1",
            [$roomType]
        );
    }

    if ($background) {
        echo json_encode(['success' => true, 'background' => $background]);
    } else {
        // Return dynamic fallback if no active background found
        $fallbacks = generateDynamicFallbacks();

        if (isset($fallbacks[$roomType])) {
            echo json_encode([
                'success' => true,
                'background' => [
                    'image_filename' => $fallbacks[$roomType]['png'],
                    'webp_filename' => $fallbacks[$roomType]['webp'],
                    'background_name' => 'Original (Fallback)'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No background found for this room type'
            ]);
        }
    }
} catch (PDOException $e) {
    // On missing table or DB error, return fallback backgrounds
    $fallbacks = generateDynamicFallbacks();
    if (isset($fallbacks[$roomType])) {
        echo json_encode([
            'success'    => true,
            'background' => [
                'image_filename'    => $fallbacks[$roomType]['png'],
                'webp_filename'     => $fallbacks[$roomType]['webp'],
                'background_name'   => 'Original (Fallback)'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?> 