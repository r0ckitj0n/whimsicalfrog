<?php
// Shop page section
?>
<section id="shopPage" class="p-6 bg-white rounded-lg shadow-lg">
    <h2 class="text-4xl font-merienda text-center text-[#556B2F] mb-8">Our Craft Shelves</h2>
    <div id="productCategories" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($categories as $category => $products): ?>
            <div class="category-item bg-white p-4 rounded-lg shadow-md cursor-pointer hover:shadow-lg transition-shadow duration-300"
                 onclick="displayProducts('<?php echo htmlspecialchars($category); ?>', <?php echo htmlspecialchars(json_encode($products)); ?>)">
                <img src="<?php echo htmlspecialchars($products[0][8] ?? 'images/placeholder.png'); ?>" 
                     alt="<?php echo htmlspecialchars($category); ?>" 
                     class="w-full h-48 object-cover rounded-md mb-4">
                <h3 class="text-xl font-merienda text-[#556B2F] mb-2"><?php echo htmlspecialchars($category); ?></h3>
                <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($products[0][4] ?? 'No description available'); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeProductModal()">&times;</span>
        <div id="modalContent" class="p-4">
            <!-- Content will be dynamically inserted here -->
        </div>
    </div>
</div>

<script>
// Remove the cart initialization since it's now handled in cart.js
function openProductModal() {
    console.log('Opening product modal');
    const modal = document.getElementById('productModal');
    if (modal) {
        modal.style.display = 'block';
    } else {
        console.error('Product modal element not found');
    }
}

function closeProductModal() {
    console.log('Closing product modal');
    const modal = document.getElementById('productModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('productModal');
    if (event.target == modal) {
        closeProductModal();
    }
}

function displayProducts(category, products) {
    console.log('Displaying products for category:', category);
    const modal = document.getElementById('productModal');
    const modalContent = document.getElementById('modalContent');
    
    if (!modal || !modalContent) {
        console.error('Modal elements not found');
        return;
    }
    
    let productsHTML = `
        <div class="text-center mb-6">
            <h2 class="text-3xl font-merienda text-[#556B2F]">${category}</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    `;
    
    products.forEach(product => {
        const imageUrl = product[8] || 'images/placeholder.png';
        const price = parseFloat(product[3]) || 0.00;
        const escapedName = product[1].replace(/'/g, "\\'").replace(/"/g, '\\"');
        productsHTML += `
            <div class="product-item bg-white p-4 rounded-lg shadow-md">
                <img src="${imageUrl}" 
                     alt="${escapedName}" 
                     class="w-full h-48 object-cover rounded-md mb-4">
                <h3 class="text-xl font-merienda text-[#556B2F] mb-2">${escapedName}</h3>
                <p class="text-gray-700 text-sm mb-4">${product[4] || 'No description available'}</p>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold text-[#6B8E23]">$${price.toFixed(2)}</span>
                    <button onclick="addToCart('${product[0]}', '${escapedName}', ${price}, '${imageUrl}')" 
                            class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-semibold py-2 px-4 rounded-md transition duration-150 cursor-pointer">
                        Add to Cart
                    </button>
                </div>
            </div>
        `;
    });
    
    productsHTML += '</div>';
    modalContent.innerHTML = productsHTML;
    openProductModal();
}

function addToCart(id, name, price, image) {
    console.log('Adding to cart:', { id, name, price, image });
    try {
        if (typeof window.cart === 'undefined') {
            console.error('Cart not initialized');
            alert('Shopping cart is not available. Please refresh the page and try again.');
            return;
        }
        window.cart.addItem({ id, name, price, image });
        console.log('Item added to cart successfully');
    } catch (error) {
        console.error('Error adding item to cart:', error);
        alert('There was an error adding the item to your cart. Please try again.');
    }
}
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 1200px;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
}

.close-button {
    position: absolute;
    right: 20px;
    top: 10px;
    color: #556B2F;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-button:hover {
    color: #6B8E23;
}

/* Ensure buttons are visible and properly styled */
button {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    cursor: pointer !important;
    pointer-events: auto !important;
    background-color: #6B8E23 !important;
    color: white !important;
    font-weight: 600 !important;
    padding: 0.5rem 1rem !important;
    border-radius: 0.375rem !important;
    transition: background-color 0.15s ease-in-out !important;
}

button:hover {
    background-color: #556B2F !important;
}

/* Ensure product items are properly styled */
.product-item {
    display: flex;
    flex-direction: column;
    background-color: white;
    padding: 1rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.product-item img {
    width: 100%;
    height: 12rem;
    object-fit: cover;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.product-item h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #556B2F;
    margin-bottom: 0.5rem;
}

.product-item p {
    color: #4B5563;
    font-size: 0.875rem;
    margin-bottom: 1rem;
    flex-grow: 1;
}

.product-item .flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}
</style> 