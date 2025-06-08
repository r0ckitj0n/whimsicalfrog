<?php
// Shop page section
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=shop');
    exit;
}

// Categories are already loaded in index.php and available in $categories
?>

<section id="shopPage" class="py-6">
    <h1 class="text-3xl font-merienda text-center text-[#556B2F] mb-6">Welcome to Our Shop</h1>
    
    <!-- Category Navigation -->
    <div class="flex flex-wrap justify-center mb-8 gap-2">
        <?php foreach (array_keys($categories) as $category): ?>
            <button class="category-btn px-4 py-2 rounded-full bg-[#6B8E23] text-white hover:bg-[#556B2F] transition-colors"
                    data-category="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
            </button>
        <?php endforeach; ?>
        <button class="category-btn px-4 py-2 rounded-full bg-[#6B8E23] text-white hover:bg-[#556B2F] transition-colors active"
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
                $productName = htmlspecialchars($product['productName']);
                $productId = isset($product['productId']) ? htmlspecialchars($product['productId']) : '';
                $price = isset($product['price']) ? htmlspecialchars($product['price']) : '';
                $description = isset($product['description']) ? htmlspecialchars($product['description']) : '';
                $imageUrl = isset($product['imageUrl']) ? htmlspecialchars($product['imageUrl']) : 'images/product-placeholder.webp';
                
                // Format price
                $formattedPrice = '$' . number_format((float)$price, 2);
        ?>
        <div class="product-card" data-category="<?php echo htmlspecialchars($category); ?>">
            <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300">
                <img src="<?php echo $imageUrl; ?>" alt="<?php echo $productName; ?>" class="w-full h-48 object-cover" 
                     onerror="this.onerror=null; this.src='images/product-placeholder.png';">
                <div class="p-4">
                    <h3 class="font-merienda text-lg text-[#556B2F] mb-2"><?php echo $productName; ?></h3>
                    <p class="text-gray-600 mb-2 text-sm line-clamp-2"><?php echo $description; ?></p>
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-[#6B8E23]"><?php echo $formattedPrice; ?></span>
                        <button class="add-to-cart-btn bg-[#6B8E23] hover:bg-[#556B2F] text-white px-3 py-1 rounded-md text-sm transition-colors"
                                data-product-id="<?php echo $productId; ?>"
                                data-product-name="<?php echo $productName; ?>"
                                data-product-price="<?php echo $price; ?>"
                                data-product-image="<?php echo $imageUrl; ?>">
                            Add to Cart
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category filtering
    const categoryButtons = document.querySelectorAll('.category-btn');
    const productCards = document.querySelectorAll('.product-card');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Update active button
            categoryButtons.forEach(btn => btn.classList.remove('active', 'bg-[#556B2F]'));
            this.classList.add('active', 'bg-[#556B2F]');
            
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
    
    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productImage = this.getAttribute('data-product-image');
            
            // Add to cart using the cart.js functionality
            if (typeof window.cart !== 'undefined') {
                window.cart.addItem({
                    id: productId,
                    name: productName,
                    price: productPrice,
                    image: productImage,
                    quantity: 1
                });
                
                // Show confirmation
                const customAlert = document.getElementById('customAlertBox');
                const customAlertMessage = document.getElementById('customAlertMessage');
                customAlertMessage.textContent = `${productName} added to your cart!`;
                customAlert.style.display = 'block';
                
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    customAlert.style.display = 'none';
                }, 3000);
            } else {
                console.error('Cart functionality not available');
            }
        });
    });
});
</script>
