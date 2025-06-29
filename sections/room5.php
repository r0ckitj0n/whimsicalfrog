<?php
/**
 * Room 5 (Sublimation) - Clean implementation using RoomHelper
 * Eliminates code duplication and uses centralized functionality
 */

// Extract room number from URL
$roomNumber = '5';

// Include required helpers
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/room_helper.php';
require_once __DIR__ . '/../includes/item_image_helpers.php';

// Initialize room helper
$roomHelper = new RoomHelper($roomNumber);

// Load room data using existing categories
$roomHelper->loadRoomData($categories ?? []);

// Output SEO tags
echo $roomHelper->renderSeoTags();

// Output CSS links
echo $roomHelper->renderCssLinks();
?>

<!-- Load Global CSS Variables -->
<script>
// Load and inject global CSS variables
async function loadGlobalCSS() {
    try {
        const response = await fetch('/api/global_css_rules.php?action=generate_css');
        const data = await response.json();
        
        if (data.success && data.css_content) {
            // Create or update global CSS style element
            let globalStyle = document.getElementById('globalCSSVariables');
            if (!globalStyle) {
                globalStyle = document.createElement('style');
                globalStyle.id = 'globalCSSVariables';
                document.head.appendChild(globalStyle);
            }
            globalStyle.textContent = data.css_content;
        }
    } catch (error) {
        // Silently fail - CSS variables will use defaults
    }
}

// Load global CSS when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadGlobalCSS();
});
</script>

<!-- Global Product Popup -->
<?php 
require_once __DIR__ . '/../components/global_popup.php';
echo renderGlobalPopup();
echo renderGlobalPopupCSS();
?>

<!-- Room container -->
<?php echo $roomHelper->renderRoomContainer(
    $roomHelper->renderRoomHeader() . 
    $roomHelper->renderProductIcons()
); ?>

<!-- JavaScript -->
<?php echo $roomHelper->renderJavaScript(); ?>

<!-- Load centralized room functions and cart functionality -->
<script src="js/room-functions.js?v=<?php echo time(); ?>"></script>
<script src="js/cart.js?v=<?php echo time(); ?>"></script>
<script src="js/sales.js?v=<?php echo time(); ?>"></script>
<script src="js/global-popup.js?v=<?php echo time(); ?>"></script>
<script src="js/dynamic_backgrounds.js?v=<?php echo time(); ?>"></script>

<script>
// Universal room functionality
const ROOM_NUMBER = <?php echo json_encode($roomNumber); ?>;
const ROOM_TYPE = <?php echo json_encode($roomHelper->getRoomType()); ?>;

console.log('Room 5 (Sublimation) loaded with <?php echo count($roomHelper->getRoomItems()); ?> items');

// Room positioning system - loads coordinates from database
document.addEventListener('DOMContentLoaded', function() {
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');

    // Room coordinates loaded from database (database-only system)
    let baseAreas = []; // Will be loaded from database

    function updateAreaCoordinates() {
        if (!roomOverlayWrapper) {
            console.error('Room overlay wrapper not found for scaling.');
            return;
        }

        const wrapperWidth = roomOverlayWrapper.offsetWidth;
        const wrapperHeight = roomOverlayWrapper.offsetHeight;

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

        // Apply coordinates to each product icon
        baseAreas.forEach((areaData, index) => {
            const areaElement = document.querySelector(`.product-icon[data-index="${index}"]`);
            if (areaElement) {
                areaElement.style.position = 'absolute';
                areaElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
                areaElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
                areaElement.style.width = (areaData.width * scaleX) + 'px';
                areaElement.style.height = (areaData.height * scaleY) + 'px';
            }
        });
    }

    // Load coordinates from database first, then initialize
    loadRoomCoordinatesFromDatabase();
    
    async function loadRoomCoordinatesFromDatabase() {
        try {
            const response = await fetch(`api/get_room_coordinates.php?room_type=${ROOM_TYPE}`);
            
            // Check if the response is ok (not 500 error)
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: Database not available`);
            }
            
            const data = await response.json();
            
            if (data.success && data.coordinates && data.coordinates.length > 0) {
                baseAreas = data.coordinates;
                console.log(`Loaded ${ROOM_TYPE} coordinates from database:`, data.map_name);
                
                // Initialize coordinates after loading
                updateAreaCoordinates();
            } else {
                console.error(`No active room map found in database for ${ROOM_TYPE}`);
                return; // Don't initialize if no coordinates available
            }
        } catch (error) {
            console.error(`Error loading ${ROOM_TYPE} coordinates from database:`, error);
            return; // Don't initialize if database error
        }
    }

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateAreaCoordinates, 100);
    });
});
</script> 