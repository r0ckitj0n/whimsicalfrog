<?php
// Tumblers room page
$tumblerProducts = [];
if (isset($categories['Tumblers'])) {
    $tumblerProducts = $categories['Tumblers'];
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
        padding-top: 70%; /* 1280x896 Aspect Ratio (896/1280 * 100) */
        position: relative; /* For absolute positioning of content inside */
        background-image: url('images/room_tumblers.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room_tumblers.png?v=cb2');
    }

    .room-overlay {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(0.5px);
        /* min-height: 80vh; Removed, as parent now controls height via aspect ratio */
        padding: 10px;
        position: absolute; /* Changed from relative */
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 15px; /* Match wrapper if needed */
    }
    
    .shelf-area { /* This is now the direct container for product-icons */
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        /* The children (.product-icon) will be positioned relative to this */
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
    
    /* Tumblers Room Specific Areas - Now handled by JavaScript */
    /* .area-1 { top: 163px; left: 420px; width: 64px; height: 134px; } */
    /* .area-2 { top: 162px; left: 510px; width: 61px; height: 126px; } */
    /* .area-3 { top: 159px; left: 595px; width: 66px; height: 126px; } */
    /* .area-4 { top: 344px; left: 233px; width: 67px; height: 142px; } */
    /* .area-5 { top: 333px; left: 319px; width: 71px; height: 144px; } */
    /* .area-6 { top: 326px; left: 399px; width: 66px; height: 144px; } */
    /* .area-7 { top: 333px; left: 472px; width: 66px; height: 134px; } */
    /* .area-8 { top: 324px; left: 570px; width: 63px; height: 128px; } */
    /* .area-9 { top: 320px; left: 643px; width: 59px; height: 126px; } */
    /* .area-10 { top: 537px; left: 224px; width: 76px; height: 152px; } */
    /* .area-11 { top: 524px; left: 315px; width: 67px; height: 140px; } */
    /* .area-12 { top: 513px; left: 390px; width: 69px; height: 133px; } */
    /* .area-13 { top: 501px; left: 466px; width: 62px; height: 130px; } */
    /* .area-14 { top: 488px; left: 538px; width: 57px; height: 128px; } */
    /* .area-15 { top: 477px; left: 603px; width: 60px; height: 125px; } */
    
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
        z-index: 10;
    }
    
    .back-button:hover {
        background: rgba(85, 107, 47, 0.9);
        transform: scale(1.05);
    }
</style>

<section id="tumblersRoomPage" class="p-2">
    <div class="room-container mx-auto max-w-full">
        <div class="room-overlay-wrapper"> 
            <a href="/?page=main_room" class="back-button">← Back to Main Room</a>
            
            <div class="room-overlay">
                <div class="room-header">
                    <h1 class="text-3xl font-merienda text-[#556B2F] mb-2">🥤 Tumbler Collection</h1>
                    <p class="text-sm text-gray-700">Hover over items on the shelves to see details</p>
                </div>
                
                <?php if (empty($tumblerProducts)): ?>
                    <div class="text-center py-8">
                        <div class="bg-white bg-opacity-90 rounded-lg p-6 inline-block">
                            <p class="text-xl text-gray-600">No tumbler items available at the moment.</p>
                            <p class="text-gray-500 mt-2">Check back soon for new drinkware!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="shelf-area">
                        <?php foreach ($tumblerProducts as $index => $product): ?>
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
    const roomOverlayWrapper = document.querySelector('#tumblersRoomPage .room-overlay-wrapper');

    const baseAreas = [
        { selector: '.area-1', top: 183, left: 440, width: 64, height: 134 },  // Orig: 163, 420
        { selector: '.area-2', top: 182, left: 530, width: 61, height: 126 },  // Orig: 162, 510
        { selector: '.area-3', top: 179, left: 615, width: 66, height: 126 },  // Orig: 159, 595
        { selector: '.area-4', top: 364, left: 253, width: 67, height: 142 },  // Orig: 344, 233
        { selector: '.area-5', top: 353, left: 339, width: 71, height: 144 },  // Orig: 333, 319
        { selector: '.area-6', top: 346, left: 419, width: 66, height: 144 },  // Orig: 326, 399
        { selector: '.area-7', top: 353, left: 492, width: 66, height: 134 },  // Orig: 333, 472
        { selector: '.area-8', top: 344, left: 590, width: 63, height: 128 },  // Orig: 324, 570
        { selector: '.area-9', top: 340, left: 663, width: 59, height: 126 },  // Orig: 320, 643
        { selector: '.area-10', top: 557, left: 244, width: 76, height: 152 }, // Orig: 537, 224
        { selector: '.area-11', top: 544, left: 335, width: 67, height: 140 }, // Orig: 524, 315
        { selector: '.area-12', top: 533, left: 410, width: 69, height: 133 }, // Orig: 513, 390
        { selector: '.area-13', top: 521, left: 486, width: 62, height: 130 }, // Orig: 501, 466
        { selector: '.area-14', top: 508, left: 558, width: 57, height: 128 }, // Orig: 488, 538
        { selector: '.area-15', top: 497, left: 623, width: 60, height: 125 }  // Orig: 477, 603
    ];

    function updateAreaCoordinates() {
        if (!roomOverlayWrapper) {
            console.error('Tumblers Room overlay wrapper not found for scaling.');
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
                // console.warn('Area element not found in Tumblers room:', areaData.selector);
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