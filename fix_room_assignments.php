<?php
/**
 * Complete fix for room assignments as requested by user:
 * Main Room (Explore Rooms) = 0
 * T-Shirts & Apparel = 1  
 * Tumblers & Drinkware = 2
 * Custom Artwork = 3
 * Sublimation Items = 4
 * Window Wraps = 5
 * Deactivate or move Landing Page out of the way
 */

require_once __DIR__ . '/api/config.php';

echo "=== Fixing Room Assignments ===\n\n";

try {
    $pdo = Database::getInstance();
    
    // Show current state
    echo "=== Current Room State ===\n";
    $stmt = $pdo->prepare("SELECT room_number, room_name, door_label, is_active FROM room_settings ORDER BY room_number");
    $stmt->execute();
    $currentRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($currentRooms as $room) {
        $activeStatus = $room['is_active'] ? 'ACTIVE' : 'INACTIVE';
        echo "Room {$room['room_number']}: {$room['room_name']} ({$room['door_label']}) - {$activeStatus}\n";
    }
    
    echo "\n=== Applying Fixes ===\n";
    
    // Step 1: Deactivate Landing Page (it's not needed for room_main)
    $stmt = $pdo->prepare("UPDATE room_settings SET is_active = 0 WHERE room_name = 'Landing Page'");
    $result = $stmt->execute();
    echo ($result ? "✅" : "❌") . " Deactivated Landing Page\n";
    
    // Step 2: Ensure Main Room exists and is set to room 0
    $stmt = $pdo->prepare("SELECT * FROM room_settings WHERE room_name = 'Main Room'");
    $stmt->execute();
    $mainRoom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mainRoom) {
        // Update existing Main Room to room 0
        $stmt = $pdo->prepare("UPDATE room_settings SET room_number = 0, is_active = 1 WHERE room_name = 'Main Room'");
        $result = $stmt->execute();
        echo ($result ? "✅" : "❌") . " Updated Main Room to room 0\n";
    } else {
        // Create Main Room if it doesn't exist
        $stmt = $pdo->prepare("INSERT INTO room_settings (room_number, room_name, door_label, is_active, display_order) VALUES (0, 'Main Room', 'Explore Rooms', 1, 0)");
        $result = $stmt->execute();
        echo ($result ? "✅" : "❌") . " Created Main Room as room 0\n";
    }
    
    // Step 3: Set the other rooms to correct numbers (they should already be correct from previous script)
    $finalRoomAssignments = [
        ['name' => 'T-Shirts & Apparel', 'number' => 1],
        ['name' => 'Tumblers & Drinkware', 'number' => 2], 
        ['name' => 'Custom Artwork', 'number' => 3],
        ['name' => 'Sublimation Items', 'number' => 4],
        ['name' => 'Window Wraps', 'number' => 5]
    ];
    
    foreach ($finalRoomAssignments as $assignment) {
        $stmt = $pdo->prepare("UPDATE room_settings SET room_number = ?, is_active = 1 WHERE room_name = ?");
        $result = $stmt->execute([$assignment['number'], $assignment['name']]);
        echo ($result ? "✅" : "❌") . " Set {$assignment['name']} to room {$assignment['number']}\n";
    }
    
    // Show final state
    echo "\n=== Final Room State (Active Only) ===\n";
    $stmt = $pdo->prepare("SELECT room_number, room_name, door_label FROM room_settings WHERE is_active = 1 ORDER BY room_number");
    $stmt->execute();
    $finalRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalRooms as $room) {
        echo "Room {$room['room_number']}: {$room['room_name']} ({$room['door_label']})\n";
    }
    
    echo "\n✅ Room assignment fixes completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Room assignment fix error: " . $e->getMessage());
}
?>
