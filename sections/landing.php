<<<<<<< HEAD

<!-- Database-driven CSS for landing -->
<style id="landing-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadLandingCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=landing');
            const cssText = await response.text();
            const styleElement = document.getElementById('landing-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ landing CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load landing CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>landing CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadLandingCSS);
</script>


<!-- Database-driven CSS for landing -->

<script>
    // Load CSS from database
    async function loadLandingCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=landing');
            const cssText = await response.text();
            const styleElement = document.getElementById('landing-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ landing CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load landing CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>landing CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadLandingCSS);
</script>


<!-- Database-driven CSS for landing -->

<script>
    // Load CSS from database
    async function loadLandingCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=landing');
            const cssText = await response.text();
            const styleElement = document.getElementById('landing-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ landing CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load landing CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>landing CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadLandingCSS);
</script>

=======
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
<?php
// Landing page section
?>


<section id="landingPage" class="relative">
<<<<<<< HEAD
    <a href="/?page=room_main" class="clickable-area area-1" title="Enter the Main Room">
=======
                    <a href="/?page=room_main" class="clickable-area area-1" title="Enter the Main Room" onclick="event.preventDefault(); window.location.href = '/?page=room_main';">
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        <picture>
            <source srcset="images/sign_welcome.webp" type="image/webp">
            <source srcset="images/sign_welcome.png" type="image/png">
            <img src="images/sign_welcome.png" alt="Welcome - Click to Enter">
        </picture>
    </a>
</section>

<<<<<<< HEAD
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for logout success notification
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('logout') === 'success') {
        // Show logout success notification
        if (window.showSuccess && typeof window.showSuccess === 'function') {
            window.showSuccess('You have been logged out successfully. Thank you for visiting Whimsical Frog! 👋', { 
                duration: 4000,
                persistent: false 
            });
        } else {
            // Fallback notification
            console.log('✅ User logged out successfully');
        }
        
        // Clean up URL by removing the logout parameter
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('logout');
        window.history.replaceState({}, '', newUrl);
    }
    
    // Original landing page code below
    if (window.innerWidth < 1000) {
        // Mobile/tablet: CSS-only layout; skip JS adjustments entirely
=======
<?php
try {
    $stmt = Database::getInstance()->prepare("SELECT coordinates FROM room_maps WHERE room_type = 'landing' AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $map = $stmt->fetch(PDO::FETCH_ASSOC);
    $landingCoordsJson = $map ? $map['coordinates'] : '[]';
} catch (Exception $e) {
    $landingCoordsJson = '[]';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Landing page positioning script loaded');
    console.log('🎯 Window width:', window.innerWidth);
    
    if (window.innerWidth < 1000) {
        // Mobile/tablet: CSS-only layout; skip JS adjustments entirely
        console.log('🎯 Mobile layout detected, skipping JS positioning');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        return;
    }
    // Original image dimensions
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    
<<<<<<< HEAD
    // Area coordinates
    const areaCoordinates = [
        { selector: '.area-1', top: 414, left: 466, width: 285, height: 153 } // Area 1
    ];

    function positionAreas() {
        // Get viewport dimensions
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
=======
    // Load server-side coordinates
    const rawAreaCoords = <?php echo $landingCoordsJson; ?>;
    const areaCoordinates = rawAreaCoords.map(area => ({
        selector: '.' + area.selector,
        top: area.top,
        left: area.left,
        width: area.width,
        height: area.height
    }));

    function positionAreas() { // positions using current areaCoordinates
        console.log('🎯 positionAreas() called');
        // Get viewport dimensions
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        console.log('🎯 Viewport dimensions:', viewportWidth, 'x', viewportHeight);
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        
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
<<<<<<< HEAD
            if (element) {
                // Apply scaled coordinates
                element.style.top = `${(area.top * scale) + offsetY}px`;
                element.style.left = `${(area.left * scale) + offsetX}px`;
                element.style.width = `${area.width * scale}px`;
                element.style.height = `${area.height * scale}px`;
=======
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
        });
    }

<<<<<<< HEAD
    // Position areas initially and on resize
=======
    // Initialize positioning with server-side data
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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
