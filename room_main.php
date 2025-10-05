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



<!-- Preload the main room background to start fetch earlier -->
<link rel="preload" as="image" href="<?php echo htmlspecialchars($backgroundMain, ENT_QUOTES, 'UTF-8'); ?>">

<!-- Room main styles now managed by global CSS system (css/room-main.css) -->

<section id="mainRoomPage" class="main-room-section" data-bg-url="<?php echo htmlspecialchars($backgroundMain, ENT_QUOTES, 'UTF-8'); ?>">



    <?php
    // Dynamic room doors generation with error handling
    require_once __DIR__ . '/api/room_helpers.php';

try {
    $roomDoors = getRoomDoorsData();
    if (!empty($debugMode)) {
        echo "\n<!-- DEBUG: roomDoors count = " . count($roomDoors) . " -->\n";
    }

    if (empty($roomDoors)) {
        echo '<div class="no-doors-message">No rooms are currently available.</div>';
    } else {
        foreach ($roomDoors as $door):
            $roomNumber = htmlspecialchars($door['room_number']);

            // Skip Main Room (room 0) since we're already on the main room page
            if ($roomNumber == '0') {
                continue;
            }

            $roomName = htmlspecialchars((string)($door['room_name'] ?? ''));
            $doorLabel = htmlspecialchars((string)($door['door_label'] ?? ''));
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

<!-- room-main.js is now loaded by app.js per-page loader (page: room_main) -->

<!-- Fallback click script removed: handled by src/js/room-main.js and RoomModalManager -->

<!-- Room modal system loaded globally in index.php -->


