<?php
// Sublimation room page
$sublimationProducts = [];
if (isset($categories['Sublimation'])) {
    $sublimationProducts = $categories['Sublimation'];
}
?>
<style>
    .room-container {
        /* Removed background-image, it will be on room-overlay-wrapper */
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 80vh; /* This might be overridden by aspect ratio logic below */
        position: relative;
        border-radius: 15px;
        overflow: hidden;
        /* max-width: 100%; */ /* Ensure it can shrink */
        /* width: 100%; */ /* Take full available width up to its container's limit */
        /* display: flex; */ /* To center the wrapper if it's smaller than container */
        /* justify-content: center; */
        /* align-items: center; */
    }
    
    .room-overlay-wrapper { /* New wrapper for aspect ratio and background */
        width: 100%;
        padding-top: 70%; /* Adjusted for 1280x896 aspect ratio (896/1280 * 100) */
        position: relative; /* For absolute positioning of content inside */
        background-image: url('images/room_sublimation.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
        overflow: hidden; /* Add this to prevent internal scrollbars */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room_sublimation.png?v=cb2');
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
        background-color: rgba(0, 100, 255, 0.3); /* Temporary background for visualization */
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
    
    /* Sublimation Room Specific Areas */
    .area-1 { top: 30%; left: 10%; }
    .area-2 { top: 35%; left: 25%; }
    .area-3 { top: 32%; left: 40%; }
    .area-4 { top: 50%; left: 55%; }
    .area-5 { top: 55%; left: 70%; }
    .area-6 { top: 52%; left: 85%; }
    
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
        background: #6B8E23;
        color: white;
        border: none;
        padding: 8px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .popup-add-btn:hover {
        background: #556B2F;
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
        z-index: 10;
    }
    
    .back-button:hover {
        background: rgba(85, 107, 47, 0.9);
        transform: scale(1.05);
    }
</style>

<section id="sublimationRoomPage" class="p-2">
    <div class="room-container mx-auto max-w-full" data-room-name="Sublimation">
        <div class="room-overlay-wrapper">
            <a href="/?page=main_room" class="back-button">← Back to Main Room</a>
            
            <div class="room-overlay-content">
                <div class="room-header">
                    <h1>Sublimation Station</h1>
                    <p>Explore a variety of sublimated items and gifts.</p>
                </div>
                
                <?php if (empty($sublimationProducts)): ?>
                    <div class="text-center py-8">
                        <div class="bg-white bg-opacity-90 rounded-lg p-6 inline-block">
                            <p class="text-xl text-gray-600">No sublimation items available at the moment.</p>
                            <p class="text-gray-500 mt-2">Check back soon for new blanks!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="shelf-area">
                        <?php foreach ($sublimationProducts as $index => $product): ?>
                            <?php $area_class = 'area-' . ($index + 1); ?>
                            <div class="product-icon <?php echo $area_class; ?>" 
                                 data-product-id="<?php echo htmlspecialchars($product[0]); ?>"
                                 onmouseenter="showPopup(this, <?php echo htmlspecialchars(json_encode($product)); ?>)"
                                 onmouseleave="hidePopup()">
                                <?php echo getImageTag($product[8] ?? 'images/placeholder.png', $product[1]); ?>
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
        <div id="popupDescription" class="popup-description"></div>
        <div id="popupPrice" class="popup-price"></div>
        <button id="popupAddBtn" class="popup-add-btn" onclick="addToCartFromPopup()">
            Add to Cart
        </button>
    </div>
</section>

<script>
let currentProduct = null;
let popupTimeout = null;

function showPopup(element, product) {
    clearTimeout(popupTimeout);
    currentProduct = product;
    
    const popup = document.getElementById('productPopup');
    const rect = element.getBoundingClientRect();
    
    // Update popup content
    document.getElementById('popupImage').src = product[8] || 'images/placeholder.png';
    document.getElementById('popupTitle').textContent = product[1];
    document.getElementById('popupDescription').textContent = product[4] || 'No description available';
    document.getElementById('popupPrice').textContent = '$' + parseFloat(product[3] || 0).toFixed(2);
    
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
    popupTimeout = setTimeout(() => {
        const popup = document.getElementById('productPopup');
        popup.classList.remove('show');
        currentProduct = null;
    }, 100);
}

function addToCartFromPopup() {
    if (!currentProduct) return;
    
    const id = currentProduct[0];
    const name = currentProduct[1];
    const price = parseFloat(currentProduct[3] || 0);
    const image = currentProduct[8] || 'images/placeholder.png';
    
    console.log('Adding to cart:', { id, name, price, image });
    try {
        if (typeof window.cart === 'undefined') {
            console.error('Cart not initialized');
            alert('Shopping cart is not available. Please refresh the page and try again.');
            return;
        }
        window.cart.addItem({ id, name, price, image });
        console.log('Item added to cart successfully');
        
        // Hide popup after adding to cart
        hidePopup();
    } catch (error) {
        console.error('Error adding item to cart:', error);
        alert('There was an error adding the item to your cart. Please try again.');
    }
}

// Keep popup visible when hovering over it
document.getElementById('productPopup').addEventListener('mouseenter', () => {
    clearTimeout(popupTimeout);
});

document.getElementById('productPopup').addEventListener('mouseleave', () => {
    hidePopup();
});

// Script to dynamically scale product icon areas
document.addEventListener('DOMContentLoaded', function() {
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomOverlayWrapper = document.querySelector('#sublimationRoomPage .room-overlay-wrapper');

    const baseAreas = [
        { selector: '.area-1', top: 245, left: 261, width: 197, height: 48 }, // Orig: 225, 241
        { selector: '.area-2', top: 309, left: 192, width: 351, height: 51 }, // Orig: 289, 172
        { selector: '.area-3', top: 383, left: 208, width: 341, height: 46 }, // Orig: 363, 188
        { selector: '.area-4', top: 288, left: 816, width: 295, height: 48 }, // Orig: 268, 796
        { selector: '.area-5', top: 372, left: 807, width: 134, height: 74 }, // Orig: 352, 787
        { selector: '.area-6', top: 375, left: 947, width: 161, height: 77 }, // Orig: 355, 927
        { selector: '.area-7', top: 455, left: 205, width: 169, height: 75 }, // Orig: 435, 185
        { selector: '.area-8', top: 447, left: 381, width: 101, height: 72 }  // Orig: 427, 361
    ];

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

    updateAreaCoordinates();
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateAreaCoordinates, 100);
    });
});
</script> 