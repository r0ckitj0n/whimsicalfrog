<?php
// Landing page section
?>
<style>
    #landingPage {
        /* Background image is handled by the body element in index.php */
        position: relative; /* For absolute positioning of clickable areas */
        width: 100%;
        height: 100vh;
        overflow: hidden;
    }
    
    .clickable-area {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: rgba(255, 255, 255, 0.0); /* Transparent background */
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .clickable-area:hover {
        transform: scale(1.1); /* Enlarge to 110% on hover */
    }
    
    .clickable-area img {
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    
    /* Area 1 base coordinates for desktop */
    .area-1 {
        top: 414px;
        left: 466px;
        width: 285px;
        height: 153px;
        border-radius: 8px;
    }
    
    .area-1 img {
        width: 100%;
        height: auto;
        display: block;
    }
    
    /* Responsive adjustments */
    @media (max-width: 767px) {
        /* On small screens centre the sign with flexbox */
        #landingPage {
            background-size: cover; /* Ensure background fills small screens */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin-top: 0;
        }

        /* Welcome sign placement */
        .area-1 {
            position: static !important;
            transform: none !important;
            width: 80vw;
            max-width: 400px;
            height: auto;
            display: block !important;
            z-index: 9999;
        }

        .clickable-area {
            position: relative !important;
            display: block;
            margin: 0 auto;
        }
    }
</style>

<section id="landingPage" class="relative">
    <a href="/?page=main_room" class="clickable-area area-1" title="Enter the Main Room">
        <picture>
            <source srcset="images/sign_welcome.webp" type="image/webp">
            <source srcset="images/sign_welcome.png" type="image/png">
            <img src="images/sign_welcome.png" alt="Welcome - Click to Enter">
        </picture>
    </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth < 1000) {
        // Mobile/tablet: CSS-only layout; skip JS adjustments entirely
        return;
    }
    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    
    // Area coordinates
    const areaCoordinates = [
        { selector: '.area-1', top: 414, left: 466, width: 285, height: 153 } // Area 1
    ];

    function positionAreas() {
        // Get viewport dimensions
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        // Calculate the scale factor for the full-screen background
        const viewportRatio = viewportWidth / viewportHeight;
        const imageRatio = originalImageWidth / originalImageHeight;
        
        let scale, offsetX, offsetY;
        
        // Calculate how the background image is displayed (cover)
        if (viewportRatio > imageRatio) {
            // Viewport is wider than image ratio, image height matches viewport height
            scale = viewportHeight / originalImageHeight;
            offsetX = (viewportWidth - (originalImageWidth * scale)) / 2;
            offsetY = 0;
        } else {
            // Viewport is taller than image ratio, image width matches viewport width
            scale = viewportWidth / originalImageWidth;
            offsetY = (viewportHeight - (originalImageHeight * scale)) / 2;
            offsetX = 0;
        }
        
        // Position each clickable area
        areaCoordinates.forEach(area => {
            const element = document.querySelector(area.selector);
            if (element) {
                // Apply scaled coordinates
                element.style.top = `${(area.top * scale) + offsetY}px`;
                element.style.left = `${(area.left * scale) + offsetX}px`;
                element.style.width = `${area.width * scale}px`;
                element.style.height = `${area.height * scale}px`;
            }
        });
    }

    // Position areas initially and on resize
    positionAreas();
    window.addEventListener('resize', positionAreas);

    // On small screens, change enter link to go directly to shop
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    if (isMobile) {
        const enterLink = document.querySelector('.area-1');
        if (enterLink) {
            enterLink.setAttribute('href', '/?page=shop');
        }
    }
});
</script>
