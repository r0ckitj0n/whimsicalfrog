<?php
// Web-accessible coordinate fix script
// Uses same database pattern as existing API files

function getRoomCoordinatesData() {
    return [
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
}

// Use same database connection pattern as other API files
require_once '../includes/database.php';

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance()->getPDO();
    
    $results = [];
    $roomCoordinates = getRoomCoordinatesData();
    
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
            $results[$roomType] = "Updated with " . count($coordinates) . " coordinates";
        } else {
            // Insert new entry
            $stmt = $pdo->prepare("
                INSERT INTO room_maps (room_type, coordinates, is_active, created_at, updated_at) 
                VALUES (?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$roomType, $coordinateJson]);
            $results[$roomType] = "Created with " . count($coordinates) . " coordinates";
        }
    }
    
    // Verify results
    $verification = [];
    $stmt = $pdo->query("
        SELECT room_type, coordinates, is_active 
        FROM room_maps 
        WHERE room_type IN ('room1','room2','room3','room4','room5') 
        ORDER BY room_type
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $coords = json_decode($row['coordinates'], true);
        $verification[$row['room_type']] = [
            'coordinate_count' => is_array($coords) ? count($coords) : 0,
            'is_active' => $row['is_active'],
            'first_coordinate' => is_array($coords) && count($coords) > 0 ? $coords[0] : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Room coordinates updated successfully',
        'results' => $results,
        'verification' => $verification
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
