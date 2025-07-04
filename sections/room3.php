<?php
/**
 * Room 3 (Tumblers) - Clean implementation using RoomHelper
 * Eliminates code duplication and uses centralized functionality
 */

// Extract room number from URL
$roomNumber = '3';

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
<script src="js/room-event-manager.js?v=1751412139"></script>
<script src="js/room-modal-manager.js?v=1751412139"></script>
<script src="js/room-css-manager.js?v=1751412139"></script>
<script>
// loadGlobalCSS function moved to css-initializer.js for centralization

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

<!-- Include Quantity Modal and Item Details Modal -->
<?php 
include __DIR__ . '/../components/quantity_modal.php';

// Include item details modal for yesterday's behavior
require_once __DIR__ . '/../components/detailed_item_modal.php';
echo renderDetailedItemModal([], []);
?>

<!-- JavaScript -->
<?php echo $roomHelper->renderJavaScript(); ?>

<!-- Load centralized room functions and cart functionality -->
<script src="js/room-functions.js?v=<?php echo time(); ?>"></script>
<script src="js/global-popup.js?v=<?php echo time(); ?>"></script>
<script src="js/dynamic_backgrounds.js?v=<?php echo time(); ?>"></script>

<script>
// Universal room functionality
const ROOM_NUMBER = <?php echo json_encode($roomNumber); ?>;
const ROOM_TYPE = <?php echo json_encode($roomHelper->getRoomType()); ?>;

// Global popup system is now handled by js/global-popup.js
// Room functions are now handled by global popup system

console.log('Room 3 (Tumblers) loaded with <?php echo count($roomHelper->getRoomItems()); ?> items');

// Item Details Modal functionality - use global modal system
window.showItemDetailsModal = function(sku) {
    // Use the existing global modal system
    if (typeof window.showGlobalItemModal === 'function') {
        window.showGlobalItemModal(sku);
    } else {
        console.error('Global item modal system not available');
    }
};

// Modal close functions (matching the detailed modal component)
function closeDetailedModal() {
    const modal = document.getElementById('detailedItemModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

function closeDetailedModalOnOverlay(event) {
    if (event.target === event.currentTarget) {
        closeDetailedModal();
    }
}

// Room positioning system - loads coordinates from database
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Room3 DOMContentLoaded - initializing coordinate system...');
    
    // Set global variables for coordinate system
    window.originalImageWidth = 1280;
    window.originalImageHeight = 896;
    window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    window.ROOM_TYPE = 'room3';
    
    if (window.roomOverlayWrapper) {
        console.log('✅ Room overlay wrapper found, loading coordinates...');
        
        // Direct coordinate loading and application
        async function loadAndApplyCoordinates() {
            try {
                console.log('🔄 Fetching coordinates from database...');
                const response = await fetch('api/get_room_coordinates.php?room_type=room3');
                const data = await response.json();
                
                if (data.success && data.coordinates && data.coordinates.length > 0) {
                    console.log('✅ Coordinates loaded:', data.coordinates);
                    
                    // Apply coordinates immediately
                    applyCoordinatesToItems(data.coordinates);
                    
                    // Also apply on window resize
                    window.addEventListener('resize', function() {
                        applyCoordinatesToItems(data.coordinates);
                    });
                } else {
                    console.error('❌ No coordinates found in database');
                }
            } catch (error) {
                console.error('❌ Error loading coordinates:', error);
            }
        }
        
        // Function to apply coordinates to items
        function applyCoordinatesToItems(coordinates) {
            console.log('🎯 Applying coordinates to items...');
            
            const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
            const wrapperHeight = window.roomOverlayWrapper.offsetHeight;
            
            console.log(`📐 Wrapper dimensions: ${wrapperWidth}x${wrapperHeight}`);
            
            const wrapperAspectRatio = wrapperWidth / wrapperHeight;
            const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;
            
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
            
            const scaleX = renderedImageWidth / window.originalImageWidth;
            const scaleY = renderedImageHeight / window.originalImageHeight;
            
            console.log(`📐 Scale factors: scaleX=${scaleX}, scaleY=${scaleY}`);
            
            // Apply coordinates to each item
            coordinates.forEach((coord, index) => {
                // Extract area number from selector (e.g. ".area-1" -> 1)
                const areaNumber = parseInt(coord.selector.replace('.area-', ''));
                // Map to item-icon index (area-1 -> item-icon-0, area-2 -> item-icon-1, etc.)
                const itemIndex = areaNumber - 1;
                const itemElement = document.getElementById('item-icon-' + itemIndex);
                
                if (itemElement) {
                    const newTop = (coord.top * scaleY + offsetY);
                    const newLeft = (coord.left * scaleX + offsetX);
                    const newWidth = (coord.width * scaleX);
                    const newHeight = (coord.height * scaleY);
                    
                    itemElement.style.position = 'absolute';
                    itemElement.style.cursor = 'pointer';
                    itemElement.style.top = newTop + 'px';
                    itemElement.style.left = newLeft + 'px';
                    itemElement.style.width = newWidth + 'px';
                    itemElement.style.height = newHeight + 'px';
                    
                    console.log(`✅ Positioned item-icon-${itemIndex}: top=${newTop}px, left=${newLeft}px, width=${newWidth}px, height=${newHeight}px`);
                } else {
                    console.warn(`⚠️ Item element item-icon-${itemIndex} not found`);
                }
            });
        }
        
        // Load coordinates after a short delay to ensure DOM is ready
        setTimeout(loadAndApplyCoordinates, 100);
        
        // Also try after page is fully loaded
        window.addEventListener('load', function() {
            console.log('🔄 Page fully loaded, applying coordinates again...');
            setTimeout(loadAndApplyCoordinates, 500);
        });
    } else {
        console.error('❌ Room overlay wrapper not found');
    }
});
</script> 