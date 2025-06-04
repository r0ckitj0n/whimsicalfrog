<?php
// Main room page with clickable doors for each category
?>
<style>
    .main-room-container {
        /* Removed background-image as it's now handled by the body in index.php */
        background-color: transparent; /* Ensure background is transparent */
        position: relative;
        border-radius: 15px;
        overflow: hidden;
        padding-top: 70%; /* 1280x896 Aspect Ratio (896/1280 * 100) */
        opacity: 1;
        mix-blend-mode: normal;
    }
    
    /* Removed WebP support rules for .main-room-container as background is now on body */
    
    .door-area {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        overflow: hidden; /* Ensure content doesn't spill outside */
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
    
    /* Door positions - updated with pixel values - Now handled by JavaScript */
    /* .door-tshirts { top: 301px; left: 104px; width: 158px; height: 348px; } */ /* Area 1 */
    /* .door-tumblers { top: 463px; left: 414px; width: 84px; height: 157px; } */ /* Area 2 */
    /* .door-artwork { top: 168px; left: 640px; width: 77px; height: 124px; } */ /* Area 3 */
    /* .door-sublimation { top: 344px; left: 663px; width: 103px; height: 258px; } */ /* Area 4 */
    /* .door-windowwraps { top: 323px; left: 879px; width: 153px; height: 306px; } */ /* Area 5 */

    /* .room-overlay-wrapper { ... } was here, removed as it seemed redundant with .main-room-container styles */

    /* Additional transparency handling */
    .main-room-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: transparent;
        pointer-events: none;
    }

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
    <div class="main-room-container mx-auto max-w-full">
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
    </div>
</section>

<script>
function enterRoom(category) {
    console.log('Entering room:', category);
    window.location.href = `/?page=room_${category}`;
}

// Script to dynamically scale door areas
document.addEventListener('DOMContentLoaded', function() {
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomContainer = document.querySelector('#mainRoomPage .main-room-container');

    const baseAreas = [
        { selector: '.door-tshirts', top: 477, left: 246, width: 100, height: 80 }, // Area 1
        { selector: '.door-tumblers', top: 611, left: 564, width: 100, height: 80 }, // Area 2
        { selector: '.door-artwork', top: 278, left: 747, width: 100, height: 80 }, // Area 3
        { selector: '.door-windowwraps', top: 467, left: 892, width: 100, height: 80 }, // Area 4
        { selector: '.door-sublimation', top: 319, left: 998, width: 100, height: 80 }, // Area 5
        { selector: '.door-area-6', top: 323, left: 1003, width: 100, height: 80 }, // Area 6
        { selector: '.door-area-7', top: 323, left: 1003, width: 100, height: 80 }, // Area 7
        { selector: '.door-area-8', top: 537, left: 1164, width: 100, height: 80 } // Area 8
    ];

    function updateAreaCoordinates() {
        if (!roomContainer) {
            console.error('Main Room container not found for scaling.');
            return;
        }

        const wrapperWidth = roomContainer.offsetWidth;
        const wrapperHeight = roomContainer.offsetHeight;

        const wrapperAspectRatio = wrapperWidth / wrapperHeight;
        const imageAspectRatio = originalImageWidth / originalImageHeight;

        let renderedImageWidth, renderedImageHeight;
        let offsetX = 0;
        let offsetY = 0;

        if (wrapperAspectRatio > imageAspectRatio) {
            renderedImageHeight = wrapperHeight;
            renderedImageWidth = renderedImageHeight * imageAspectRatio;
            offsetX = (wrapperWidth - renderedImageWidth) / 2;
        } else {
            renderedImageWidth = wrapperWidth;
            renderedImageHeight = renderedImageWidth / imageAspectRatio;
            offsetY = (wrapperHeight - renderedImageHeight) / 2;
        }

        const scaleX = renderedImageWidth / originalImageWidth;
        const scaleY = renderedImageHeight / originalImageHeight;

        baseAreas.forEach(areaData => {
            const areaElement = roomContainer.querySelector(areaData.selector);
            if (areaElement) {
                areaElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
                areaElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
                areaElement.style.width = (areaData.width * scaleX) + 'px';
                areaElement.style.height = (areaData.height * scaleY) + 'px';
            } else {
                // console.warn('Area element not found in Main room:', areaData.selector);
            }
        });
    }

    updateAreaCoordinates();
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateAreaCoordinates, 100);
    });
});
</script> 
