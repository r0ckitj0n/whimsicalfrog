<?php
// Cart page content
?>
<section id="cartPage" class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-merienda text-[#556B2F] mb-8">Shopping Cart</h1>
        
        <div id="cartItems" class="bg-white rounded-lg shadow-lg p-6">
            <!-- Cart items will be rendered here by cart.js -->
        </div>
    </div>
</section>

<script>
    // Wait for both DOM and cart script to be loaded
    function initializeCart() {
        if (typeof window.cart !== 'undefined') {
            console.log('Cart found, rendering...');
            window.cart.renderCart();
            
            // Listen for cart updates
            window.addEventListener('cartUpdated', function() {
                console.log('Cart updated, re-rendering...');
                window.cart.renderCart();
            });
        } else {
            console.log('Cart not found, retrying in 100ms...');
            setTimeout(initializeCart, 100);
        }
    }

    // Start initialization when DOM is loaded
    document.addEventListener('DOMContentLoaded', initializeCart);
</script> 