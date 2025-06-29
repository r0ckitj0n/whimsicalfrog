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

// Global popup system is now handled by js/global-popup.js

// Product details functionality
function showProductDetails(sku) {
    // Try to find the product in the room items
    const product = <?php echo json_encode($roomHelper->getRoomItems()); ?>.find(item => item.sku === sku);
    
    if (!product) {
        console.error('Product not found:', sku);
        return;
    }

    // Use the detailed product modal component
    const modalHTML = generateDetailedProductModal(product);
    
    // Insert modal into page
    const existingModal = document.getElementById('detailedProductModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Show the modal
    showDetailedModal();
}

// Generate detailed product modal HTML
function generateDetailedProductModal(item) {
    function hasData(value) {
        return value && value.trim() !== '' && value.toLowerCase() !== 'n/a' && value !== 'null';
    }

    const stockLevel = parseInt(item.stockLevel) || 0;
    const isOutOfStock = stockLevel <= 0;
    const stockClass = isOutOfStock ? 'out-of-stock' : (stockLevel <= 5 ? 'low-stock' : 'in-stock');
    const stockText = isOutOfStock ? 'Out of Stock' : (stockLevel <= 5 ? `Only ${stockLevel} left` : `${stockLevel} in stock`);

    return `
        <div id="detailedProductModal" class="modal-overlay">
            <div class="modal-content detailed-product-modal">
                <div class="modal-header">
                    <h2 class="modal-title">${item.name || item.productName || 'Product Details'}</h2>
                    <button id="closeDetailedModal" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="product-details-container">
                        <div class="product-image-section">
                            <img src="${item.primaryImageUrl || `images/items/${item.sku}A.png`}" 
                                 alt="${item.name || item.productName || 'Product'}" 
                                 class="product-detail-image">
                        </div>
                        <div class="product-info-section">
                            <div class="product-basic-info">
                                <p class="product-sku">SKU: ${item.sku}</p>
                                <p class="product-price">$${parseFloat(item.retailPrice || 0).toFixed(2)}</p>
                                <div class="stock-info ${stockClass}">
                                    <span class="stock-text">${stockText}</span>
                                </div>
                            </div>
                            ${hasData(item.description) ? `
                                <div class="product-description">
                                    <h4>Description</h4>
                                    <p>${item.description}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="addToCartFromDetails" class="btn btn-primary" ${isOutOfStock ? 'disabled' : ''}>
                        ${isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Show detailed modal
function showDetailedModal() {
    const modal = document.getElementById('detailedProductModal');
    if (modal) {
        modal.style.display = 'flex';
        
        // Close button handler
        const closeBtn = document.getElementById('closeDetailedModal');
        if (closeBtn) {
            closeBtn.addEventListener('click', hideDetailedModal);
        }
        
        // Add to cart handler
        const addToCartBtn = document.getElementById('addToCartFromDetails');
        if (addToCartBtn && !addToCartBtn.disabled) {
            addToCartBtn.addEventListener('click', function() {
                // Implementation depends on your cart system
                console.log('Add to cart clicked from detailed modal');
                hideDetailedModal();
            });
        }
        
        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideDetailedModal();
            }
        });
    }
}

// Hide detailed modal
function hideDetailedModal() {
    const modal = document.getElementById('detailedProductModal');
    if (modal) {
        modal.style.display = 'none';
        modal.remove();
    }
}

console.log('Room 3 (Tumblers) loaded with <?php echo count($roomHelper->getRoomItems()); ?> items');
</script> 