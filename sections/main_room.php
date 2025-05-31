<?php
// Main room page with clickable doors for each category
?>
<style>
    .main-room-container {
        background-image: url('images/room_main.webp');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 80vh;
        position: relative;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .door-area {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(2px);
        border: 2px solid transparent;
    }
    
    .door-area:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: #6B8E23;
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(107, 142, 35, 0.3);
    }
    
    .door-label {
        position: absolute;
        bottom: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(107, 142, 35, 0.9);
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: bold;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .door-area:hover .door-label {
        opacity: 1;
    }
    
    /* Door positions - adjust these based on your room_main.png layout */
    .door-tshirts { top: 34.8%; left: 8.7%; width: 13.0%; height: 47.1%; } /* Area 1 */
    .door-tumblers { top: 55.5%; left: 34.5%; width: 7.2%; height: 18.5%; } /* Area 2 */
    .door-artwork { top: 40.4%; left: 55.1%; width: 8.9%; height: 31.2%; } /* Area 3 */
    .door-sublimation { top: 38.2%; left: 73.0%; width: 13.3%; height: 36.8%; } /* Area 4 */
    .door-windowwraps { top: 19.6%; left: 53.2%; width: 6.5%; height: 10.2%; } /* Area 5 */

    .room-overlay-wrapper { /* New wrapper for aspect ratio and background */
        width: 100%;
        padding-top: 56.25%; /* 16:9 Aspect Ratio (9 / 16 * 100) - Adjust if your image aspect ratio is different */
        position: relative; /* For absolute positioning of content inside */
        background-image: url('images/room_main.webp?v=cb2');
        background-size: cover;
        background-position: center;
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room_main.png?v=cb2');
    }
</style>

<section id="mainRoomPage" class="p-2">
    <?php /* <div class="text-center mb-4">
        <a href="/?page=landing" class="inline-block transform transition-transform duration-300 hover:scale-105">
            <img src="images/webp/welcome_sign.webp" alt="Welcome to Whimsical Frog - Return to Landing Page" class="max-w-xs md:max-w-sm lg:max-w-md mx-auto rounded-lg shadow-lg" style="filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3)); max-height: 150px;">
        </a>
    </div> */ ?>
    
    <div class="main-room-container mx-auto max-w-full">
        <!-- T-Shirts Door -->
        <div class="door-area door-tshirts" onclick="enterRoom('tshirts')">
            <div class="door-label">T-Shirts & Apparel</div>
        </div>
        
        <!-- Tumblers Door -->
        <div class="door-area door-tumblers" onclick="enterRoom('tumblers')">
            <div class="door-label">Tumblers & Drinkware</div>
        </div>
        
        <!-- Artwork Door -->
        <div class="door-area door-artwork" onclick="enterRoom('artwork')">
            <div class="door-label">Custom Artwork</div>
        </div>
        
        <!-- Sublimation Door -->
        <div class="door-area door-sublimation" onclick="enterRoom('sublimation')">
            <div class="door-label">Sublimation Items</div>
        </div>
        
        <!-- Window Wraps Door -->
        <div class="door-area door-windowwraps" onclick="enterRoom('windowwraps')">
            <div class="door-label">Window Wraps</div>
        </div>
    </div>
</section>

<script>
function enterRoom(category) {
    console.log('Entering room:', category);
    window.location.href = `/?page=room_${category}`;
}
</script> 