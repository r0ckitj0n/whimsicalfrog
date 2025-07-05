<?php
/**
 * Room 6 (Window Wraps) - Clean implementation using RoomHelper
 * Eliminates code duplication and uses centralized functionality
 */

// Extract room number from URL
$roomNumber = '6';

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

<!-- Room container -->
<?php echo $roomHelper->renderRoomContainer(
    $roomHelper->renderRoomHeader() . 
    $roomHelper->renderProductIcons()
); ?>

<!-- Include Quantity Modal for Add to Cart functionality -->
<?php include __DIR__ . '/../components/quantity_modal.php'; ?>

<!-- Include item details modal for yesterday's behavior -->
<?php 
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

console.log('Room 6 (Window Wraps) loaded with <?php echo count($roomHelper->getRoomItems()); ?> items');

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
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');

    // Room coordinates loaded from database (database-only system)
    let baseAreas = []; // Will be loaded from database
        // updateAreaCoordinates function moved to room-coordinate-manager.js for centralization
</script> 