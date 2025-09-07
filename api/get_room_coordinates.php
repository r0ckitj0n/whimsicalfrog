<?php
// CORS headers for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure absolute include and clean JSON output
require_once __DIR__ . '/config.php';
ini_set('display_errors', 0);
ob_start();

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
        echo json_encode(['success' => false, 'message' => 'Room is required']);
        exit;
    }

    // Normalize to integer 1..5 if possible, otherwise accept strings like 'room1'
    if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
        $roomNumber = (int)$m[1];
    } else {
        $roomNumber = (int)$roomParam;
    }
    if ($roomNumber < 1 || $roomNumber > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid room. Expected 1-5.']);
        exit;
    }

    // Internal storage still uses 'room_type' column like 'room1'. Map quietly here.
    $internalRoomType = 'room' . $roomNumber;

    // Get the active map for the specified room
    $map = Database::queryOne("SELECT * FROM room_maps WHERE room_type = ? AND is_active = TRUE", [$internalRoomType]);

    if ($map) {
        $coordinates = json_decode($map['coordinates'], true);
        if (ob_get_length() !== false) { ob_end_clean(); }
        echo json_encode([
            'success' => true,
            'coordinates' => $coordinates,
            'map_name' => $map['map_name'],
            'updated_at' => $map['updated_at']
        ]);
    } else {
        // Return empty coordinates if no active map found
        if (ob_get_length() !== false) { ob_end_clean(); }
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
    if (ob_get_length() !== false) { ob_end_clean(); }
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>