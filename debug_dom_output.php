<?php
/**
 * Debug script to check exactly what the room_main.php is outputting
 */

require_once __DIR__ . '/api/room_helpers.php';

echo "=== DEBUG: Room Main DOM Output ===\n\n";

try {
    $roomDoors = getRoomDoorsData();
    
    echo "Rooms from database:\n";
    foreach ($roomDoors as $i => $door) {
        echo "  {$i}: Room {$door['room_number']} - {$door['room_name']} ({$door['door_label']})\n";
    }
    
    echo "\nGenerated HTML (what should appear in DOM):\n";
    echo "----------------------------------------\n";
    
    if (empty($roomDoors)) {
        echo '<div class="no-doors-message">No rooms are currently available.</div>';
    } else {
        $areaIndex = 1;
        foreach ($roomDoors as $door):
            $roomNumber = htmlspecialchars($door['room_number']);
            $roomName = htmlspecialchars($door['room_name']);
            $doorLabel = htmlspecialchars($door['door_label']);
            ?>
<!-- <?php echo $roomName; ?> Door -->
<div class="door-area area-<?php echo $areaIndex; ?>" data-url="/room/<?php echo $roomNumber; ?>" data-category="<?php echo $doorLabel; ?>" data-room="<?php echo $roomNumber; ?>">
    <picture class="door-picture">
        <source srcset="images/signs/sign_door_room<?php echo $roomNumber; ?>.webp" type="image/webp">
        <img src="images/signs/sign_door_room<?php echo $roomNumber; ?>.png" alt="<?php echo $doorLabel; ?>" class="door-sign">
    </picture>
    <div class="door-label"><?php echo $doorLabel; ?></div>
</div>

<?php
            $areaIndex++;
        endforeach;
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
?>
