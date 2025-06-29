<?php
/**
 * Main room page with clickable doors for each category
 * Cleaned up version with centralized CSS and improved structure
 */

// Include centralized functions
require_once __DIR__ . '/../includes/functions.php';
?>

<!-- CSS link for main room styles -->
<link href="css/main-room.css?v=<?php echo time(); ?>" rel="stylesheet">

<section id="mainRoomPage" class="main-room-section">
    <!-- T-Shirts Door -->
    <div class="door-area area-1" onclick="enterRoom(2)" data-room="2" data-category="T-Shirts & Apparel">
        <picture class="door-picture">
            <source srcset="images/sign_door_room2.webp" type="image/webp">
            <img src="images/sign_door_room2.png" alt="T-Shirts & Apparel" class="door-sign">
        </picture>
        <div class="door-label">T-Shirts & Apparel</div>
    </div>
    
    <!-- Tumblers Door -->
    <div class="door-area area-2" onclick="enterRoom(3)" data-room="3" data-category="Tumblers & Drinkware">
        <picture class="door-picture">
            <source srcset="images/sign_door_room3.webp" type="image/webp">
            <img src="images/sign_door_room3.png" alt="Tumblers & Drinkware" class="door-sign">
        </picture>
        <div class="door-label">Tumblers & Drinkware</div>
    </div>
    
    <!-- Artwork Door -->
    <div class="door-area area-3" onclick="enterRoom(4)" data-room="4" data-category="Custom Artwork">
        <picture class="door-picture">
            <source srcset="images/sign_door_room4.webp" type="image/webp">
            <img src="images/sign_door_room4.png" alt="Custom Artwork" class="door-sign">
        </picture>
        <div class="door-label">Custom Artwork</div>
    </div>
    
    <!-- Window Wraps Door -->
    <div class="door-area area-4" onclick="enterRoom(6)" data-room="6" data-category="Window Wraps">
        <picture class="door-picture">
            <source srcset="images/sign_door_room6.webp" type="image/webp">
            <img src="images/sign_door_room6.png" alt="Window Wraps" class="door-sign">
        </picture>
        <div class="door-label">Window Wraps</div>
    </div>
    
    <!-- Sublimation Door -->
    <div class="door-area area-5" onclick="enterRoom(5)" data-room="5" data-category="Sublimation Items">
        <picture class="door-picture">
            <source srcset="images/sign_door_room5.webp" type="image/webp">
            <img src="images/sign_door_room5.png" alt="Sublimation Items" class="door-sign">
        </picture>
        <div class="door-label">Sublimation Items</div>
    </div>
</section>

<!-- JavaScript for main room functionality -->
<script src="js/main-room.js?v=<?php echo time(); ?>"></script> 
