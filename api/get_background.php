<?php

require_once __DIR__ . '/../includes/functions.php';
// Get active background for a room
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$host = 'localhost';
$dbname = 'whimsicalfrog';
$username = 'root';
$password = 'Palz2516';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Return fallback backgrounds if database fails
    $roomType = $_GET['room_type'] ?? '';
    $fallbacks = [
        'landing' => ['png' => 'home_background.png', 'webp' => 'home_background.webp'],
        'room_main' => ['png' => 'room_main.png', 'webp' => 'room_main.webp'],
        'room2' => ['png' => 'room2.png', 'webp' => 'room2.webp'],
        'room3' => ['png' => 'room3.png', 'webp' => 'room3.webp'],
        'room4' => ['png' => 'room4.png', 'webp' => 'room4.webp'],
        'room5' => ['png' => 'room5.png', 'webp' => 'room5.webp'],
        'room6' => ['png' => 'room6.png', 'webp' => 'room6.webp']
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
            'room2' => ['png' => 'room2.png', 'webp' => 'room2.webp'],
            'room3' => ['png' => 'room3.png', 'webp' => 'room3.webp'],
            'room4' => ['png' => 'room4.png', 'webp' => 'room4.webp'],
            'room5' => ['png' => 'room5.png', 'webp' => 'room5.webp'],
            'room6' => ['png' => 'room6.png', 'webp' => 'room6.webp']
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