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

<!-- Search Bar for Main Room -->
<div class="main-room-search-bar" style="text-align: center; padding: 20px 0; margin-bottom: 20px;">
    <div style="position: relative; max-width: 400px; margin: 0 auto;">
        <input type="text" id="headerSearchInput" placeholder="Search products..." 
               class="w-full px-4 py-2 pl-10 pr-4 text-sm border-2 border-[#87ac3a] rounded-full focus:outline-none focus:ring-2 focus:ring-[#87ac3a] transition-all duration-200"
               style="width: 100%; padding: 10px 12px 10px 40px; font-size: 14px; border: 2px solid #87ac3a; border-radius: 25px; background: rgba(135, 172, 58, 0.8); color: white; outline: none;">
        <div style="position: absolute; top: 50%; left: 12px; transform: translateY(-50%); pointer-events: none;">
            <svg style="width: 16px; height: 16px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>
</div>

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

<!-- Search Modal -->
<div id="searchModal" class="modal" style="display: none;">
    <div class="search-modal-content">
        <div class="search-modal-header">
            <h2 class="search-modal-title">Search Products</h2>
            <button class="search-modal-close" onclick="closeSearchModal()">&times;</button>
        </div>
        <div class="search-modal-body">
            <div id="searchResults"></div>
        </div>
    </div>
</div>

<!-- JavaScript for main room functionality -->
<script src="js/main-room.js?v=<?php echo time(); ?>"></script>
<script src="js/search.js?v=<?php echo time(); ?>"></script> 
