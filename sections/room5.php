<?php
// Sublimation room page
$sublimationItems = [];
if (isset($categories['Sublimation'])) {
    $sublimationItems = $categories['Sublimation'];
}

// Include image helpers for room pages
require_once __DIR__ . '/../includes/item_image_helpers.php';
?>

<!-- Include room headers CSS -->
<link href="css/room-headers.css?v=<?php echo time(); ?>" rel="stylesheet">

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
            console.log('Global CSS variables loaded successfully');
        }
    } catch (error) {
        console.warn('Failed to load global CSS variables:', error);
    }
}

// Load global CSS when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadGlobalCSS();
});
</script>
<style>
    .room-container {
        /* Removed background-image, it will be on room-overlay-wrapper */
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        /* min-height: 80vh; */ /* This might be overridden by aspect ratio logic below */
        position: relative;
        border-radius: 15px;
        overflow: hidden;
        /* max-width: 100%; */ /* Ensure it can shrink */
        /* width: 100%; */ /* Take full available width up to its container's limit */
        /* display: flex; */ /* To center the wrapper if it's smaller than container */
        /* justify-content: center; */
        /* align-items: center; */
    }
    
    /* Modal-specific styles */
    <?php if (isset($_GET['modal'])): ?>
    body {
        background: none !important;
        margin: 0;
        padding: 0;
    }
    
    .room-container {
        margin: 0;
        border-radius: 0;
        height: 100vh;
    }
    
    .room-overlay-wrapper {
        border-radius: 0;
        height: 100%;
        padding-top: 0;
    }
    
    .room-overlay-content {
        padding-top: 60px; /* Account for modal header */
    }
    <?php endif; ?>
    
    .room-overlay-wrapper { /* New wrapper for aspect ratio and background */
        width: 100%;
        padding-top: 70%; /* Adjusted for 1280x896 aspect ratio (896/1280 * 100) */
        position: relative; /* For absolute positioning of content inside */
        background-image: url('images/room5.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
        overflow: hidden; /* Add this to prevent internal scrollbars */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room5.png?v=cb2');
    }

    .room-overlay-content { /* New content container */
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex; /* Using flex to layer header, shelf-area, and back button */
        flex-direction: column;
        overflow: hidden; /* Prevent content overflow issues */
    }
    
    .shelf-area {
        position: absolute; /* Position relative to room-overlay-content */
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
    }
    
    .product-icon {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background-color: #fff; /* White background, fully opaque */
        border-radius: 8px; /* Rounded corners */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
        z-index: 10; /* Ensure icons are above background but below popup */
        pointer-events: auto; /* Ensure hover events work */
    }
    
    .product-icon:hover {
        transform: scale(1.1);
        z-index: 100;
    }
    
    .product-icon img {
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    /* Out of stock badge styling */
    .out-of-stock-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #dc2626;
        color: white;
        font-size: 10px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 10px;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .product-icon.out-of-stock {
        opacity: 0.7;
        filter: grayscale(30%);
    }
    
    .product-icon.out-of-stock:hover {
        opacity: 0.9;
        filter: grayscale(10%);
    }
    
    /* Removed Sublimation Room Specific Areas CSS positioning to avoid conflict with JavaScript positioning */
    /* JavaScript in the document.addEventListener('DOMContentLoaded') function below now handles all positioning */
    
    .product-popup {
        position: absolute;
        background: white;
        border-radius: 15px;
        padding: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        border: 3px solid #8B4513;
        width: 280px;
        z-index: 200;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        transform: translateY(10px);
    }
    
    .product-popup.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .popup-image {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 10px;
        margin-bottom: 10px;
    }
    
    .popup-title {
        font-size: 16px;
        font-weight: bold;
        color: #556B2F;
        margin-bottom: 8px;
    }
    
    .popup-category {
        font-size: 12px;
        color: #6B8E23;
        margin-bottom: 1px;
        font-weight: 600;
    }
    
    .popup-description {
        font-size: 12px;
        color: #666;
        margin-bottom: 10px;
        line-height: 1.4;
    }
    
    .popup-price {
        font-size: 18px;
        font-weight: bold;
        color: #6B8E23;
        margin-bottom: 10px;
    }
    
    .popup-add-btn {
        width: 100%;
        background: #87ac3a !important;
        color: white !important;
        border: none !important;
        padding: 8px !important;
        border-radius: 8px !important;
        font-weight: bold !important;
        cursor: pointer !important;
        transition: background 0.3s ease !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }
    
    .popup-add-btn:hover {
        background: #a3cc4a !important;
        color: white !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        transform: translateY(-1px) !important;
    }

    /* Modal Add to Cart button styling - force green color */
    div #confirmAddToCart,
    #confirmAddToCart {
        background-color: #87ac3a !important;
        color: #ffffff !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }

    div #confirmAddToCart:hover,
    #confirmAddToCart:hover {
        background-color: #a3cc4a !important;
        color: #ffffff !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        transform: translateY(-1px) !important;
    }
    
    .room-header {
        text-align: center;
        background: transparent;
        padding: 10px;
        /* border-radius: 15px; */ /* Match the container's rounding if needed */
        margin-bottom: 10px;
        position: relative; /* Needed for z-index to work if other elements overlap */
        z-index: 10; /* Ensure header is above other elements like product icons if they could overlap */
    }
    
    .room-header h1 {
        font-size: 2.5rem;
        font-family: 'Merienda', cursive;
        color: white;
        -webkit-text-stroke: 2px #556B2F;
        text-stroke: 2px #556B2F;
        text-shadow: 
            3px 3px 0px #6B8E23,
            -1px -1px 0 #556B2F,  
             1px -1px 0 #556B2F,
            -1px  1px 0 #556B2F,
             1px  1px 0 #556B2F,
            0 0 10px rgba(255, 255, 255, 0.7);
        margin-bottom: 0.5rem;
    }
    
    .room-header p {
        font-size: 1rem;
        color: white;
        -webkit-text-stroke: 1px #556B2F;
        text-stroke: 1px #556B2F;
        text-shadow: 
            2px 2px 0px #6B8E23,
            0 0 8px rgba(255, 255, 255, 0.6);
    }
    
    .back-button {
        position: absolute;
        top: 20px;
        left: 20px;
        background: rgba(107, 142, 35, 0.9);
        color: white;
        padding: 10px 15px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
        z-index: 1000; /* Increased z-index to ensure it's above everything */
        cursor: pointer; /* Added to show hand cursor on hover */
        pointer-events: auto !important; /* Ensure clicks are registered */
    }
    
    .back-button:hover {
        background: rgba(85, 107, 47, 0.9);
        transform: scale(1.05);
    }
</style>

<section id="sublimationRoomPage" class="p-2">
    <div class="room-container mx-auto max-w-full" data-room-name="Sublimation">
        <div class="room-overlay-wrapper">
<?php if (!isset($_GET['modal'])): ?>
            <a href="/?page=main_room" class="back-button text-[#556B2F]" onclick="console.log('Back button clicked!'); return true;">← Back to Main Room</a>
            <?php endif; ?>
            <div class="room-overlay-content">
                <div class="room-header">
                    <h1 id="roomTitle" class="room-title">Sublimation Studio</h1>
                    <p id="roomDescription" class="room-description">Explore our sublimation printing on various items.</p>
                </div>
                
                <?php if (empty($sublimationItems)): ?>
                    <div class="text-center py-8">
                        <div class="bg-white bg-opacity-90 rounded-lg p-6 inline-block">
                            <p class="text-xl text-gray-600">No sublimation items available at the moment.</p>
                            <p class="text-gray-500 mt-2">Check back soon for new designs!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="shelf-area">
                        <?php foreach ($sublimationItems as $index => $item): ?>
                            <?php 
                            $area_class = 'area-' . ($index + 1);
                            $stock = (int)($item['stock'] ?? $item['stockLevel'] ?? 0);
                            $out_of_stock_class = ($stock <= 0) ? ' out-of-stock' : '';
                            ?>
                            <div class="product-icon <?php echo $area_class . $out_of_stock_class; ?>" 
                                 data-product-id="<?php echo htmlspecialchars($item['id'] ?? ''); ?>"
                                 data-stock="<?php echo $stock; ?>"
                                 onmouseenter="showPopup(this, <?php echo htmlspecialchars(json_encode($item)); ?>)"
                                 onmouseleave="hidePopup()">
                                <?php 
                                // Use new image system with fallback to old system
                                $primaryImage = getPrimaryImageBySku($item['sku']);
                                if ($primaryImage && $primaryImage['file_exists']) {
                                    echo '<img src="' . htmlspecialchars($primaryImage['image_path'] ?? '') . '" alt="' . htmlspecialchars($item['name'] ?? '') . '">';
                                } else {
                                    echo getImageTag($item['image'] ?? 'images/items/placeholder.png', $item['name']);
                                }
                                
                                // Add out of stock badge if stock is 0
                                if ($stock <= 0) {
                                    echo '<div class="out-of-stock-badge">Out of Stock</div>';
                                }
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Product Popup -->
    <div id="productPopup" class="product-popup">
        <img id="popupImage" src="" alt="" class="popup-image">
        <div id="popupTitle" class="popup-title"></div>
        <div id="popupCategory" class="popup-category"></div>
        <div id="popupDescription" class="popup-description"></div>
        <div id="popupPrice" class="popup-price"></div>
        <button id="popupAddBtn" class="popup-add-btn" onclick="openQuantityModal()">
            Add to Cart
        </button>
    </div>
</section>

<!-- Quantity Selection Modal -->
<div id="quantityModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-[#87ac3a]">Select Quantity</h3>
            <button id="closeQuantityModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        
        <div class="flex items-center mb-4">
            <img id="modalProductImage" src="" alt="" class="w-16 h-16 object-contain bg-gray-100 rounded mr-4">
            <div>
                <h4 id="modalProductName" class="font-medium text-gray-800"></h4>
                <p id="modalProductPrice" class="text-[#87ac3a] font-semibold"></p>
            </div>
        </div>
        
        <div class="mb-6">
            <label for="quantityInput" class="block text-sm font-medium text-gray-700 mb-2">Quantity:</label>
            <div class="flex items-center justify-center gap-4">
                <button id="decreaseQty" class="bg-gray-200 hover:bg-gray-300 text-gray-800 w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold">-</button>
                <input type="number" id="quantityInput" value="1" min="1" max="999" class="w-20 text-center border border-gray-300 rounded-md py-2 text-lg font-semibold">
                <button id="increaseQty" class="bg-gray-200 hover:bg-gray-300 text-gray-800 w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold">+</button>
            </div>
        </div>
        
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <div class="flex justify-between items-center text-lg">
                <span class="font-medium text-gray-700">Total:</span>
                <span id="modalTotal" class="font-bold text-[#87ac3a] text-xl">$0.00</span>
            </div>
            <div class="text-sm text-gray-500 mt-1">
                <span id="modalUnitPrice">$0.00</span> × <span id="modalQuantity">1</span>
            </div>
        </div>
        
        <div class="flex gap-3">
            <button id="cancelQuantityModal" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md font-medium">Cancel</button>
            <button id="confirmAddToCart" class="flex-1 bg-[#87ac3a] hover:bg-[#a3cc4a] text-white py-2 px-4 rounded-md font-medium">Add to Cart</button>
        </div>
    </div>
</div>

<script>
let currentProduct = null;
let popupTimeout = null;
let popupOpen = false;

function showPopup(element, product) {
    console.log('showPopup called with:', element, product);
    
    // Prevent rapid re-triggering of same popup (anti-flashing protection)
    if (currentProduct && currentProduct.id === product.id) {
        clearTimeout(popupTimeout);
        return;
    }
    
    clearTimeout(popupTimeout);
    currentProduct = product;
    popupOpen = true;
    
    const popup = document.getElementById('productPopup');
    const rect = element.getBoundingClientRect();
    
    // Update popup content - use primary image from new system
    const productId = product['id'] || product['productId'];
                    fetch(`api/get_item_images.php?sku=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.primaryImage) {
                document.getElementById('popupImage').src = data.primaryImage.image_path;
            } else {
                document.getElementById('popupImage').src = product['image'] || 'images/items/placeholder.png';
            }
        })
        .catch(() => {
            document.getElementById('popupImage').src = product['image'] || 'images/items/placeholder.png';
        });
    document.getElementById('popupTitle').textContent = product['name'];
    document.getElementById('popupCategory').textContent = product['productType'] || product['category'] || '';
    document.getElementById('popupDescription').textContent = product['description'] || 'No description available';
    document.getElementById('popupPrice').textContent = '$' + parseFloat(product['basePrice'] || product['price'] || 0).toFixed(2);
    
    // Position popup
    const roomContainer = element.closest('.room-container');
    const containerRect = roomContainer.getBoundingClientRect();
    
    let left = rect.left - containerRect.left + rect.width + 10;
    let top = rect.top - containerRect.top - 50;
    
    // Adjust if popup would go off screen
    if (left + 280 > containerRect.width) {
        left = rect.left - containerRect.left - 290;
    }
    if (top < 0) {
        top = rect.top - containerRect.top + rect.height + 10;
    }
    
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.classList.add('show');
}

function hidePopup() {
    // Clear any existing timeout
    clearTimeout(popupTimeout);
    
    // Add a small delay before hiding to allow moving mouse to popup
    popupTimeout = setTimeout(() => {
        const popup = document.getElementById('productPopup');
        if (popup && popup.classList.contains('show')) {
            popup.classList.remove('show');
            currentProduct = null;
            popupOpen = false;
        }
    }, 200); // Increased delay for stability
}

// Make functions globally available
window.showPopup = showPopup;
window.hidePopup = hidePopup;

// Quantity modal functionality - wrapped in DOM ready
let quantityModal, modalProductImage, modalProductName, modalProductPrice;
let modalUnitPrice, modalQuantity, modalTotal, quantityInput;
let decreaseQtyBtn, increaseQtyBtn, closeModalBtn, cancelModalBtn, confirmAddBtn;
let modalProduct = null;

// Initialize modal elements when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get modal elements
    quantityModal = document.getElementById('quantityModal');
    modalProductImage = document.getElementById('modalProductImage');
    modalProductName = document.getElementById('modalProductName');
    modalProductPrice = document.getElementById('modalProductPrice');
    modalUnitPrice = document.getElementById('modalUnitPrice');
    modalQuantity = document.getElementById('modalQuantity');
    modalTotal = document.getElementById('modalTotal');
    quantityInput = document.getElementById('quantityInput');
    decreaseQtyBtn = document.getElementById('decreaseQty');
    increaseQtyBtn = document.getElementById('increaseQty');
    closeModalBtn = document.getElementById('closeQuantityModal');
    cancelModalBtn = document.getElementById('cancelQuantityModal');
    confirmAddBtn = document.getElementById('confirmAddToCart');

    // Function to update total calculation
    function updateTotal() {
        const quantity = parseInt(quantityInput.value) || 1;
        const unitPrice = modalProduct ? modalProduct.price : 0;
        const total = quantity * unitPrice;
        
        modalQuantity.textContent = quantity;
        modalTotal.textContent = '$' + total.toFixed(2);
    }

    // Quantity input event listeners
    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            const value = Math.max(1, Math.min(999, parseInt(this.value) || 1));
            this.value = value;
            updateTotal();
        });
    }

    if (decreaseQtyBtn) {
        decreaseQtyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const current = parseInt(quantityInput.value) || 1;
            if (current > 1) {
                quantityInput.value = current - 1;
                updateTotal();
            }
        });
    }

    if (increaseQtyBtn) {
        increaseQtyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const current = parseInt(quantityInput.value) || 1;
            if (current < 999) {
                quantityInput.value = current + 1;
                updateTotal();
            }
        });
    }

    // Modal close functionality
    function closeQuantityModal() {
        if (quantityModal) {
            quantityModal.classList.add('hidden');
        }
        if (quantityInput) {
            quantityInput.value = 1;
        }
        modalProduct = null;
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeQuantityModal);
    }
    
    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', closeQuantityModal);
    }

    // Close modal when clicking outside
    if (quantityModal) {
        quantityModal.addEventListener('click', function(e) {
            if (e.target === quantityModal) {
                closeQuantityModal();
            }
        });
    }

    // Confirm add to cart
    if (confirmAddBtn) {
        confirmAddBtn.addEventListener('click', function() {
            if (modalProduct && typeof window.cart !== 'undefined') {
                const quantity = parseInt(quantityInput.value) || 1;
                
                console.log('Adding to cart:', modalProduct, 'quantity:', quantity);
                try {
                    window.cart.addItem({
                        id: modalProduct.id,
                        name: modalProduct.name,
                        price: modalProduct.price,
                        image: modalProduct.image,
                        quantity: quantity
                    });
                    console.log('Item added to cart successfully');
                    
                    // Show confirmation alert if available
                    const customAlert = document.getElementById('customAlertBox');
                    const customAlertMessage = document.getElementById('customAlertMessage');
                    if (customAlert && customAlertMessage) {
                        const quantityText = quantity > 1 ? ` (${quantity})` : '';
                        customAlertMessage.textContent = `${modalProduct.name}${quantityText} added to your cart!`;
                        customAlert.style.display = 'block';
                        
                        // Auto-hide after 5 seconds (more readable)
                        setTimeout(() => {
                            customAlert.style.display = 'none';
                        }, 5000);
                    }
                    
                    // Close modal
                    closeQuantityModal();
                } catch (error) {
                    console.error('Error adding item to cart:', error);
                    alert('There was an error adding the item to your cart. Please try again.');
                }
            } else {
                console.error('Cart functionality not available');
                alert('Shopping cart is not available. Please refresh the page and try again.');
            }
        });
    }

    // Make updateTotal and closeQuantityModal available globally for openQuantityModal
    window.updateTotal = updateTotal;
    window.closeQuantityModal = closeQuantityModal;
});

// Add to cart directly (skip quantity modal)
async function openQuantityModal() {
    if (!currentProduct) return;
    
    const id = currentProduct['id'];
    let name = currentProduct['name'];
    let price = parseFloat(currentProduct['basePrice'] || currentProduct['price'] || 0);
    let image = currentProduct['image'] || 'images/items/placeholder.png';
    
    // Fetch fresh product data from database to get current image path
    try {
        const response = await fetch('api/get_items.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ item_ids: [id] })
        });
        
        if (response.ok) {
            const items = await response.json();
            if (items && items.length > 0) {
                const freshItem = items[0];
                name = freshItem.name || name;
                price = parseFloat(freshItem.price) || price;
                image = freshItem.image || image;
                console.log('Updated item data from database:', { id, name, price, image });
            }
        }
    } catch (error) {
        console.warn('Could not fetch fresh item data, using cached data:', error);
    }
    
    // Hide popup
    hidePopup();
    
    // Add directly to cart with quantity 1
    if (typeof window.cart !== 'undefined') {
        const quantity = 1;
        
        console.log('Adding to cart:', { id, name, price, image }, 'quantity:', quantity);
        try {
            window.cart.addItem({
                id: id,
                name: name,
                price: price,
                image: image,
                quantity: quantity
            });
            console.log('Item added to cart successfully');
            
            // Show confirmation alert if available
            const customAlert = document.getElementById('customAlertBox');
            const customAlertMessage = document.getElementById('customAlertMessage');
            if (customAlert && customAlertMessage) {
                customAlertMessage.textContent = `${name} added to your cart!`;
                customAlert.style.display = 'block';
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    customAlert.style.display = 'none';
                }, 5000);
            }
        } catch (error) {
            console.error('Error adding item to cart:', error);
            alert('There was an error adding the item to your cart. Please try again.');
        }
    } else {
        console.error('Cart functionality not available');
        alert('Shopping cart is not available. Please refresh the page and try again.');
    }
}

// Keep popup visible when hovering over it
document.getElementById('productPopup').addEventListener('mouseenter', () => {
    clearTimeout(popupTimeout);
});

document.getElementById('productPopup').addEventListener('mouseleave', () => {
    hidePopup();
});

// Simple document click listener for popup closing
document.addEventListener('click', function(e) {
    console.log('Document clicked:', e.target);
    const popup = document.getElementById('productPopup');
    
    // Close popup if it's open and click is outside it
    if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.product-icon')) {
        console.log('Closing popup');
        hidePopup();
    }
});

// Click-outside room functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up click-outside functionality');
    
    // Handle clicks on document body for background detection
    document.body.addEventListener('click', function(e) {
        console.log('Body clicked:', e.target);
        
        // Skip if click is on or inside room container or any UI elements
        const roomContainer = document.querySelector('#sublimationRoomPage .room-container');
        const backButton = document.querySelector('.back-button');
        
        // Debug what was clicked
        console.log('Clicked on back button?', e.target === backButton || backButton.contains(e.target));
        console.log('Clicked on room container?', roomContainer && roomContainer.contains(e.target));
        
        // If back button was clicked, let it handle navigation
        if (e.target === backButton || (backButton && backButton.contains(e.target))) {
            console.log('Back button clicked, allowing default navigation');
            return true; // Let the link handle navigation
        }
        
        // If popup is open, don't handle background clicks
        const popup = document.getElementById('productPopup');
        if (popup && popup.classList.contains('show')) {
            console.log('Popup is open, not handling background click');
            return;
        }
        
        // If click is not on room container or its children, navigate to main room
        if (roomContainer && !roomContainer.contains(e.target)) {
            console.log('Click outside room container, navigating to main room');
            window.location.href = '/?page=main_room';
        }
    });
    
    // Ensure back button works
    const backButton = document.querySelector('.back-button');
    if (backButton) {
        console.log('Back button found, ensuring it works');
        
        // Remove any existing click listeners that might interfere
        const newBackButton = backButton.cloneNode(true);
        backButton.parentNode.replaceChild(newBackButton, backButton);
        
        // Add a clean click listener
        newBackButton.addEventListener('click', function(e) {
            console.log('Back button clicked via event listener');
            // Let the default link behavior happen
        });
    }
});

// Script to dynamically scale product icon areas
document.addEventListener('DOMContentLoaded', function() {
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomOverlayWrapper = document.querySelector('#sublimationRoomPage .room-overlay-wrapper');

    // Room coordinates loaded from database (database-only system)
    let baseAreas = []; // Will be loaded from database

    function updateAreaCoordinates() {
        if (!roomOverlayWrapper) {
            console.error('Sublimation Room overlay wrapper not found for scaling.');
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

        baseAreas.forEach(areaData => {
            const areaElement = roomOverlayWrapper.querySelector(areaData.selector);
            if (areaElement) {
                areaElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
                areaElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
                areaElement.style.width = (areaData.width * scaleX) + 'px';
                areaElement.style.height = (areaData.height * scaleY) + 'px';
            } else {
                // console.warn('Area element not found in Sublimation room:', areaData.selector);
            }
        });
    }

    // Load coordinates from database first, then initialize
    loadRoomCoordinatesFromDatabase();
    
    async function loadRoomCoordinatesFromDatabase() {
        try {
            const response = await fetch('api/get_room_coordinates.php?room_type=room5');
            
            // Check if the response is ok (not 500 error)
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: Database not available`);
            }
            
            const data = await response.json();
            
            if (data.success && data.coordinates && data.coordinates.length > 0) {
                baseAreas = data.coordinates;
                console.log('Loaded Sublimation room coordinates from database:', data.map_name);
            } else {
                console.error('No active room map found in database for Sublimation room');
                return; // Don't initialize if no coordinates available
            }
        } catch (error) {
            console.error('Error loading Sublimation room coordinates from database:', error);
            return; // Don't initialize if database error
        }
        
        // Initialize coordinates after loading
        updateAreaCoordinates();
    }

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateAreaCoordinates, 100);
    });
});
</script>

<!-- Load dynamic background script -->
<script src="js/dynamic_backgrounds.js?v=<?php echo time(); ?>"></script>

<!-- Load dynamic room settings -->
<script>
// Load room settings for dynamic title and description
async function loadRoomSettings() {
    try {
        const response = await fetch('/api/room_settings.php?action=get_room&room_number=5');
        const data = await response.json();
        
        if (data.success && data.room) {
            const room = data.room;
            document.getElementById('roomTitle').textContent = room.room_name;
            document.getElementById('roomDescription').textContent = room.description;
            console.log('Loaded room settings for room 5:', room.room_name);
        } else {
            console.warn('Failed to load room settings, using defaults');
        }
    } catch (error) {
        console.error('Error loading room settings:', error);
    }
}

// Load room settings when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadRoomSettings();
});
</script>
