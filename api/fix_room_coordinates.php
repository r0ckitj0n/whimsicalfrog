<?php
// Fix missing room coordinate data in room_maps table
require_once __DIR__ . '/../includes/database.php';

try {
    $pdo = Database::getInstance()->getPDO();
    
    echo "=== FIXING ROOM COORDINATES ===\n\n";
    
    // Check current room_maps entries
    echo "1. CURRENT ROOM_MAPS ENTRIES:\n";
    $stmt = $pdo->query("SELECT room_type, is_active, updated_at FROM room_maps ORDER BY room_type");
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($existing)) {
        echo "  ❌ NO ENTRIES in room_maps table!\n";
    } else {
        foreach ($existing as $entry) {
            echo "  {$entry['room_type']} - Active: {$entry['is_active']} - Updated: {$entry['updated_at']}\n";
        }
    }
    
    // Define coordinate data for each room
    $roomCoordinates = [
        'room1' => [ // T-Shirts & Apparel - spread across room
            ['top' => 200, 'left' => 150, 'width' => 80, 'height' => 80],
            ['top' => 350, 'left' => 300, 'width' => 80, 'height' => 80],
            ['top' => 180, 'left' => 450, 'width' => 80, 'height' => 80],
            ['top' => 400, 'left' => 600, 'width' => 80, 'height' => 80]
        ],
        'room2' => [ // Tumblers & Drinkware - positioned near kitchen area
            ['top' => 250, 'left' => 200, 'width' => 80, 'height' => 80],
            ['top' => 180, 'left' => 350, 'width' => 80, 'height' => 80],
            ['top' => 320, 'left' => 480, 'width' => 80, 'height' => 80],
            ['top' => 280, 'left' => 600, 'width' => 80, 'height' => 80]
        ],
        'room3' => [ // Custom Artwork - wall positions
            ['top' => 100, 'left' => 200, 'width' => 100, 'height' => 80],
            ['top' => 150, 'left' => 400, 'width' => 100, 'height' => 80],
            ['top' => 200, 'left' => 600, 'width' => 100, 'height' => 80]
        ],
        'room4' => [ // Sublimation Items - center table area
            ['top' => 300, 'left' => 400, 'width' => 80, 'height' => 80]
        ],
        'room5' => [ // Window Wraps - near window
            ['top' => 242, 'left' => 261, 'width' => 108, 'height' => 47]
        ]
    ];
    
    echo "\n2. INSERTING/UPDATING COORDINATE DATA:\n";
    
    foreach ($roomCoordinates as $roomType => $coordinates) {
        // Check if entry exists
        $stmt = $pdo->prepare("SELECT id FROM room_maps WHERE room_type = ?");
        $stmt->execute([$roomType]);
        $existingId = $stmt->fetchColumn();
        
        $coordinateJson = json_encode($coordinates);
        
        if ($existingId) {
            // Update existing entry
            $stmt = $pdo->prepare("
                UPDATE room_maps 
                SET coordinates = ?, is_active = 1, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$coordinateJson, $existingId]);
            echo "  ✅ Updated {$roomType}: " . count($coordinates) . " coordinate points\n";
        } else {
            // Insert new entry
            $stmt = $pdo->prepare("
                INSERT INTO room_maps (room_type, coordinates, is_active, created_at, updated_at) 
                VALUES (?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$roomType, $coordinateJson]);
            echo "  ✅ Created {$roomType}: " . count($coordinates) . " coordinate points\n";
        }
    }
    
    echo "\n3. VERIFICATION - CHECKING UPDATED ENTRIES:\n";
    $stmt = $pdo->query("
        SELECT room_type, coordinates, is_active, updated_at 
        FROM room_maps 
        WHERE room_type IN ('room1','room2','room3','room4','room5') 
        ORDER BY room_type
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $coords = json_decode($row['coordinates'], true);
        $coordCount = is_array($coords) ? count($coords) : 0;
        echo "  {$row['room_type']}: {$coordCount} coordinates - Active: {$row['is_active']} - Updated: {$row['updated_at']}\n";
        
        // Show first coordinate as sample
        if ($coordCount > 0) {
            $first = $coords[0];
            echo "    Sample: top:{$first['top']}, left:{$first['left']}, w:{$first['width']}, h:{$first['height']}\n";
        }
    }
    
    echo "\n✅ ROOM COORDINATE FIXES COMPLETE!\n";
    echo "Test by opening Room 1 modal - items should now be positioned correctly.\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
