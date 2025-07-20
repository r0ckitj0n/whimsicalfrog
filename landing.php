<?php
// Landing page section
?>


<section id="landingPage" class="relative">
                    <a href="/?page=room_main" class="clickable-area area-1" title="Enter the Main Room" onclick="event.preventDefault(); window.location.href = '/?page=room_main';">
        <picture>
            <source srcset="images/signs/sign_welcome.webp" type="image/webp">
            <source srcset="images/signs/sign_welcome.png" type="image/png">
            <img src="images/signs/sign_welcome.png" alt="Welcome - Click to Enter">
        </picture>
    </a>
</section>

<?php
try {
    $stmt = Database::getInstance()->prepare("SELECT coordinates FROM room_maps WHERE room_type = 'landing' AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $map = $stmt->fetch(PDO::FETCH_ASSOC);
    $landingCoordsJson = $map ? $map['coordinates'] : '[]';
} catch (Exception $e) {
    $landingCoordsJson = '[]';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ensure fullscreen styling applies
    document.body.classList.add('mode-fullscreen');
    console.log('🎯 Landing page positioning script loaded');
    console.log('🎯 Window width:', window.innerWidth);
    
    if (window.innerWidth < 1000) {
        // Mobile/tablet: CSS-only layout; skip JS adjustments entirely
        console.log('🎯 Mobile layout detected, skipping JS positioning');
        return;
    }
    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    
    // Load server-side coordinates
    const rawAreaCoords = <?php echo $landingCoordsJson; ?>;
    const areaCoordinates = rawAreaCoords.map(area => {
        // Ensure selector has exactly one leading dot
        let sel = area.selector || '';
        const selector = sel.startsWith('.') ? sel : '.' + sel;
        return {
            selector,
            top: area.top,
            left: area.left,
            width: area.width,
            height: area.height
        };
    });

    function positionAreas() { // positions using current areaCoordinates
        console.log('🎯 positionAreas() called');
        // Get viewport dimensions
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        console.log('🎯 Viewport dimensions:', viewportWidth, 'x', viewportHeight);
        
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
            console.log('🎯 Looking for element:', area.selector, 'Found:', element);
            if (element) {
                // Apply scaled coordinates
                const newTop = `${(area.top * scale) + offsetY}px`;
                const newLeft = `${(area.left * scale) + offsetX}px`;
                const newWidth = `${area.width * scale}px`;
                const newHeight = `${area.height * scale}px`;
                
                element.style.top = newTop;
                element.style.left = newLeft;
                element.style.width = newWidth;
                element.style.height = newHeight;
                
                console.log('🎯 Positioned', area.selector, 'to:', newTop, newLeft, newWidth, newHeight);
            } else {
                console.warn('🎯 Element not found:', area.selector);
            }
        });
    }

    // Initialize positioning with server-side data
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
