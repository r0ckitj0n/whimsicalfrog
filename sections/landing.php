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
        background-color: rgba(255, 255, 255, 0.1); /* Slightly visible on hover */
    }
    
    .clickable-area img {
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    
    /* Area 1 base coordinates */
    .area-1 { 
        top: 411px; 
        left: 601px; 
        width: 125px; 
        height: 77px; 
        background-color: #fff; /* White background for product areas, fully opaque */
        border-radius: 8px;
    }
</style>

<section id="landingPage" class="relative">
    <a href="/?page=main_room" class="clickable-area area-1" title="Enter the Main Room">
        <img src="images/sign_welcome.webp" alt="Welcome - Click to Enter" onerror="this.onerror=null; this.src='images/sign_welcome.png';">
    </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    
    // Area coordinates
    const areaCoordinates = [
        { selector: '.area-1', top: 411, left: 601, width: 125, height: 77 } // Area 1
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
        
        console.log('Landing page - Viewport dimensions:', viewportWidth, 'x', viewportHeight);
        console.log('Landing page - Scale:', scale, 'Offsets:', offsetX, offsetY);
        
        // Position each clickable area
        areaCoordinates.forEach(area => {
            const element = document.querySelector(area.selector);
            if (element) {
                // Apply scaled coordinates
                element.style.top = `${(area.top * scale) + offsetY}px`;
                element.style.left = `${(area.left * scale) + offsetX}px`;
                element.style.width = `${area.width * scale}px`;
                element.style.height = `${area.height * scale}px`;
                console.log(`Positioned ${area.selector}:`, element.style.top, element.style.left);
            }
        });
    }

    // Position areas initially and on resize
    positionAreas();
    window.addEventListener('resize', positionAreas);
});
</script>
