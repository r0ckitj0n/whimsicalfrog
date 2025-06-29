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

<!-- Include Quantity Modal for Add to Cart functionality -->
<?php include __DIR__ . '/../components/quantity_modal.php'; ?>

<!-- Include item details modal for yesterday's behavior -->
<?php 
require_once __DIR__ . '/../components/detailed_product_modal.php';
echo renderDetailedProductModal([], []);
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

// Item Details Modal functionality - like yesterday's behavior
async function showItemDetailsModal(sku) {
    try {
        console.log('Opening item details modal for SKU:', sku);
        
        // Fetch item details
        const response = await fetch(`api/get_item_details.php?sku=${sku}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            // Find and show the detailed modal (it's included in the page)
            const modal = document.getElementById('detailedProductModal');
            if (modal) {
                // Update modal content with the item data
                updateDetailedModalContent(data.item, data.images || []);
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            } else {
                console.error('Detailed product modal not found');
                // Fallback to quantity modal
                window.showQuantityModal(sku, data.item.name, data.item.retailPrice, data.item.primaryImageUrl);
            }
        } else {
            console.error('Failed to load item details:', data.message);
        }
    } catch (error) {
        console.error('Error opening item details modal:', error);
    }
}

// Function to update detailed modal content
function updateDetailedModalContent(item, images) {
    // Update basic info
    const titleElement = document.querySelector('#detailedProductModal h2');
    if (titleElement) titleElement.textContent = item.name;
    
    const skuElement = document.querySelector('#detailedProductModal .text-xs');
    if (skuElement) skuElement.textContent = `${item.category || 'Product'} • SKU: ${item.sku}`;
    
    const priceElement = document.getElementById('detailedCurrentPrice');
    if (priceElement) priceElement.textContent = `$${parseFloat(item.retailPrice || 0).toFixed(2)}`;
    
    // Update main image
    const mainImage = document.getElementById('detailedMainImage');
    if (mainImage) {
        const imageUrl = images.length > 0 ? images[0].image_path : `images/items/${item.sku}A.webp`;
        mainImage.src = imageUrl;
        mainImage.alt = item.name;
        
        // Add error handling for image loading
        mainImage.onerror = function() {
            if (!this.src.includes('placeholder')) {
                this.src = 'images/items/placeholder.webp';
            }
        };
    }
    
    // Update stock status
    const stockBadge = document.querySelector('#detailedProductModal .bg-green-100, #detailedProductModal .bg-red-100');
    if (stockBadge && stockBadge.querySelector('svg')) {
        const stockLevel = parseInt(item.stockLevel || 0);
        if (stockLevel > 0) {
            stockBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800';
            stockBadge.innerHTML = `
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                In Stock (${stockLevel} available)
            `;
        } else {
            stockBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800';
            stockBadge.innerHTML = `
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
                Out of Stock
            `;
        }
    }
    
    // Set quantity max value
    const quantityInput = document.getElementById('detailedQuantity');
    if (quantityInput) {
        quantityInput.max = item.stockLevel || 1;
        quantityInput.value = 1;
    }
}

// Modal close functions (matching the detailed modal component)
function closeDetailedModal() {
    const modal = document.getElementById('detailedProductModal');
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