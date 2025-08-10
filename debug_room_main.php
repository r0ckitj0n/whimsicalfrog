<?php
/**
 * Debug version of room_main.php to investigate door data issues
 */

// Simulate the same environment as room_main.php
define('INCLUDED_FROM_INDEX', true);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/background_helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Add debug mode flag
$debugMode = true;

echo "<h1>Debug Room Main</h1>\n";
echo "<pre>\n";

try {
    echo "=== Testing getRoomDoorsData() in web context ===\n";
    
    // Include the room helpers
    require_once __DIR__ . '/api/room_helpers.php';
    
    echo "✅ Room helpers included successfully\n";
    
    // Test the function
    $roomDoors = getRoomDoorsData();
    
    echo "✅ getRoomDoorsData() called successfully\n";
    echo "Result count: " . count($roomDoors) . "\n";
    
    if (empty($roomDoors)) {
        echo "❌ PROBLEM: getRoomDoorsData() returned empty array!\n";
        
        // Try direct database query to debug
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT room_number, room_name, door_label, description, display_order
            FROM room_settings 
            WHERE is_active = 1 
            AND room_number NOT IN ('A', 'B')
            ORDER BY display_order, room_number
        ");
        $stmt->execute();
        $directResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Direct query returned: " . count($directResults) . " results\n";
        foreach ($directResults as $row) {
            echo "  - Room {$row['room_number']}: {$row['room_name']} ({$row['door_label']})\n";
        }
        
    } else {
        echo "✅ getRoomDoorsData() returned data:\n";
        foreach ($roomDoors as $door) {
            echo "  - Room {$door['room_number']}: {$door['room_name']} ({$door['door_label']})\n";
        }
        
        echo "\n=== Testing door HTML generation ===\n";
        $areaIndex = 1;
        foreach ($roomDoors as $door) {
            $roomNumber = htmlspecialchars($door['room_number']);
            $roomName = htmlspecialchars($door['room_name']);
            $doorLabel = htmlspecialchars($door['door_label']);
            
            echo "Generating door {$areaIndex} for room {$roomNumber}:\n";
            echo "  data-room=\"{$roomNumber}\"\n";
            echo "  data-category=\"{$doorLabel}\"\n";
            
            $areaIndex++;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception caught: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>
