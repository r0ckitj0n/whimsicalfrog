<?php
// CORS headers for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// CORS headers for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (
    isset($_SERVER['REQUEST_METHOD']) && 
    $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
) {
    http_response_code(200);
    exit;
}

require_once 'config.php';

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $roomType = $_GET['room_type'] ?? '';

    if (empty($roomType)) {
        echo json_encode(['success' => false, 'message' => 'Room type is required']);
        exit;
    }

    // Get the active map for the specified room type
    $map = Database::queryOne("SELECT * FROM room_maps WHERE room_type = ? AND is_active = TRUE", [$roomType]);

    if ($map) {
        $coordinates = json_decode($map['coordinates'], true);
        echo json_encode([
            'success' => true,
            'coordinates' => $coordinates,
            'map_name' => $map['map_name'],
            'updated_at' => $map['updated_at']
        ]);
    } else {
        // Return empty coordinates if no active map found
        echo json_encode([
            'success' => true,
            'coordinates' => [],
            'message' => 'No active map found for this room'
        ]);
    }

} catch (Exception $e) {
    // Log error but don't expose sensitive database info
    error_log("Room coordinates API error: " . $e->getMessage());

    // Return error for debugging
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug_room_type' => $roomType ?? 'not set',
        'debug_room_number' => $roomNumber ?? 'not set'
    ]);
}
?> 