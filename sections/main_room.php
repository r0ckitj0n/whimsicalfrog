<?php
// Main room page with clickable doors for each category
?>
<style>
    .main-room-container {
        background-image: url('images/room_main.webp');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        padding-top: 70%; /* 1280x896 Aspect Ratio (896/1280 * 100) */
        position: relative;
        border-radius: 15px;
        overflow: hidden;
        opacity: 1;
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
    
    /* Door positions - updated with pixel values - Now handled by JavaScript */
    /* .door-tshirts { top: 301px; left: 104px; width: 158px; height: 348px; } */ /* Area 1 */
    /* .door-tumblers { top: 463px; left: 414px; width: 84px; height: 157px; } */ /* Area 2 */
    /* .door-artwork { top: 168px; left: 640px; width: 77px; height: 124px; } */ /* Area 3 */
    /* .door-sublimation { top: 344px; left: 663px; width: 103px; height: 258px; } */ /* Area 4 */
    /* .door-windowwraps { top: 323px; left: 879px; width: 153px; height: 306px; } */ /* Area 5 */

    /* .room-overlay-wrapper { ... } was here, removed as it seemed redundant with .main-room-container styles */

    .no-webp .main-room-container {
        /* Ensure PNG fallback for main-room-container if no-webp is active */
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

// Script to dynamically scale door areas
document.addEventListener('DOMContentLoaded', function() {
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomContainer = document.querySelector('#mainRoomPage .main-room-container');

    const baseAreas = [
        { selector: '.door-tshirts', top: 321, left: 124, width: 158, height: 348 },     // Orig: 301, 104
        { selector: '.door-tumblers', top: 483, left: 434, width: 84, height: 157 },    // Orig: 463, 414
        { selector: '.door-artwork', top: 188, left: 660, width: 77, height: 124 },      // Orig: 168, 640
        { selector: '.door-sublimation', top: 364, left: 683, width: 103, height: 258 },// Orig: 344, 663
        { selector: '.door-windowwraps', top: 343, left: 899, width: 153, height: 306 } // Orig: 323, 879
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