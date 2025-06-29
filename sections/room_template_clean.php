<?php
/**
 * Clean Room Template - Uses centralized RoomHelper for all room functionality
 * Replaces the massive room_template.php with a clean, maintainable solution
 */

// Extract room number from URL
$roomNumber = isset($_GET['page']) ? str_replace('room', '', $_GET['page']) : '2';

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

<!-- Popup for product details -->
<div id="productPopup" class="popup" style="display: none;">
    <div class="popup-content">
        <img class="popup-image" src="" alt="Product">
        <div class="popup-details">
            <h3 class="popup-title"></h3>
            <p class="popup-price"></p>
            <p class="popup-description"></p>
            <div class="popup-stock"></div>
            <button class="popup-add-to-cart" onclick="addToCartWithModal(window.currentPopupProduct)">Add to Cart</button>
        </div>
    </div>
</div>

<!-- Room container -->
<?php echo $roomHelper->renderRoomContainer(
    $roomHelper->renderRoomHeader() . 
    $roomHelper->renderProductIcons()
); ?>

<!-- JavaScript -->
<?php echo $roomHelper->renderJavaScript(); ?>

<script>
// Additional room-specific initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('Room <?php echo $roomNumber; ?> loaded with <?php echo count($roomHelper->getRoomItems()); ?> items');
    
    // Initialize global popup and modal systems
    if (typeof initializePopupEventListeners === 'function') {
        initializePopupEventListeners();
    }
    if (typeof initializeModalEventListeners === 'function') {
        initializeModalEventListeners();
    }
});
</script> 