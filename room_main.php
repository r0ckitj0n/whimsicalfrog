<?php
/**
 * Main room page with clickable doors for each category
 * Now fullscreen with modal-based room navigation
 */

// Redirect to index.php if accessed directly
if (!defined('INCLUDED_FROM_INDEX')) {
    // Redirect to home if accessed directly
    header('Location: /');
    exit;
}

// Include centralized functions
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/background_helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Add debug mode flag
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Get user authentication status for header
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();
$userData = getCurrentUser() ?? [];
$welcomeMessage = $isLoggedIn ? getUsername() : '';

// Get cart information for header
$cartCount = 0;
$cartTotal = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'] ?? 0;
        $cartTotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
    }
}
$formattedCartTotal = '$' . number_format($cartTotal, 2);

// Database-driven search bar visibility for room_main (room_number = 1)
$showSearchBar = true;
try {
    Database::getInstance();
    $row = Database::queryOne("SELECT show_search_bar FROM room_settings WHERE room_number = 1 AND is_active = 1");
    $showSearchBar = $row ? (bool)$row['show_search_bar'] : true;
} catch (Throwable $e) {
    $showSearchBar = true; // Default to showing search bar on error
}

// Header configuration is handled by index.php
// This file only provides the room_main specific data

// Compute main room background URL via PHP
$backgroundMain = get_active_background('room_main') ?: '/images/backgrounds/background-room-main.webp';

?>



<!-- Room main styles now managed by global CSS system (css/room-main.css) -->

<section id="mainRoomPage" class="main-room-section" data-bg-url="<?php echo htmlspecialchars($backgroundMain, ENT_QUOTES, 'UTF-8'); ?>">



    <?php
    // Dynamic room doors generation with error handling
    require_once __DIR__ . '/api/room_helpers.php';

try {
    $roomDoors = getRoomDoorsData();

    if (empty($roomDoors)) {
        echo '<div class="no-doors-message">No rooms are currently available.</div>';
    } else {
        foreach ($roomDoors as $door):
            $roomNumber = htmlspecialchars($door['room_number']);

            // Skip Main Room (room 0) since we're already on the main room page
            if ($roomNumber == '0') {
                continue;
            }

            $roomName = htmlspecialchars($door['room_name']);
            $doorLabel = htmlspecialchars($door['door_label']);
            ?>
    <!-- <?php echo $roomName; ?> Door -->
        <div class="door-area area-<?php echo $roomNumber; ?> room-door" data-category="<?php echo $doorLabel; ?>" data-room="<?php echo $roomNumber; ?>" style="cursor: pointer;">
        <picture class="door-picture">
            <source srcset="images/signs/sign-door-room<?php echo $roomNumber; ?>.webp" type="image/webp">
            <img src="images/signs/sign-door-room<?php echo $roomNumber; ?>.png" alt="<?php echo $doorLabel; ?>" class="door-sign">
        </picture>
        <div class="door-label"><?php echo $doorLabel; ?></div>
    </div>
    
    <?php
        endforeach;
    } // End of else block
} catch (Exception $e) {
    error_log("Error loading room doors: " . $e->getMessage());
    echo '<div class="no-doors-message">Unable to load rooms at this time. Please try again later.</div>';
}
?>
</section>

<!-- Load room-main.js directly to ensure it always works -->
<script type="module">
    console.log('🚀 Loading room-main.js...');

    // Try multiple import paths
    const loadScript = async () => {
        try {
            // Try Vite dev server first with correct path
            await import('http://localhost:5176/src/js/room-main.js');
            console.log('✅ room-main.js loaded from Vite dev server');
        } catch (error) {
            console.warn('❌ Failed to load from Vite dev server:', error);
            try {
                // Try direct path
                await import('./src/js/room-main.js');
                console.log('✅ room-main.js loaded from direct path');
            } catch (error2) {
                console.error('❌ Failed to load from direct path:', error2);
                try {
                    // Try Vite proxy
                    await import('/vite-proxy.php?path=src/js/room-main.js');
                    console.log('✅ room-main.js loaded from Vite proxy');
                } catch (error3) {
                    console.error('❌ Failed to load from Vite proxy:', error3);
                }
            }
        }
    };

    loadScript();
</script>

<!-- Room modal system loaded globally in index.php -->


