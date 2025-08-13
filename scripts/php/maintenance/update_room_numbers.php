<?php
/**
 * Update room numbers as requested by user:
 * Main Room (Explore Rooms) = 0
 * T-Shirts & Apparel = 1  
 * Tumblers & Drinkware = 2
 * Custom Artwork = 3
 * Sublimation Items = 4
 * Window Wraps = 5
 */

require_once __DIR__ . '/../../../api/config.php';

echo "Updating room numbers...\n\n";

try {
    $pdo = Database::getInstance();
    
    // Show current state
    echo "=== Current Room Numbers ===\n";
    $stmt = $pdo->prepare("SELECT room_number, room_name, door_label, display_order FROM room_settings WHERE is_active = 1 ORDER BY display_order, room_number");
    $stmt->execute();
    $currentRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($currentRooms as $room) {
        echo "Room {$room['room_number']}: {$room['room_name']} ({$room['door_label']}) - Display Order: {$room['display_order']}\n";
    }
    
    echo "\n=== Updating Room Numbers ===\n";
    
    // First, check if Landing Page (room 0) should be deactivated or moved
    $stmt = $pdo->prepare("SELECT * FROM room_settings WHERE room_number = 0 AND room_name = 'Landing Page'");
    $stmt->execute();
    $landingPage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($landingPage) {
        echo "Found Landing Page at room 0. Moving to room 999 temporarily...\n";
        $stmt = $pdo->prepare("UPDATE room_settings SET room_number = 999 WHERE room_number = 0 AND room_name = 'Landing Page'");
        $stmt->execute();
    }
    
    // Define the new room number mapping (Main Room gets 0)
    $roomUpdates = [
        ['current_name' => 'Main Room', 'new_number' => 0],
        ['current_name' => 'T-Shirts & Apparel', 'new_number' => 1],
        ['current_name' => 'Tumblers & Drinkware', 'new_number' => 2],
        ['current_name' => 'Custom Artwork', 'new_number' => 3],
        ['current_name' => 'Sublimation Items', 'new_number' => 4],
        ['current_name' => 'Window Wraps', 'new_number' => 5]
    ];
    
    // First pass: Move all existing rooms to temp numbers to avoid conflicts
    echo "Moving existing rooms to temporary numbers...\n";
    foreach ($roomUpdates as $i => $update) {
        $tempNumber = 100 + $i; // Use 100+ as temp numbers
        $stmt = $pdo->prepare("UPDATE room_settings SET room_number = ? WHERE room_name = ? AND is_active = 1");
        $stmt->execute([$tempNumber, $update['current_name']]);
        echo "Moved '{$update['current_name']}' to temp room {$tempNumber}\n";
    }
    
    // Second pass: Move from temp numbers to final numbers
    echo "\nApplying final room numbers...\n";
    foreach ($roomUpdates as $i => $update) {
        $tempNumber = 100 + $i;
        $stmt = $pdo->prepare("UPDATE room_settings SET room_number = ? WHERE room_number = ? AND is_active = 1");
        $result = $stmt->execute([$update['new_number'], $tempNumber]);
        
        if ($result) {
            echo "✅ Updated '{$update['current_name']}' to room {$update['new_number']}\n";
        } else {
            echo "❌ Failed to update '{$update['current_name']}'\n";
        }
    }
    
    // Show updated state
    echo "\n=== Updated Room Numbers ===\n";
    $stmt = $pdo->prepare("SELECT room_number, room_name, door_label, display_order FROM room_settings WHERE is_active = 1 ORDER BY room_number");
    $stmt->execute();
    $updatedRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($updatedRooms as $room) {
        echo "Room {$room['room_number']}: {$room['room_name']} ({$room['door_label']}) - Display Order: {$room['display_order']}\n";
    }
    
    echo "\n✅ Room number update completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Room update error: " . $e->getMessage());
}
?>
