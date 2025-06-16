<?php
// Cart page content
?>
<section id="cartPage" class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-merienda mb-8" style="color:#87ac3a !important;">Shopping Cart</h1>
        <div class="modal-content relative" style="padding-top: 60px;">
            <!-- Back to Main Room Button inside modal -->
            <a href="/?page=main_room" class="back-button text-[#556B2F]" style="position:absolute;top:16px;left:16px;background:rgba(107,142,35,0.9);color:white;padding:8px 14px;border-radius:25px;text-decoration:none;font-weight:bold;transition:all 0.3s ease;z-index:1000;cursor:pointer;pointer-events:auto;">
                ← Back to Main Room
            </a>
            <div id="cartItems" class="bg-white rounded-lg shadow-lg p-6">
                <!-- Cart items will be rendered here by cart.js -->
            </div>
        </div>
    </div>
</section>

<script>
    // Wait for both DOM and cart script to be loaded
    async function initializeCart() {
        if (typeof window.cart !== 'undefined') {
            console.log('Cart found, rendering...');
            await window.cart.renderCart();
            
            // Listen for cart updates
            window.addEventListener('cartUpdated', async function() {
                console.log('Cart updated, re-rendering...');
                await window.cart.renderCart();
            });
        } else {
            console.log('Cart not found, retrying in 100ms...');
            setTimeout(initializeCart, 100);
        }
    }

    // Start initialization when DOM is loaded
    document.addEventListener('DOMContentLoaded', initializeCart);
</script>
