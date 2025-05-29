<?php
// T-Shirts room page
$tshirtProducts = [];
if (isset($categories['T-Shirts'])) {
    $tshirtProducts = $categories['T-Shirts'];
}
?>
<style>
    .room-container {
        background-image: url('images/webp/room_tshirts.webp');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 80vh;
        position: relative;
        border-radius: 15px;
        overflow: hidden;
    }
    
    .room-overlay {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(0.5px);
        min-height: 80vh;
        padding: 10px;
        position: relative;
    }
    
    .shelf-area {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
    }
    
    .product-icon {
        position: absolute;
        width: 40px;
        height: 40px;
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        background: white;
        padding: 2px;
    }
    
    .product-icon:hover {
        transform: scale(1.2);
        border-color: #6B8E23;
        box-shadow: 0 4px 15px rgba(107, 142, 35, 0.5);
        z-index: 100;
    }
    
    .product-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    
    /* Position icons on shelves - adjust based on your room_tshirts.png */
    .icon-1 { top: 25%; left: 15%; }
    .icon-2 { top: 30%; left: 35%; }
    .icon-3 { top: 28%; left: 55%; }
    .icon-4 { top: 45%; left: 20%; }
    .icon-5 { top: 50%; left: 40%; }
    .icon-6 { top: 48%; left: 60%; }
    
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

<section id="tshirtsRoomPage" class="p-2">
    <div class="room-container mx-auto max-w-full">
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
                        <div class="product-icon icon-<?php echo $index + 1; ?>" 
                             data-product-id="<?php echo htmlspecialchars($product[0]); ?>"
                             onmouseenter="showPopup(this, <?php echo htmlspecialchars(json_encode($product)); ?>)"
                             onmouseleave="hidePopup()">
                            <img src="<?php echo htmlspecialchars($product[8] ?? 'images/placeholder.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($product[1]); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
</script> 