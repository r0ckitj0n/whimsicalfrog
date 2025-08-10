<?php
/**
 * Shift room coordinates from rooms 2-6 to rooms 1-5
 * This script updates the database to match the new room numbering system
 */

require_once '../api/config.php';

try {
    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    echo "Starting room coordinate shift...\n";
    
    // Map of old room types to new room types
    $roomMappings = [
        'room2' => 'room1',
        'room3' => 'room2', 
        'room4' => 'room3',
        'room5' => 'room4',
        'room6' => 'room5'
    ];
    
    // Get current active coordinates for each old room
    $existingCoords = [];
    foreach (array_keys($roomMappings) as $oldRoom) {
        $stmt = $pdo->prepare("SELECT * FROM room_maps WHERE room_type = ? AND is_active = 1");
        $stmt->execute([$oldRoom]);
        $result = $stmt->fetch();
        if ($result) {
            $existingCoords[$oldRoom] = $result;
            echo "Found coordinates for {$oldRoom}: " . strlen($result['coordinates']) . " characters\n";
        }
    }
    
    // Deactivate any existing room1-room5 entries to avoid conflicts
    echo "Deactivating existing room1-room5 entries...\n";
    for ($i = 1; $i <= 5; $i++) {
        $stmt = $pdo->prepare("UPDATE room_maps SET is_active = 0 WHERE room_type = ?");
        $stmt->execute(["room{$i}"]);
    }
    
    // Deactivate the old room2-room6 entries
    echo "Deactivating old room2-room6 entries...\n";
    foreach (array_keys($roomMappings) as $oldRoom) {
        $stmt = $pdo->prepare("UPDATE room_maps SET is_active = 0 WHERE room_type = ?");
        $stmt->execute([$oldRoom]);
    }
    
    // Create new entries with shifted coordinates
    echo "Creating new coordinate entries...\n";
    foreach ($roomMappings as $oldRoom => $newRoom) {
        if (isset($existingCoords[$oldRoom])) {
            $coords = $existingCoords[$oldRoom];
            
            // Insert new entry with shifted room type
            $stmt = $pdo->prepare("INSERT INTO room_maps (room_type, map_name, coordinates, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
            $stmt->execute([
                $newRoom,
                $coords['map_name'] . ' (Shifted)',
                $coords['coordinates']
            ]);
            
            echo "Created {$newRoom} from {$oldRoom} coordinates\n";
        } else {
            echo "Warning: No coordinates found for {$oldRoom}\n";
        }
    }
    
    $pdo->commit();
    echo "Room coordinate shift completed successfully!\n";
    
    // Verify the new mappings
    echo "\nVerifying new mappings:\n";
    for ($i = 1; $i <= 5; $i++) {
        $stmt = $pdo->prepare("SELECT room_type, map_name, LENGTH(coordinates) as coord_length FROM room_maps WHERE room_type = ? AND is_active = 1");
        $stmt->execute(["room{$i}"]);
        $result = $stmt->fetch();
        if ($result) {
            echo "room{$i}: {$result['map_name']} ({$result['coord_length']} chars)\n";
        } else {
            echo "room{$i}: NO ACTIVE COORDINATES FOUND\n";
        }
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
