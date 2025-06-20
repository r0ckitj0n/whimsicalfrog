<?php
// Tumblers room page
$tumblerItems = [];
if (isset($categories['Tumblers'])) {
    $tumblerItems = $categories['Tumblers'];
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
        background-image: url('images/room3.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
        overflow: hidden; /* Add this to prevent internal scrollbars */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room3.png?v=cb2');
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
    
    /* Removed Tumblers Room Specific Areas CSS positioning to avoid conflict with JavaScript positioning */
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
        font-weight: semibold;
        margin-bottom: 1px;
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
        color: #556B2F;
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    
    .back-button {
        position: absolute;
        top: 16px;
        left: 16px;
        background: rgba(107, 142, 35, 0.9);
        color: white;
        padding: 8px 14px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
        z-index: 1000;
        cursor: pointer;
        pointer-events: auto;
    }
    
    .back-button:hover {
        background: rgba(107, 142, 35, 1);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    @media (max-width: 768px) {
        .room-header h1 {
            font-size: 1.8rem;
        }
        
        .product-popup {
            width: 250px;
            padding: 12px;
        }
        
        .popup-image {
            height: 120px;
        }
        
        .back-button {
            padding: 6px 10px;
            font-size: 0.9rem;
        }
    }
</style>

<div class="room-container">
    <div class="room-overlay-wrapper">
        <div class="room-overlay-content">
<?php if (!isset($_GET['modal'])): ?>
            <a href="/?page=main_room" class="back-button">← Back to Main Room</a>
            <?php endif; ?>
            
            <div class="room-header">
                <h1 id="roomTitle" class="room-title">Tumblers Room</h1>
                <p id="roomDescription" class="room-description">Discover our collection of custom tumblers and drinkware.</p>
            </div>
            
            <div class="shelf-area" id="tumblerShelfArea">
                <!-- Products will be dynamically positioned here -->
            </div>
        </div>
    </div>
</div>

<!-- Product popup template -->
<div id="productPopup" class="product-popup">
    <img id="popupImage" class="popup-image" src="" alt="">
    <div id="popupCategory" class="popup-category"></div>
    <div id="popupTitle" class="popup-title"></div>
    <div id="popupDescription" class="popup-description"></div>
    <div id="popupPrice" class="popup-price"></div>
    <button id="popupAddBtn" class="popup-add-btn">Add to Cart</button>
</div>

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
document.addEventListener('DOMContentLoaded', function() {
    // Tumblers room item data
    const tumblerItems = <?php echo json_encode($tumblerItems ?? []); ?>;
    
    console.log('Tumblers room items:', tumblerItems);
    
    // Get room coordinates from API
                fetch('/api/get_room_coordinates.php?room_type=room3')
        .then(response => response.json())
        .then(coordinatesData => {
            console.log('Tumblers room coordinates:', coordinatesData);
            
            // Use coordinates from database (database-only system) - keep as pixels for proper scaling
            let roomCoordinates = [];
            if (coordinatesData.success && coordinatesData.coordinates && coordinatesData.coordinates.length > 0) {
                // Keep database coordinates as pixels for advanced scaling system
                roomCoordinates = coordinatesData.coordinates;
                console.log('Using database coordinates for tumblers room (pixels)', roomCoordinates);
            } else {
                console.error('No active room map found in database for Tumblers room');
                return; // Don't initialize if no coordinates available
            }
            
            const shelfArea = document.getElementById('tumblerShelfArea');
            const productPopup = document.getElementById('productPopup');
            
            // Clear existing content
            shelfArea.innerHTML = '';
            
                            // Advanced pixel-based scaling system (same as other rooms)
            const originalImageWidth = 1280;
            const originalImageHeight = 896;
            const roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
            
            function calculateScaledPosition(coord) {
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
                
                return {
                    left: (coord.left * scaleX + offsetX) + 'px',
                    top: (coord.top * scaleY + offsetY) + 'px',
                    width: (coord.width * scaleX) + 'px',
                    height: (coord.height * scaleY) + 'px'
                };
            }
            
            // Create product icons for each item and coordinate
            console.log('Creating product icons for', tumblerItems.length, 'items with', roomCoordinates.length, 'coordinates');
            tumblerItems.forEach((item, index) => {
                console.log('Processing item', index, ':', item.name);
                if (index < roomCoordinates.length) {
                    const coord = roomCoordinates[index];
                    console.log('Using coordinate:', coord);
                    const productIcon = document.createElement('div');
                    
                    // Check stock level and add appropriate classes
                    const stock = parseInt(item.stock || item.stockLevel || 0);
                    const outOfStockClass = (stock <= 0) ? ' out-of-stock' : '';
                    productIcon.className = 'product-icon' + outOfStockClass;
                    
                    // Use advanced pixel scaling
                    const scaledPos = calculateScaledPosition(coord);
                    productIcon.style.left = scaledPos.left;
                    productIcon.style.top = scaledPos.top;
                    productIcon.style.width = scaledPos.width;
                    productIcon.style.height = scaledPos.height;
                    productIcon.setAttribute('data-stock', stock);
                    
                    // Get the image URL - use SKU-based system
                    const imageUrl = `images/items/${item.sku}A.png`;
                    
                    let iconHTML = `<img src="${imageUrl}" alt="${item.name ?? 'Item'}" onerror="this.src='images/items/placeholder.png'; this.onerror=null;">`;
                    
                    // Add out of stock badge if stock is 0
                    if (stock <= 0) {
                        iconHTML += '<div class="out-of-stock-badge">Out of Stock</div>';
                    }
                    
                    productIcon.innerHTML = iconHTML;
                    
                    // Add hover events for popup (like other rooms)
                    productIcon.addEventListener('mouseenter', function(e) {
                        clearTimeout(popupHideTimeout); // Clear any pending hide
                        showProductPopup(item, this);
                    });
                    
                    productIcon.addEventListener('mouseleave', function(e) {
                        hidePopup();
                    });
                    
                    // Also keep click event for mobile/touch devices
                    productIcon.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        showProductPopup(item, this);
                    });
                    
                    shelfArea.appendChild(productIcon);
                    console.log('Added product icon to shelf area:', item.name);
                } else {
                    console.log('No coordinate available for item', index, ':', item.name);
                }
            });
            console.log('Finished creating product icons. Total icons added:', shelfArea.children.length);
            
            // Add resize handler for responsive scaling
            let resizeTimeout;
            function handleResize() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    console.log('Window resized, updating tumbler positions');
                    // Re-position all existing icons
                    const icons = shelfArea.querySelectorAll('.product-icon');
                    icons.forEach((icon, index) => {
                        if (index < roomCoordinates.length) {
                            const coord = roomCoordinates[index];
                            const scaledPos = calculateScaledPosition(coord);
                            icon.style.left = scaledPos.left;
                            icon.style.top = scaledPos.top;
                            icon.style.width = scaledPos.width;
                            icon.style.height = scaledPos.height;
                        }
                    });
                }, 100);
            }
            
            window.addEventListener('resize', handleResize);
        })
        .catch(error => {
            console.error('Error fetching tumblers room coordinates:', error);
            // Don't initialize if database error - database-only system
            return;
        });
    
    function showProductPopup(item, element) {
        // Prevent rapid re-triggering of same popup
        if (currentPopupItem && currentPopupItem.sku === item.sku) {
            clearTimeout(popupHideTimeout);
            return;
        }
        
        clearTimeout(popupHideTimeout);
        currentPopupItem = item;
        
        const popup = document.getElementById('productPopup');
        const popupImage = document.getElementById('popupImage');
        const popupCategory = document.getElementById('popupCategory');
        const popupTitle = document.getElementById('popupTitle');
        const popupDescription = document.getElementById('popupDescription');
        const popupPrice = document.getElementById('popupPrice');
        const popupAddBtn = document.getElementById('popupAddBtn');
        
        // Get the image URL - use SKU-based system
        const imageUrl = `images/items/${item.sku}A.png`;
        
        // Populate popup content
        popupImage.src = imageUrl;
        popupImage.onerror = function() {
            this.src = 'images/items/placeholder.png';
            this.onerror = null;
        };
        popupCategory.textContent = item.category ?? 'Tumblers';
        popupTitle.textContent = item.name ?? 'Item Name';
        popupDescription.textContent = item.description ?? 'No description available';
        popupPrice.textContent = '$' + (parseFloat(item.price ?? item.retailPrice ?? 0)).toFixed(2);
        
        // Better positioning relative to the element (like other rooms)
        const rect = element.getBoundingClientRect();
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
        
        // Show popup
        popup.classList.add('show');
        
        // Add to cart functionality - trigger quantity modal like other rooms
        popupAddBtn.onclick = function() {
            popup.classList.remove('show');
            openQuantityModal(item);
        };
        
        // Hide popup when clicking outside
        setTimeout(() => {
            document.addEventListener('click', hidePopup);
        }, 100);
    }
    
    let popupHideTimeout = null;
    let currentPopupItem = null;
    
    function hidePopup(e) {
        // Clear any existing timeout
        clearTimeout(popupHideTimeout);
        
        // Add a small delay before hiding to allow moving mouse to popup
        popupHideTimeout = setTimeout(() => {
            const popup = document.getElementById('productPopup');
            if (popup && popup.classList.contains('show')) {
                popup.classList.remove('show');
                currentPopupItem = null;
            }
            if (e && !popup.contains(e.target) && !e.target.closest('.product-icon')) {
                document.removeEventListener('click', hidePopup);
            }
        }, 200); // Increased delay for stability
    }
    
    // Keep popup visible when hovering over it (like other rooms)
    document.getElementById('productPopup').addEventListener('mouseenter', () => {
        // Clear any hide timeout when hovering over popup
        clearTimeout(popupHideTimeout);
    });

    document.getElementById('productPopup').addEventListener('mouseleave', () => {
        hidePopup();
    });
    
    // Quantity modal functionality
    let modalProduct = null;
    let quantityModal, modalProductImage, modalProductName, modalProductPrice;
    let modalUnitPrice, modalQuantity, modalTotal, quantityInput;
    let decreaseQtyBtn, increaseQtyBtn, closeModalBtn, cancelModalBtn, confirmAddBtn;

    // Initialize modal elements
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

    // Function to open quantity modal
    window.openQuantityModal = function(product) {
        modalProduct = product;
        
        // Set product details
        modalProductName.textContent = product.name || 'Product';
        modalProductPrice.textContent = '$' + parseFloat(product.price ?? product.retailPrice ?? 0).toFixed(2);
        modalUnitPrice.textContent = '$' + parseFloat(product.price ?? product.retailPrice ?? 0).toFixed(2);
        
        // Set product image
        const imageUrl = `images/items/${product.sku}A.png`;
        modalProductImage.src = imageUrl;
        modalProductImage.onerror = function() {
            this.src = 'images/items/placeholder.png';
            this.onerror = null;
        };
        
        // Reset quantity
        quantityInput.value = 1;
        updateTotal();
        
        // Show modal
        quantityModal.classList.remove('hidden');
    };

    // Function to update total calculation
    function updateTotal() {
        const quantity = parseInt(quantityInput.value) || 1;
        const unitPrice = modalProduct ? parseFloat(modalProduct.price ?? modalProduct.retailPrice ?? 0) : 0;
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
        confirmAddBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (modalProduct && typeof addToCart === 'function') {
                const quantity = parseInt(quantityInput.value) || 1;
                const sku = modalProduct.sku ?? modalProduct.id;
                const name = modalProduct.name ?? 'Item';
                const price = parseFloat(modalProduct.price ?? modalProduct.retailPrice ?? 0);
                const imageUrl = `images/items/${modalProduct.sku}A.png`;
                
                // Add multiple items to cart
                for (let i = 0; i < quantity; i++) {
                    addToCart(sku, name, price, imageUrl);
                }
                
                closeQuantityModal();
            } else {
                console.error('addToCart function not found or no product selected');
                alert('Unable to add item to cart. Please refresh the page and try again.');
            }
        });
    }
});

// Load room settings for dynamic title and description
async function loadRoomSettings() {
    try {
        const response = await fetch('/api/room_settings.php?action=get_room&room_number=3');
        const data = await response.json();
        
        if (data.success && data.room) {
            const room = data.room;
            document.getElementById('roomTitle').textContent = room.room_name;
            document.getElementById('roomDescription').textContent = room.description;
            console.log('Loaded room settings for room 3:', room.room_name);
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

<!-- Load dynamic background script -->
<script src="js/dynamic_backgrounds.js?v=<?php echo time(); ?>"></script> 