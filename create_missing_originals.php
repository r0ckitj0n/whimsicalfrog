<?php
// Script to create Original maps for Landing Page and Main Room

// Database connection
$host = 'localhost';
$dbname = 'whimsicalfrog';
$username = 'root';
$password = 'Palz2516!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to database successfully\n";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Landing Page coordinates (1 area)
$landingCoordinates = [
    ['selector' => 'area-1', 'top' => 414, 'left' => 466, 'width' => 285, 'height' => 153]
];

// Main Room coordinates (5 areas with proper dimensions from main_room.php)
$mainRoomCoordinates = [
    ['selector' => 'area-1', 'top' => 243, 'left' => 30, 'width' => 234, 'height' => 233],
    ['selector' => 'area-2', 'top' => 403, 'left' => 390, 'width' => 202, 'height' => 241],
    ['selector' => 'area-3', 'top' => 271, 'left' => 753, 'width' => 170, 'height' => 235],
    ['selector' => 'area-4', 'top' => 291, 'left' => 1001, 'width' => 197, 'height' => 255],
    ['selector' => 'area-5', 'top' => 157, 'left' => 486, 'width' => 190, 'height' => 230]
];

function createOriginalMap($pdo, $roomType, $coordinates) {
    // Check if Original map already exists
    $checkStmt = $pdo->prepare("SELECT id FROM room_maps WHERE room_type = ? AND map_name = 'Original'");
    $checkStmt->execute([$roomType]);
    
    if ($checkStmt->fetch()) {
        echo "⚠️  Original map for $roomType already exists, skipping...\n";
        return;
    }
    
    // Convert coordinates to JSON format
    $coordinatesJson = json_encode($coordinates);
    
    // Insert Original map
    $stmt = $pdo->prepare("
        INSERT INTO room_maps (room_type, map_name, coordinates, is_active, created_at) 
        VALUES (?, 'Original', ?, 1, NOW())
    ");
    
    if ($stmt->execute([$roomType, $coordinatesJson])) {
        echo "✅ Created Original map for $roomType with " . count($coordinates) . " areas\n";
    } else {
        echo "❌ Failed to create Original map for $roomType\n";
    }
}

echo "\n🚀 Creating Original maps for Landing Page and Main Room...\n\n";

// Create Landing Page Original map
createOriginalMap($pdo, 'landing', $landingCoordinates);

// Create Main Room Original map  
createOriginalMap($pdo, 'room_main', $mainRoomCoordinates);

echo "\n✅ Script completed!\n";
echo "\nTo verify, check the Room Mapper dropdown for:\n";
echo "- Landing Page: Should show 'Original (ACTIVE) 🔒 PROTECTED'\n";
echo "- Main Room: Should show 'Original (ACTIVE) 🔒 PROTECTED'\n";
?> 