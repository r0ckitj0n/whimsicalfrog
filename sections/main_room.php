<?php
// Main room page with clickable doors for each category
?>
<style>
    /* Removed .main-room-container styles as it's no longer a primary positioning container */
    /* The background is now handled by the body in index.php */
    
    .door-area {
        position: absolute; /* Position relative to the nearest positioned ancestor (which will be body/html) */
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        overflow: hidden; /* Ensure content doesn't spill outside */
        pointer-events: auto; /* Make door areas clickable */
    }
    
    .door-area:hover {
        transform: scale(1.05);
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
    
    .door-sign {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
        background: transparent;
        mix-blend-mode: normal;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        image-rendering: -webkit-optimize-contrast; /* Improve image rendering */
        image-rendering: crisp-edges;
    }
    
    .door-area:hover .door-sign {
        transform: scale(1.1);
    }
    
    /* Additional transparency handling - removed as it was for the old container */

    /* Welcome sign specific styles */
    .flex-grow picture {
        background: transparent;
        display: block;
        line-height: 0; /* Remove any extra space */
    }

    .flex-grow img {
        background: transparent;
        mix-blend-mode: normal;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
        display: block; /* Remove any extra space */
        line-height: 0; /* Remove any extra space */
    }
</style>

<section id="mainRoomPage" class="p-2">
    <!-- T-Shirts Door -->
    <div class="door-area door-tshirts" onclick="enterRoom('tshirts')">
        <picture class="block">
            <source srcset="images/sign_door_tshirts.webp" type="image/webp">
            <img src="images/sign_door_tshirts.png" alt="T-Shirts & Apparel" class="door-sign">
        </picture>
        <div class="door-label">T-Shirts & Apparel</div>
    </div>
    
    <!-- Tumblers Door -->
    <div class="door-area door-tumblers" onclick="enterRoom('tumblers')">
        <picture class="block">
            <source srcset="images/sign_door_tumblers.webp" type="image/webp">
            <img src="images/sign_door_tumblers.png" alt="Tumblers & Drinkware" class="door-sign">
        </picture>
        <div class="door-label">Tumblers & Drinkware</div>
    </div>
    
    <!-- Artwork Door -->
    <div class="door-area door-artwork" onclick="enterRoom('artwork')">
        <picture class="block">
            <source srcset="images/sign_door_artwork.webp" type="image/webp">
            <img src="images/sign_door_artwork.png" alt="Custom Artwork" class="door-sign">
        </picture>
        <div class="door-label">Custom Artwork</div>
    </div>
    
    <!-- Sublimation Door -->
    <div class="door-area door-sublimation" onclick="enterRoom('sublimation')">
        <picture class="block">
            <source srcset="images/sign_door_sublimation.webp" type="image/webp">
            <img src="images/sign_door_sublimation.png" alt="Sublimation Items" class="door-sign">
        </picture>
        <div class="door-label">Sublimation Items</div>
    </div>
    
    <!-- Window Wraps Door -->
    <div class="door-area door-windowwraps" onclick="enterRoom('windowwraps')">
        <picture class="block">
            <source srcset="images/sign_door_windowwraps.webp" type="image/webp">
            <img src="images/sign_door_windowwraps.png" alt="Window Wraps" class="door-sign">
        </picture>
        <div class="door-label">Window Wraps</div>
    </div>
    
    <!-- Extra clickable areas (if needed) -->
    <div class="door-area door-area-6" onclick="enterRoom('tshirts')">
        <div class="door-label">T-Shirts & Apparel</div>
    </div>
    
    <div class="door-area door-area-7" onclick="enterRoom('tumblers')">
        <div class="door-label">Tumblers & Drinkware</div>
    </div>
    
    <div class="door-area door-area-8" onclick="enterRoom('windowwraps')">
        <div class="door-label">Window Wraps</div>
    </div>
</section>

<script>
function enterRoom(category) {
    console.log('Entering room:', category);
    window.location.href = `/?page=room_${category}`;
}

// Direct positioning script for main room doors
document.addEventListener('DOMContentLoaded', function() {
    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    
    // Door coordinates from user
    const doorCoordinates = [
        { selector: '.door-tshirts', top: 269, left: 58, width: 211, height: 195 }, // Area 1
        { selector: '.door-tumblers', top: 424, left: 407, width: 183, height: 186 }, // Area 2
        { selector: '.door-artwork', top: 323, left: 743, width: 100, height: 80 }, // Area 3 (added clickable dimensions)
        { selector: '.door-windowwraps', top: 324, left: 743, width: 163, height: 194 }, // Area 4
        { selector: '.door-sublimation', top: 490, left: 1172, width: 100, height: 80 }, // Area 5 (added clickable dimensions)
        { selector: '.door-area-6', top: 622, left: 593, width: 244, height: 162 } // Area 6
    ];

    function positionDoors() {
        // Get viewport dimensions - use full viewport
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        // Calculate the scale factor for the full-screen background
        const viewportRatio = viewportWidth / viewportHeight;
        const imageRatio = originalImageWidth / originalImageHeight;
        
        let scale, offsetX, offsetY;
        
        // Calculate how the background image is displayed (cover)
        if (viewportRatio > imageRatio) {
            // Viewport is wider than image ratio, image width matches viewport width
            scale = viewportWidth / originalImageWidth;
            offsetY = (viewportHeight - (originalImageHeight * scale)) / 2;
            offsetX = 0;
        } else {
            // Viewport is taller than image ratio, image height matches viewport height
            scale = viewportHeight / originalImageHeight;
            offsetX = (viewportWidth - (originalImageWidth * scale)) / 2;
            offsetY = 0;
        }
        
        console.log('Viewport dimensions:', viewportWidth, 'x', viewportHeight);
        console.log('Scale:', scale, 'Offsets:', offsetX, offsetY);
        
        // Position each door
        doorCoordinates.forEach(door => {
            const element = document.querySelector(door.selector);
            if (element) {
                // Apply scaled coordinates
                element.style.top = `${(door.top * scale) + offsetY}px`;
                element.style.left = `${(door.left * scale) + offsetX}px`;
                element.style.width = `${door.width * scale}px`;
                element.style.height = `${door.height * scale}px`;
                console.log(`Positioned ${door.selector}:`, element.style.top, element.style.left);
            }
        });
    }

    // Position doors initially and on resize
    positionDoors();
    window.addEventListener('resize', positionDoors);
});
</script>
