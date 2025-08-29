<<<<<<< HEAD

<!-- Database-driven CSS for room_main -->
<style id="room_main-css">
/* CSS will be loaded from database */
</style>

<!-- Database-driven CSS for room_template -->
<style id="room_template-css">
/* CSS will be loaded from database */
</style>

<!-- Database-driven CSS for rooms -->
<style id="rooms-css">
/* CSS will be loaded from database */
</style>

<!-- Database-driven CSS for room_layout -->
<style id="room_layout-css">
/* CSS will be loaded from database */
</style>

<script>
    // Load CSS from database - multiple categories like other room pages
    async function loadRoom_mainCSS() {
        try {
            const categories = ['room_main', 'room_template', 'rooms', 'room_layout'];
            
            for (const category of categories) {
                const response = await fetch(`/api/css_generator.php?category=${category}`);
                const cssText = await response.text();
                const styleElement = document.getElementById(`${category}-css`);
                if (styleElement && cssText) {
                    styleElement.textContent = cssText;
                    console.log(`✅ ${category} CSS loaded from database`);
                }
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load room CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>Room CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadRoom_mainCSS);
</script>

<?php
/**
 * Main room page with clickable doors for each category
 * Fully dynamic version that loads room names and settings from database
=======
<?php
/**
 * Main room page with clickable doors for each category
 * Now fullscreen with modal-based room navigation
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
 */

// Include centralized functions
require_once __DIR__ . '/../includes/functions.php';
<<<<<<< HEAD

// Get main room settings and full-screen configuration
$mainRoomSettings = null;
$isFullScreen = false;
$showMainRoomTitle = false;
$roomDoors = [];

try {
    $pdo = Database::getInstance();
    
    // Get main room settings (room 1)
    $stmt = $pdo->prepare("SELECT * FROM room_settings WHERE room_number = 1");
    $stmt->execute();
    $mainRoomSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if full-screen mode is enabled for main room
    $stmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE setting_key = 'main_room_fullscreen' AND category = 'rooms'");
    $stmt->execute();
    $fullScreenSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    $isFullScreen = $fullScreenSetting ? filter_var($fullScreenSetting['setting_value'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Check if main room title should be displayed (disabled by default)
    $stmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE setting_key = 'main_room_show_title' AND category = 'rooms'");
    $stmt->execute();
    $showTitleSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    $showMainRoomTitle = $showTitleSetting ? filter_var($showTitleSetting['setting_value'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Get all room doors with their current names and settings
    $stmt = $pdo->prepare("
        SELECT rs.room_number, rs.room_name, rs.door_label, rs.description, rs.is_active,
               rca.category_id, c.name as category_name
        FROM room_settings rs
        LEFT JOIN room_category_assignments rca ON rs.room_number = rca.room_number AND rca.is_primary = 1
        LEFT JOIN categories c ON rca.category_id = c.id
        WHERE rs.room_number BETWEEN 2 AND 6 AND rs.is_active = 1
        ORDER BY rs.display_order, rs.room_number
    ");
    $stmt->execute();
    $roomDoors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error loading main room settings: " . $e->getMessage());
    // Fallback to defaults
    $isFullScreen = false;
    $roomDoors = [
        ['room_number' => 2, 'door_label' => 'T-Shirts & Apparel', 'room_name' => 'T-Shirts & Apparel', 'category_name' => 'T-Shirts'],
        ['room_number' => 3, 'door_label' => 'Tumblers & Drinkware', 'room_name' => 'Tumblers & Drinkware', 'category_name' => 'Tumblers'],
        ['room_number' => 4, 'door_label' => 'Custom Artwork', 'room_name' => 'Custom Artwork', 'category_name' => 'Artwork'],
        ['room_number' => 5, 'door_label' => 'Sublimation Items', 'room_name' => 'Sublimation Items', 'category_name' => 'Sublimation'],
        ['room_number' => 6, 'door_label' => 'Window Wraps', 'room_name' => 'Window Wraps', 'category_name' => 'Window Wraps']
    ];
}

// Apply full-screen class if enabled
$sectionClass = $isFullScreen ? 'main-room-section fullscreen' : 'main-room-section';
?>

<!-- Dynamic Main Room Styles -->


<section id="mainRoomPage" class="<?php echo $sectionClass; ?>">
    <!-- Main Room Title (if configured and enabled) -->
    <?php if ($showMainRoomTitle && $mainRoomSettings && ($mainRoomSettings['room_name'] || $mainRoomSettings['description'])): ?>
    <div class="main-room-title">
        <?php if ($mainRoomSettings['room_name']): ?>
            <h1><?php echo htmlspecialchars($mainRoomSettings['room_name']); ?></h1>
        <?php endif; ?>
        <?php if ($mainRoomSettings['description']): ?>
            <p><?php echo htmlspecialchars($mainRoomSettings['description']); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Dynamic Room Doors -->
    <?php 
    $areaIndex = 1;
    foreach ($roomDoors as $door): 
        $roomNumber = $door['room_number'];
        $doorLabel = htmlspecialchars($door['door_label'] ?: $door['room_name']);
        $categoryName = htmlspecialchars($door['category_name'] ?: $door['room_name']);
    ?>
    <div class="door-area area-<?php echo $areaIndex; ?>" 
         onclick="enterRoom(<?php echo $roomNumber; ?>)" 
         data-room="<?php echo $roomNumber; ?>" 
         data-category="<?php echo $categoryName; ?>"
         data-door-label="<?php echo $doorLabel; ?>">
        <picture class="door-picture">
            <source srcset="images/sign_door_room<?php echo $roomNumber; ?>.webp" type="image/webp">
            <img src="images/sign_door_room<?php echo $roomNumber; ?>.png" 
                 alt="<?php echo $doorLabel; ?>" 
                 class="door-sign">
        </picture>
        <div class="door-label"><?php echo $doorLabel; ?></div>
    </div>
    <?php 
    $areaIndex++;
    endforeach; 
    ?>
</section>

<!-- Enhanced Main Room JavaScript -->
<script src="js/main-room.js?v=<?php echo time(); ?>"></script>

<script>
// Enhanced main room configuration with database-driven settings
document.addEventListener('DOMContentLoaded', function() {
    // Pass server-side data to JavaScript
    window.MainRoomData = {
        isFullScreen: <?php echo json_encode($isFullScreen); ?>,
        doors: <?php echo json_encode($roomDoors); ?>,
        settings: <?php echo json_encode($mainRoomSettings); ?>
    };
    
    // Apply full-screen adjustments if needed
    if (window.MainRoomData.isFullScreen) {
        // Class is now applied via PHP in index.php for reliable timing
        
        // Add logout link in full-screen mode
        const logoutLink = document.createElement('a');
        logoutLink.innerHTML = 'Logout';
        logoutLink.href = '/logout.php';
        logoutLink.className = 'logout-fullscreen-link';
        logoutLink.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 50;
            background: rgba(135, 172, 58, 0.9);
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
            display: inline-block;
        `;
        logoutLink.onmouseover = () => logoutLink.style.background = 'rgba(135, 172, 58, 1)';
        logoutLink.onmouseout = () => logoutLink.style.background = 'rgba(135, 172, 58, 0.9)';
        
        document.body.appendChild(logoutLink);
    }
    
    console.log('Main room loaded with', window.MainRoomData.doors.length, 'doors');
    console.log('Full-screen mode:', window.MainRoomData.isFullScreen);
});
</script> 
=======
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
