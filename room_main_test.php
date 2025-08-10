<?php
/**
 * Standalone Room Main Test Page
 * Tests the complete modal functionality
 */

// Include necessary files
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/background_helpers.php';
require_once __DIR__ . '/api/room_helpers.php';

// Get background
$backgroundMain = get_active_background('room_main') ?: '/images/backgrounds/background_room_main.webp';

// Get room doors data
try {
    $roomDoors = getRoomDoorsData();
} catch (Exception $e) {
    $roomDoors = [];
    error_log("Error loading room doors: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Main Test - WhimsicalFrog</title>
    <link href="css/bundle.css" rel="stylesheet">

</head>
<body>
    <div class="test-header" id="testStatus">
        Loading...
    </div>

    <section id="mainRoomPage" class="main-room-section" data-bg-url="<?php echo htmlspecialchars($backgroundMain, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (empty($roomDoors)): ?>
            <div class="no-doors-message">No rooms are currently available.</div>
        <?php else: ?>
            <?php 
            $areaIndex = 1;
            foreach ($roomDoors as $door):
                $roomNumber = htmlspecialchars($door['room_number']);
                $roomName = htmlspecialchars($door['room_name']);
                $doorLabel = htmlspecialchars($door['door_label']);
            ?>
            <!-- <?php echo $roomName; ?> Door -->
            <div class="door-area area-<?php echo $areaIndex; ?>" data-room="<?php echo $roomNumber; ?>" data-category="<?php echo $doorLabel; ?>">
                <picture class="door-picture">
                    <source srcset="images/signs/sign_door_room<?php echo $roomNumber; ?>.webp" type="image/webp">
                    <img src="images/signs/sign_door_room<?php echo $roomNumber; ?>.png" alt="<?php echo $doorLabel; ?>" class="door-sign">
                </picture>
                <div class="door-label"><?php echo $doorLabel; ?></div>
            </div>
            <?php
                $areaIndex++;
            endforeach;
            ?>
        <?php endif; ?>
    </section>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const testStatus = document.getElementById('testStatus');
            
            // Test 1: Check if room main manager is loaded
            setTimeout(() => {
                let status = '';
                let isSuccess = true;
                
                // Check RoomModalManager
                if (window.roomModalManager) {
                    status += '✅ RoomModalManager loaded\n';
                } else {
                    status += '❌ RoomModalManager missing\n';
                    isSuccess = false;
                }
                
                // Check door elements
                const doors = document.querySelectorAll('.door-area');
                status += `✅ Found ${doors.length} door elements\n`;
                
                // Check background
                const mainRoom = document.getElementById('mainRoomPage');
                if (mainRoom) {
                    const bgUrl = mainRoom.getAttribute('data-bg-url');
                    if (bgUrl) {
                        mainRoom.style.setProperty('--room-bg-url', `url('${bgUrl}')`);
                        status += '✅ Background configured\n';
                    }
                }
                
                // Check CSS variables
                const computedStyle = getComputedStyle(document.documentElement);
                const doorZIndex = computedStyle.getPropertyValue('--z-door-areas');
                if (doorZIndex) {
                    status += `✅ Z-index variable: ${doorZIndex}\n`;
                } else {
                    status += '❌ Z-index variable missing\n';
                    isSuccess = false;
                }
                
                testStatus.textContent = status;
                testStatus.className = isSuccess ? 'test-header success' : 'test-header error';
                
                // Test door clicks
                doors.forEach((door, index) => {
                    door.addEventListener('click', function() {
                        console.log(`🚪 Door ${index + 1} clicked (Room ${this.dataset.room})`);
                        if (window.roomModalManager) {
                            window.roomModalManager.show(this.dataset.room);
                        }
                    });
                });
                
            }, 1000);
        });
    </script>
</body>
</html>
