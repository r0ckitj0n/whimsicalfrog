<?php
// T-Shirts room page
$tshirtProducts = [];
if (isset($categories['T-Shirts'])) {
    $tshirtProducts = $categories['T-Shirts'];
}
?>
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
        /* border: 2px solid red; */ /* DEBUG BORDER */
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
        background-image: url('images/room_tshirts.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
        /* border: 2px solid blue; */ /* DEBUG BORDER */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room_tshirts.png?v=cb2');
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
        /* border: 2px solid green; */ /* DEBUG BORDER */
    }
    
    .shelf-area { /* This is now the direct container for product-icons */
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        /* border: 2px solid yellow; */ /* DEBUG BORDER */
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
    
    /* T-Shirts Room Specific Areas - Now handled by JavaScript */
    /* .area-1 { top: 309px; left: 94px; width: 118px; height: 132px; } */
    /* .area-2 { top: 319px; left: 271px; width: 83px; height: 125px; } */
    /* .area-3 { top: 322px; left: 358px; width: 81px; height: 127px; } */
    /* .area-4 { top: 324px; left: 445px; width: 84px; height: 125px; } */
    /* .area-5 { top: 327px; left: 535px; width: 74px; height: 123px; } */
    /* .area-6 { top: 435px; left: 847px; width: 98px; height: 155px; } */
    /* .area-7 { top: 438px; left: 993px; width: 110px; height: 170px; } */
    
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

<section id="tshirtsRoomPage">
    <div class="room-container">
        <div class="room-overlay-wrapper"> 
            <a href="/?page=main_room" class="back-button">← Back to Main Room</a>
            
            <div class="room-overlay">
                <div class="room-header">
                    <h1 class="text-3xl font-merienda text-[#556B2F] mb-2">👕 T-Shirt Boutique</h1>
                    <p class="text-sm text-gray-700">Hover over items on the shelves to see details</p>
                </div>
                
                <?php if (empty($tshirtProducts)): ?>
                    <div class="text-center py-8">
                        <div class="bg-white bg-opacity-90 rounded-lg p-6 inline-block">
                            <p class="text-xl text-gray-600">No t-shirt items available at the moment.</p>
                            <p class="text-gray-500 mt-2">Check back soon for new designs!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="shelf-area">
                        <?php foreach ($tshirtProducts as $index => $product): ?>
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
    const roomOverlayWrapper = document.querySelector('#tshirtsRoomPage .room-overlay-wrapper'); 

    const baseAreas = [
        { selector: '.area-1', top: 329, left: 114, width: 118, height: 132 }, // Orig: 309, 94
        { selector: '.area-2', top: 339, left: 291, width: 83, height: 125 }, // Orig: 319, 271
        { selector: '.area-3', top: 342, left: 378, width: 81, height: 127 }, // Orig: 322, 358
        { selector: '.area-4', top: 344, left: 465, width: 84, height: 125 }, // Orig: 324, 445
        { selector: '.area-5', top: 347, left: 555, width: 74, height: 123 }, // Orig: 327, 535
        { selector: '.area-6', top: 455, left: 867, width: 98, height: 155 }, // Orig: 435, 847
        { selector: '.area-7', top: 458, left: 1013, width: 110, height: 170 } // Orig: 438, 993
    ];

    function updateAreaCoordinates() {
        if (!roomOverlayWrapper) {
            console.error('T-Shirts Room overlay wrapper not found for scaling.');
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
                // console.warn('Area element not found in T-Shirts room:', areaData.selector);
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