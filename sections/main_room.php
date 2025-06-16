<?php
// Main room page with clickable doors for each category
?>
<style>
    .door-area {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        /* overflow: hidden; */
        pointer-events: auto;
    }
    
    .door-area:hover {
        transform: scale(1.05);
    }
    
    .door-label {
        display: none;
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
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
    
    .door-area:hover .door-sign {
        transform: scale(1.1);
    }
    
    /* Welcome sign specific styles */
    .flex-grow picture {
        background: transparent;
        display: block;
        line-height: 0;
    }

    .flex-grow img {
        background: transparent;
        mix-blend-mode: normal;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
        display: block;
        line-height: 0;
    }

    /* Remove special overflow and debug borders for Sublimation */
    #mainRoomPage { overflow: visible !important; }
    .door-area.area-5, .door-area.area-5 .door-sign { border: none !important; overflow: unset !important; }
</style>

<section id="mainRoomPage" class="p-2">
        <!-- T-Shirts Door -->
    <div class="door-area area-1" onclick="enterRoom('tshirts')">
        <picture class="block">
            <source srcset="images/sign_door_tshirts.webp" type="image/webp">
            <img src="images/sign_door_tshirts.png" alt="T-Shirts & Apparel" class="door-sign">
        </picture>
            <div class="door-label">T-Shirts & Apparel</div>
        </div>
        
        <!-- Tumblers Door -->
    <div class="door-area area-2" onclick="enterRoom('tumblers')">
        <picture class="block">
            <source srcset="images/sign_door_tumblers.webp" type="image/webp">
            <img src="images/sign_door_tumblers.png" alt="Tumblers & Drinkware" class="door-sign">
        </picture>
            <div class="door-label">Tumblers & Drinkware</div>
        </div>
        
        <!-- Artwork Door -->
    <div class="door-area area-3" onclick="enterRoom('artwork')">
        <picture class="block">
            <source srcset="images/sign_door_artwork.webp" type="image/webp">
            <img src="images/sign_door_artwork.png" alt="Custom Artwork" class="door-sign">
        </picture>
            <div class="door-label">Custom Artwork</div>
        </div>
        
        <!-- Window Wraps Door -->
    <div class="door-area area-4" onclick="enterRoom('windowwraps')">
        <picture class="block">
            <source srcset="images/sign_door_windowwraps.webp" type="image/webp">
            <img src="images/sign_door_windowwraps.png" alt="Window Wraps" class="door-sign">
        </picture>
            <div class="door-label">Window Wraps</div>
        </div>
    
    <!-- Sublimation Items Door -->
    <div class="door-area area-5" onclick="enterRoom('sublimation')">
        <picture class="block">
            <source srcset="images/sign_door_sublimation.webp" type="image/webp">
            <img src="images/sign_door_sublimation.png" alt="Sublimation Items" class="door-sign">
        </picture>
        <div class="door-label">Sublimation Items</div>
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
        { selector: '.area-1', top: 243, left: 30, width: 234, height: 233 }, // Area 1 (T-Shirts)
        { selector: '.area-2', top: 403, left: 390, width: 202, height: 241 }, // Area 2 (Tumblers)
        { selector: '.area-3', top: 271, left: 753, width: 170, height: 235 }, // Area 3 (Artwork)
        { selector: '.area-4', top: 291, left: 1001, width: 197, height: 255 }, // Area 4 (Window Wraps)
        { selector: '.area-5', top: 157, left: 486, width: 190, height: 230 } // Area 5 (Sublimation)
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
