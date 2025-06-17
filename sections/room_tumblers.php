<?php
// Tumblers room page
$tumblerItems = [];
if (isset($categories['Tumblers'])) {
    $tumblerItems = $categories['Tumblers'];
}

// Include image helpers for room pages
require_once __DIR__ . '/../includes/item_image_helpers.php';
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
        background-image: url('images/room_tumblers.webp?v=cb2');
        background-size: contain; /* Preserve aspect ratio, fit within container */
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px; /* If you want rounded corners on the image itself */
        overflow: hidden; /* Add this to prevent internal scrollbars */
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/room_tumblers.png?v=cb2');
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
            <a href="/?page=main_room" class="back-button">← Back to Main Room</a>
            
            <div class="room-header">
                <h1>Tumblers Room</h1>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tumblers room item data
    const tumblerItems = <?php echo json_encode($tumblerItems ?? []); ?>;
    
    console.log('Tumblers room items:', tumblerItems);
    
    // Get room coordinates from API
    fetch('/api/get_room_coordinates.php?room=room_tumblers')
        .then(response => response.json())
        .then(coordinatesData => {
            console.log('Tumblers room coordinates:', coordinatesData);
            
            // Use coordinates from API, or fallback to hardcoded if needed
            let roomCoordinates = [];
            if (coordinatesData.success && coordinatesData.coordinates && coordinatesData.coordinates.length > 0) {
                roomCoordinates = coordinatesData.coordinates;
                console.log('Using database coordinates for tumblers room');
            } else {
                // Fallback coordinates for tumblers room
                roomCoordinates = [
                    { x: 15, y: 25, width: 12, height: 15 },
                    { x: 30, y: 25, width: 12, height: 15 },
                    { x: 45, y: 25, width: 12, height: 15 },
                    { x: 60, y: 25, width: 12, height: 15 },
                    { x: 75, y: 25, width: 12, height: 15 }
                ];
                console.log('Using fallback coordinates for tumblers room');
            }
            
            const shelfArea = document.getElementById('tumblerShelfArea');
            const productPopup = document.getElementById('productPopup');
            
            // Clear existing content
            shelfArea.innerHTML = '';
            
                            // Create product icons for each item and coordinate
            tumblerItems.forEach((item, index) => {
                if (index < roomCoordinates.length) {
                    const coord = roomCoordinates[index];
                    const productIcon = document.createElement('div');
                    productIcon.className = 'product-icon';
                    productIcon.style.left = coord.x + '%';
                    productIcon.style.top = coord.y + '%';
                    productIcon.style.width = coord.width + '%';
                    productIcon.style.height = coord.height + '%';
                    
                    // Get the image URL using the helper function
                    const imageUrl = getImageUrlWithFallback(item.image ?? 'images/items/placeholder.png');
                    
                    productIcon.innerHTML = `<img src="${imageUrl}" alt="${item.name ?? 'Item'}" onerror="this.src='images/items/placeholder.png'; this.onerror=null;">`;
                    
                    // Add click event for popup
                    productIcon.addEventListener('click', function(e) {
                        showProductPopup(item, e.pageX, e.pageY);
                    });
                    
                    shelfArea.appendChild(productIcon);
                }
            });
        })
        .catch(error => {
            console.error('Error fetching tumblers room coordinates:', error);
            // Use fallback coordinates if API fails
            const fallbackCoordinates = [
                { x: 15, y: 25, width: 12, height: 15 },
                { x: 30, y: 25, width: 12, height: 15 },
                { x: 45, y: 25, width: 12, height: 15 },
                { x: 60, y: 25, width: 12, height: 15 },
                { x: 75, y: 25, width: 12, height: 15 }
            ];
            
            const shelfArea = document.getElementById('tumblerShelfArea');
            
            // Clear existing content
            shelfArea.innerHTML = '';
            
            // Create product icons with fallback coordinates
            tumblerItems.forEach((item, index) => {
                if (index < fallbackCoordinates.length) {
                    const coord = fallbackCoordinates[index];
                    const productIcon = document.createElement('div');
                    productIcon.className = 'product-icon';
                    productIcon.style.left = coord.x + '%';
                    productIcon.style.top = coord.y + '%';
                    productIcon.style.width = coord.width + '%';
                    productIcon.style.height = coord.height + '%';
                    
                    // Get the image URL using the helper function
                    const imageUrl = getImageUrlWithFallback(item.image ?? 'images/items/placeholder.png');
                    
                    productIcon.innerHTML = `<img src="${imageUrl}" alt="${item.name ?? 'Item'}" onerror="this.src='images/items/placeholder.png'; this.onerror=null;">`;
                    
                    // Add click event for popup
                    productIcon.addEventListener('click', function(e) {
                        showProductPopup(item, e.pageX, e.pageY);
                    });
                    
                    shelfArea.appendChild(productIcon);
                }
            });
        });
    
    function showProductPopup(item, x, y) {
        const popup = document.getElementById('productPopup');
        const popupImage = document.getElementById('popupImage');
        const popupCategory = document.getElementById('popupCategory');
        const popupTitle = document.getElementById('popupTitle');
        const popupDescription = document.getElementById('popupDescription');
        const popupPrice = document.getElementById('popupPrice');
        const popupAddBtn = document.getElementById('popupAddBtn');
        
        // Get the image URL using the helper function
        const imageUrl = getImageUrlWithFallback(item.image ?? 'images/items/placeholder.png');
        
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
        
        // Position popup
        popup.style.left = Math.min(x, window.innerWidth - 300) + 'px';
        popup.style.top = Math.max(y - 200, 10) + 'px';
        
        // Show popup
        popup.classList.add('show');
        
        // Add to cart functionality
        popupAddBtn.onclick = function() {
            if (typeof addToCart === 'function') {
                const sku = item.sku ?? item.id;
                const name = item.name ?? 'Item';
                const price = parseFloat(item.price ?? item.retailPrice ?? 0);
                addToCart(sku, name, price, imageUrl);
            } else {
                console.error('addToCart function not found');
            }
            popup.classList.remove('show');
        };
        
        // Hide popup when clicking outside
        setTimeout(() => {
            document.addEventListener('click', hidePopup);
        }, 100);
    }
    
    function hidePopup(e) {
        const popup = document.getElementById('productPopup');
        if (!popup.contains(e.target) && !e.target.closest('.product-icon')) {
            popup.classList.remove('show');
            document.removeEventListener('click', hidePopup);
        }
    }
});
</script> 