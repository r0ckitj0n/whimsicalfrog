<?php
// Shop page section
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=shop');
    exit;
}

// Include the image carousel component and helpers
require_once __DIR__ . '/../components/image_carousel.php';
require_once __DIR__ . '/../includes/item_image_helpers.php';

// Categories are already loaded in index.php and available in $categories
?>

<style>
    /* Use the same green from the header bar */
    :root {
        --wf-green: #87ac3a;
        --wf-green-light: #a3cc4a;
    }

    #shopPage h1 {
        color: var(--wf-green) !important; /* override global reset */
    }

    /* Category buttons styling */
    .category-btn {
        background: var(--wf-green);
        color: #ffffff !important; /* override reset */
        border: none;
        transition: background 0.2s ease;
    }

    .category-btn:hover,
    .category-btn.active {
        background: var(--wf-green-light);
        color: #ffffff !important;
    }

    /* Add to Cart button styling - force green color */
    .add-to-cart-btn {
        background-color: #87ac3a !important;
        color: #ffffff !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }

    .add-to-cart-btn:hover:not(:disabled) {
        background-color: #a3cc4a !important;
        color: #ffffff !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        transform: translateY(-1px) !important;
    }

    .add-to-cart-btn:disabled {
        background-color: #9ca3af !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
    }

    /* Modal Add to Cart button styling - highest specificity */
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
</style>

<section id="shopPage" class="py-6">
    <h1 class="text-3xl font-merienda text-center mb-6">Welcome to Our Shop</h1>
    
    <!-- Category Navigation -->
    <div class="flex flex-wrap justify-center mb-8 gap-2">
        <?php foreach (array_keys($categories) as $category): ?>
            <button class="category-btn px-4 py-2 rounded-full"
                    data-category="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
            </button>
        <?php endforeach; ?>
        <button class="category-btn px-4 py-2 rounded-full active"
                data-category="all">
            All Products
        </button>
    </div>
    
    <!-- Products Grid -->
    <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php 
        // Display all products from all categories
        foreach ($categories as $category => $products): 
            foreach ($products as $product):
                // Skip products without required fields
                if (!isset($product['productName']) || !isset($product['price'])) {
                    continue;
                }
                
                // Get product details
                $productName = htmlspecialchars($product['productName'] ?? '');
                $productId = isset($product['productId']) ? htmlspecialchars($product['productId'] ?? '') : '';
                $sku = isset($product['sku']) ? htmlspecialchars($product['sku'] ?? '') : $productId;
                $price = isset($product['price']) ? htmlspecialchars($product['price'] ?? '') : '';
                $description = isset($product['description']) ? htmlspecialchars($product['description'] ?? '') : '';
                $stock = isset($product['stock']) ? (int)$product['stock'] : 0;
                
                // Format price
                $formattedPrice = '$' . number_format((float)$price, 2);
                
                // Get primary image using database-driven system
                $primaryImageData = getPrimaryImageBySku($sku);
                $imageUrl = ($primaryImageData && $primaryImageData['file_exists']) ? htmlspecialchars($primaryImageData['image_path'] ?? '') : 'images/items/placeholder.png';
        ?>
        <div class="product-card" data-category="<?php echo htmlspecialchars($category); ?>">
            <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 flex flex-col h-full">
                <?php 
                // Display product images using database-driven system
                if ($primaryImageData && $primaryImageData['file_exists']) {
                    echo '<div class="product-image-container" style="height: 192px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; overflow: hidden;">';
                    echo '<img src="' . htmlspecialchars($primaryImageData['image_path'] ?? '') . '" alt="' . htmlspecialchars($primaryImageData['alt_text'] ?: $productName) . '" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src=\'images/items/placeholder.png\';">';
                    echo '</div>';
                } else {
                    // Show placeholder if no images
                    echo '<div class="product-image-placeholder" style="height: 192px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px;">';
                    echo '<img src="images/items/placeholder.png" alt="No image available" style="max-width: 100%; max-height: 100%; object-fit: contain;">';
                    echo '</div>';
                }
                ?>
                <div class="p-4 flex flex-col flex-grow">
                    <h3 class="font-merienda text-lg text-[#87ac3a] mb-1 line-clamp-2"><?php echo $productName; ?></h3>
                    <div class="text-xs text-gray-500 mb-1"><?php echo htmlspecialchars($category); ?></div>
                    <p class="text-gray-600 mb-2 text-sm line-clamp-2 flex-grow-0">
                        <?php echo $description; ?>
                    </p>
                    <div class="mt-2 text-sm <?php echo $stock>0 ? 'text-gray-600' : 'text-red-600'; ?>">In stock: <?php echo $stock; ?></div>
                    <div class="flex justify-between items-center mt-auto">
                        <span class="font-bold text-[#87ac3a]"><?php echo $formattedPrice; ?></span>
                        <button class="add-to-cart-btn <?php echo $stock>0 ? 'bg-[#87ac3a] hover:bg-[#a3cc4a]' : 'bg-gray-400 cursor-not-allowed'; ?> text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-md hover:shadow-lg"
                                <?php if($stock==0) echo 'disabled'; ?>
                                data-product-id="<?php echo $productId; ?>"
                                data-product-name="<?php echo $productName; ?>"
                                data-product-price="<?php echo $price; ?>"
                                data-product-image="<?php echo $imageUrl; ?>">
                            <?php echo $stock>0 ? 'Add to Cart' : 'Out of Stock'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            endforeach;
        endforeach; 
        ?>
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
document.addEventListener('DOMContentLoaded', function() {
    // Category filtering
    const categoryButtons = document.querySelectorAll('.category-btn');
    const productCards = document.querySelectorAll('.product-card');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Update active button (CSS handles color changes)
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter products
            productCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // Quantity modal functionality
    const quantityModal = document.getElementById('quantityModal');
    const modalProductImage = document.getElementById('modalProductImage');
    const modalProductName = document.getElementById('modalProductName');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const modalUnitPrice = document.getElementById('modalUnitPrice');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    const quantityInput = document.getElementById('quantityInput');
    const decreaseQtyBtn = document.getElementById('decreaseQty');
    const increaseQtyBtn = document.getElementById('increaseQty');
    const closeModalBtn = document.getElementById('closeQuantityModal');
    const cancelModalBtn = document.getElementById('cancelQuantityModal');
    const confirmAddBtn = document.getElementById('confirmAddToCart');
    
    let currentProduct = null;
    
    // Function to update total calculation
    function updateTotal() {
        const quantity = parseInt(quantityInput.value) || 1;
        const unitPrice = currentProduct ? currentProduct.price : 0;
        const total = quantity * unitPrice;
        
        modalQuantity.textContent = quantity;
        modalTotal.textContent = '$' + total.toFixed(2);
    }
    
    // Quantity input event listeners
    quantityInput.addEventListener('input', function() {
        const value = Math.max(1, Math.min(999, parseInt(this.value) || 1));
        this.value = value;
        updateTotal();
    });
    
    decreaseQtyBtn.addEventListener('click', function() {
        const current = parseInt(quantityInput.value) || 1;
        if (current > 1) {
            quantityInput.value = current - 1;
            updateTotal();
        }
    });
    
    increaseQtyBtn.addEventListener('click', function() {
        const current = parseInt(quantityInput.value) || 1;
        if (current < 999) {
            quantityInput.value = current + 1;
            updateTotal();
        }
    });
    
    // Modal close functionality
    function closeModal() {
        quantityModal.classList.add('hidden');
        quantityInput.value = 1;
        currentProduct = null;
    }
    
    closeModalBtn.addEventListener('click', closeModal);
    cancelModalBtn.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    quantityModal.addEventListener('click', function(e) {
        if (e.target === quantityModal) {
            closeModal();
        }
    });
    
    // Add to cart functionality - directly add to cart (skip modal)
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productImage = this.getAttribute('data-product-image');
            
            // Add directly to cart with quantity 1
            if (typeof window.cart !== 'undefined') {
                const quantity = 1;
                
                window.cart.addItem({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    image: productImage,
                    quantity: quantity
                });
                
                // Show confirmation
                const customAlert = document.getElementById('customAlertBox');
                const customAlertMessage = document.getElementById('customAlertMessage');
                customAlertMessage.textContent = `${productName} added to your cart!`;
                customAlert.style.display = 'block';
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    customAlert.style.display = 'none';
                }, 5000);
            } else {
                console.error('Cart functionality not available');
            }
        });
    });
    
    // Confirm add to cart
    confirmAddBtn.addEventListener('click', function() {
        if (currentProduct && typeof window.cart !== 'undefined') {
            const quantity = parseInt(quantityInput.value) || 1;
            
            window.cart.addItem({
                id: currentProduct.id,
                name: currentProduct.name,
                price: currentProduct.price,
                image: currentProduct.image,
                quantity: quantity
            });
            
            // Show confirmation
            const customAlert = document.getElementById('customAlertBox');
            const customAlertMessage = document.getElementById('customAlertMessage');
            const quantityText = quantity > 1 ? ` (${quantity})` : '';
            customAlertMessage.textContent = `${currentProduct.name}${quantityText} added to your cart!`;
            customAlert.style.display = 'block';
            
                            // Auto-hide after 5 seconds (more readable)
                setTimeout(() => {
                    customAlert.style.display = 'none';
                }, 5000);
            
            // Close modal
            closeModal();
        } else {
            console.error('Cart functionality not available');
        }
    });
});
</script>
