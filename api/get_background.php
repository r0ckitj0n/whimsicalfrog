<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

// Get active background for a room
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$host = 'localhost';
$dbname = 'whimsicalfrog';
$username = 'root';
$password = 'Palz2516';

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
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

$roomType = $_GET['room_type'] ?? '';

if (empty($roomType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room type is required']);
    exit;
}

try {
    // Get active background for the room
    $stmt = $pdo->prepare("
        SELECT background_name, image_filename, webp_filename, created_at 
        FROM backgrounds 
        WHERE room_type = ? AND is_active = 1 
        LIMIT 1
    ");
    $stmt->execute([$roomType]);
    $background = $stmt->fetch(PDO::FETCH_ASSOC);

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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 