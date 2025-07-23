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
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $roomType = $_GET['room_type'] ?? '';

    if (empty($roomType)) {
        echo json_encode(['success' => false, 'message' => 'Room type is required']);
        exit;
    }

    // Check if room_maps table exists first
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'room_maps'");
    if ($tableCheck->rowCount() == 0) {
        // Table doesn't exist, return empty coordinates
        echo json_encode([
            'success' => true,
            'coordinates' => [],
            'message' => 'Room maps table not initialized - database required'
        ]);
        exit;
    }

    // Get the active map for the specified room type
    $stmt = $pdo->prepare("SELECT * FROM room_maps WHERE room_type = ? AND is_active = TRUE");
    $stmt->execute([$roomType]);
    $map = $stmt->fetch();

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

} catch (PDOException $e) {
    // Log error but don't expose sensitive database info
    error_log("Room coordinates API error: " . $e->getMessage());

    // Return graceful fallback
    echo json_encode([
        'success' => true,
        'coordinates' => [],
        'message' => 'No active room map found in database'
    ]);
}
?> 