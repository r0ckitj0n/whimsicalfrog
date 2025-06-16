<?php
// Get active background for a room
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$host = 'localhost';
$dbname = 'whimsicalfrog';
$username = 'root';
$password = 'Palz2516';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Return fallback backgrounds if database fails
    $roomType = $_GET['room_type'] ?? '';
    $fallbacks = [
        'landing' => ['png' => 'home_background.png', 'webp' => 'home_background.webp'],
        'room_main' => ['png' => 'room_main.png', 'webp' => 'room_main.webp'],
        'room_artwork' => ['png' => 'room_artwork.png', 'webp' => 'room_artwork.webp'],
        'room_tshirts' => ['png' => 'room_tshirts.png', 'webp' => 'room_tshirts.webp'],
        'room_tumblers' => ['png' => 'room_tumblers.png', 'webp' => 'room_tumblers.webp'],
        'room_sublimation' => ['png' => 'room_sublimation.png', 'webp' => 'room_sublimation.webp'],
        'room_windowwraps' => ['png' => 'room_windowwraps.png', 'webp' => 'room_windowwraps.webp']
    ];
    
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
        echo json_encode(['success' => false, 'message' => 'Room type not found']);
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
        // Return fallback if no active background found
        $fallbacks = [
            'landing' => ['png' => 'home_background.png', 'webp' => 'home_background.webp'],
            'room_main' => ['png' => 'room_main.png', 'webp' => 'room_main.webp'],
            'room_artwork' => ['png' => 'room_artwork.png', 'webp' => 'room_artwork.webp'],
            'room_tshirts' => ['png' => 'room_tshirts.png', 'webp' => 'room_tshirts.webp'],
            'room_tumblers' => ['png' => 'room_tumblers.png', 'webp' => 'room_tumblers.webp'],
            'room_sublimation' => ['png' => 'room_sublimation.png', 'webp' => 'room_sublimation.webp'],
            'room_windowwraps' => ['png' => 'room_windowwraps.png', 'webp' => 'room_windowwraps.webp']
        ];
        
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
            echo json_encode(['success' => false, 'message' => 'No background found for this room']);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 