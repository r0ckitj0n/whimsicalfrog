<?php
/**
 * Main room page with clickable doors for each category
 * Now fullscreen with modal-based room navigation
 */

// Include centralized functions
require_once __DIR__ . '/../includes/functions.php';
?>

<!- Room main styles now managed by global CSS system (css/room-main.css) ->

<section id="mainRoomPage" class="main-room-section">




    <?php
    // Dynamic room doors generation with error handling
    require_once __DIR__ . '/../api/room_helpers.php';
    
    try {
        $roomDoors = getRoomDoorsData();
        
        if (empty($roomDoors)) {
            echo '<div class="no-doors-message">No rooms are currently available.</div>';
        } else {
            $areaIndex = 1;
            foreach ($roomDoors as $door):
                $roomNumber = htmlspecialchars($door['room_number']);
                $roomName = htmlspecialchars($door['room_name']);
                $doorLabel = htmlspecialchars($door['door_label']);
    ?>
    <!- <?php echo $roomName; ?> Door ->
    <div class="door-area area-<?php echo $areaIndex; ?>" data-room="<?php echo $roomNumber; ?>" data-category="<?php echo $doorLabel; ?>">
        <picture class="door-picture">
            <source srcset="images/sign_door_room<?php echo $roomNumber; ?>.webp" type="image/webp">
            <img src="images/sign_door_room<?php echo $roomNumber; ?>.png" alt="<?php echo $doorLabel; ?>" class="door-sign">
        </picture>
        <div class="door-label"><?php echo $doorLabel; ?></div>
    </div>
    
    <?php
            $areaIndex++;
        endforeach;
        } // End of else block
    } catch (Exception $e) {
        error_log("Error loading room doors: " . $e->getMessage());
        echo '<div class="no-doors-message">Unable to load rooms at this time. Please try again later.</div>';
    }
    ?>
</section>

<!- Room modal functionality handled by unified js/room-modal-manager.js ->

<!- Main room functionality loaded by unified system ->

<script>
// Enhanced main room configuration for modal system
document.addEventListener('DOMContentLoaded', function() {
    console.log('🐸 Room Main: Initializing fullscreen mode');
    
    // Check door elements
    const doorElements = document.querySelectorAll('.door-area');
    console.log(`🚪 Found ${doorElements.length} door elements`);
    
    // Check main room background
    const mainRoomSection = document.getElementById('mainRoomPage');
    if (mainRoomSection) {
        console.log('🖼️ Main room background loaded');
    }
    
    // Configure main navigation
    const mainNav = document.querySelector('nav.main-nav');
    if (mainNav) {
        mainNav.classList.add('site-header');
        console.log('🧭 Navigation configured for room overlay');
    }
    
    // CSS positioning handles all door positioning
    // No JavaScript positioning needed - prevents repositioning swaps
    console.log('🚪 Using CSS-only positioning to prevent door swapping');
    
    // Handle window resize - CSS positioning is responsive and doesn't need JavaScript
    console.log('🚪 Door positioning is handled by CSS - no resize handling needed');
    
    // Set body to prevent scrolling
    document.body.style.overflow = 'hidden';
    
    console.log('🐸 Room Main: Initialization complete');
});

// Clean up when leaving main room
window.addEventListener('beforeunload', function() {
    document.body.style.overflow = '';
});
</script> 
<script>
// Auto-open modal if URL specifies a room
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const modalRoom = urlParams.get('modal_room');
    
    if (modalRoom && window.roomModalManager) {
        setTimeout(() => {
            window.roomModalManager.show(modalRoom, false);
        }, 500);
    }
});
</script>