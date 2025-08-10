<?php
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

// Database connection globals (same as get_background.php)
global $host, $db, $user, $pass, $port, $socket;
$host = 'localhost';
$db = 'whimsicalfrog';
$user = 'root';
$pass = 'Palz2516';
$port = 3306;
$socket = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Room 1 coordinates (T-Shirts)
    $room1_coords = [
        ['top' => 200, 'left' => 150, 'width' => 80, 'height' => 80],
        ['top' => 350, 'left' => 300, 'width' => 80, 'height' => 80],
        ['top' => 180, 'left' => 450, 'width' => 80, 'height' => 80],
        ['top' => 400, 'left' => 600, 'width' => 80, 'height' => 80]
    ];
    
    $json = json_encode($room1_coords);
    
    // Insert/update room1 coordinates
    $stmt = $pdo->prepare("INSERT INTO room_maps (room_type, coordinates, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE coordinates = ?, updated_at = NOW()");
    $stmt->execute(['room1', $json, $json]);
    
    echo json_encode(['success' => true, 'message' => 'Room1 coordinates fixed', 'coords' => count($room1_coords)]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
