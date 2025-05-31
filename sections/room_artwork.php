<?php
// Artwork room page
$artworkProducts = [];
if (isset($categories['Artwork'])) {
    $artworkProducts = $categories['Artwork'];
}
?>
<style>
    .room-container {
        /* Removed background-image, it will be on room-overlay-wrapper */
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        /* min-height: 80vh; */ /* Temporarily remove/set to 0 to test height contribution */
        min-height: 0;
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
        background-image: url('images/room_artwork.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
        overflow: hidden; /* Add this to prevent internal scrollbars */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room_artwork.png?v=cb2');
    }

    .room-overlay-content { /* New content container */
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        /* background: rgba(255, 255, 255, 0.05); */ /* Optional: for slight overlay on image */
        /* backdrop-filter: blur(0.5px); */
        /* padding: 10px; */ /* Removed padding to allow full area for coordinates */
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
    
    /* Position icons on shelves - adjust based on your room_artwork.png */
    .icon-1 { top: 30%; left: 20%; }
    .icon-2 { top: 35%; left: 40%; }
    .icon-3 { top: 32%; left: 60%; }
    .icon-4 { top: 50%; left: 25%; }
    .icon-5 { top: 55%; left: 45%; }
    .icon-6 { top: 52%; left: 65%; }
    
    /* Artwork Room Specific Areas */
    .area-1 { /* Original: top: 222px; left: 176px; width: 116px; height: 65px; */ }
    .area-2 { /* Original: top: 365px; left: 230px; width: 56px; height: 97px; */ }
    .area-3 { /* Original: top: 218px; left: 352px; width: 66px; height: 102px; */ }
    .area-4 { /* Original: top: 402px; left: 354px; width: 75px; height: 47px; */ }
    .area-5 { /* Original: top: 189px; left: 469px; width: 95px; height: 75px; */ }
    .area-6 { /* Original: top: 345px; left: 477px; width: 52px; height: 95px; */ }
    .area-7 { /* Original: top: 321px; left: 585px; width: 46px; height: 62px; */ }
    .area-8 { /* Original: top: 323px; left: 984px; width: 80px; height: 57px; */ }
    /* Removed old .area-9, .area-10, .area-11 percentage styles */

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
        border-radius: 15px;
        margin-bottom: 10px;
        position: relative;
        z-index: 10;
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
        z-index: 20; /* Ensure it's above product icons but below popup */
    }
    
    .back-button:hover {
        background: rgba(85, 107, 47, 0.9);
        transform: scale(1.05);
    }
</style>

<section id="artworkRoomPage" class="">
    <div class="room-container mx-auto max-w-full">
        <div class="room-overlay-wrapper"> 
            <a href="/?page=main_room" class="back-button">← Back to Main Room</a>
            
            <div class="room-overlay">
                <div class="room-header">
                    <h1 class="text-3xl font-merienda text-[#556B2F] mb-2">🎨 Artwork Studio</h1>
                    <p class="text-sm text-gray-700">Hover over items on the shelves to see details</p>
                </div>
                
                <?php if (empty($artworkProducts)): ?>
                    <div class="text-center py-8">
                        <div class="bg-white bg-opacity-90 rounded-lg p-6 inline-block">
                            <p class="text-xl text-gray-600">No artwork items available at the moment.</p>
                            <p class="text-gray-500 mt-2">Check back soon for new creative pieces!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="shelf-area">
                        <?php foreach ($artworkProducts as $index => $product): ?>
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
    // Ensure we are targeting the correct wrapper for artwork room
    const roomOverlayWrapper = document.querySelector('#artworkRoomPage .room-overlay-wrapper'); 

    const baseAreas = [
        { selector: '.area-1', top: 242, left: 196, width: 116, height: 65 },
        { selector: '.area-2', top: 385, left: 250, width: 56, height: 97 },
        { selector: '.area-3', top: 238, left: 372, width: 66, height: 102 },
        { selector: '.area-4', top: 422, left: 374, width: 75, height: 47 },
        { selector: '.area-5', top: 209, left: 489, width: 95, height: 75 },
        { selector: '.area-6', top: 365, left: 497, width: 52, height: 95 },
        { selector: '.area-7', top: 341, left: 605, width: 46, height: 62 },
        { selector: '.area-8', top: 343, left: 1004, width: 80, height: 57 }
    ];

    function updateAreaCoordinates() {
        if (!roomOverlayWrapper) {
            console.error('Artwork Room overlay wrapper not found for scaling.');
            return;
        }

        const wrapperWidth = roomOverlayWrapper.offsetWidth;
        const wrapperHeight = roomOverlayWrapper.offsetHeight;

        // Calculate the aspect ratio of the wrapper and the image
        const wrapperAspectRatio = wrapperWidth / wrapperHeight;
        const imageAspectRatio = originalImageWidth / originalImageHeight;

        let renderedImageWidth, renderedImageHeight;
        let offsetX = 0;
        let offsetY = 0;

        // Determine the rendered size of the image due to 'contain'
        if (wrapperAspectRatio > imageAspectRatio) {
            // Wrapper is wider than image, so image height is constrained by wrapper height
            renderedImageHeight = wrapperHeight;
            renderedImageWidth = renderedImageHeight * imageAspectRatio;
            offsetX = (wrapperWidth - renderedImageWidth) / 2; // Centered horizontally
        } else {
            // Wrapper is taller (or same aspect ratio) than image, so image width is constrained by wrapper width
            renderedImageWidth = wrapperWidth;
            renderedImageHeight = renderedImageWidth / imageAspectRatio;
            offsetY = (wrapperHeight - renderedImageHeight) / 2; // Centered vertically
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
                 // console.warn('Area element not found in Artwork room:', areaData.selector);
            }
        });
    }

    // Initial call
    updateAreaCoordinates();

    // Update on window resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateAreaCoordinates, 100); // Debounce resize
    });
});
</script> 