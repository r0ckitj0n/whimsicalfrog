<?php
/**
 * Main room page with clickable doors for each category
 * Fully dynamic version that loads room names and settings from database
 */

// Include centralized functions
require_once __DIR__ . '/../includes/functions.php';

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
<style>
.main-room-section.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 40;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-image: url('images/room_main.webp');
}

.webp .main-room-section.fullscreen {
    background-image: url('images/room_main.webp');
}

.main-room-section .door-area {
    position: absolute;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    /* Coordinates will be set by JavaScript, not CSS */
}

.main-room-section .door-area:hover {
    transform: scale(1.05);
}

.main-room-section .door-picture {
    width: 100%;
    height: 100%;
    display: block;
}

.main-room-section .door-sign {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: filter 0.3s ease;
}

.main-room-section .door-area:hover .door-sign {
    filter: brightness(1.1) drop-shadow(0 0 10px rgba(135, 172, 58, 0.5));
}

.main-room-section .door-label {
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: #87ac3a;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.main-room-section .door-area:hover .door-label {
    opacity: 1;
}

/* Main room title overlay (if configured) */
.main-room-title {
    position: absolute;
    top: 2rem;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    color: #87ac3a;
    background: rgba(255, 255, 255, 0.95);
    padding: 1rem 2rem;
    border-radius: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    font-family: 'Merienda', cursive;
    z-index: 20;
}

.main-room-title h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: bold;
}

.main-room-title p {
    margin: 0;
    font-size: 1rem;
    opacity: 0.8;
}

/* Loading states */
.door-area.loading {
    opacity: 0.7;
    pointer-events: none;
}

.door-area.loading .door-sign {
    filter: grayscale(0.5);
}
</style>

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
        document.body.classList.add('main-room-fullscreen');
        
        // Hide navigation in full-screen mode
        const nav = document.querySelector('nav.main-nav');
        if (nav) {
            nav.style.display = 'none';
        }
        
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
