<?php
/**
 * Room 2 (T-Shirts) - Clean implementation using RoomHelper
 * Eliminates code duplication and uses centralized functionality
 */

// Extract room number from URL
$roomNumber = '2';

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
<script src="js/room-coordinate-manager.js?v=<?php echo time(); ?>"></script>
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

<!-- Include Quantity Modal and Item Details Modal -->
<?php 
include __DIR__ . '/../components/quantity_modal.php';

// Include item details modal for yesterday's behavior
require_once __DIR__ . '/../components/detailed_item_modal.php';
echo renderDetailedItemModal([], []);

// Include global popup component
require_once __DIR__ . '/../components/global_popup.php';
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

console.log('Room 2 (T-Shirts) loaded with <?php echo count($roomHelper->getRoomItems()); ?> items');

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

// Room2 specific initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Room2 page loaded');
    
    // All coordinate system handling is now done by room-coordinate-manager.js
    // No need for room-specific coordinate code
});
</script> 