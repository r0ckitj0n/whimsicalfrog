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

<?php
// Calculate cart totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$salesTax = round($subtotal * 0.08, 2);
$total = $subtotal + $salesTax;
?>

<div class="cart-summary">
    <div class="flex justify-between text-lg font-semibold">
        <span>Subtotal:</span>
        <span>$<?php echo number_format($subtotal, 2); ?></span>
    </div>
    <div class="flex justify-between text-lg font-semibold">
        <span>Sales Tax (8%):</span>
        <span>$<?php echo number_format($salesTax, 2); ?></span>
    </div>
    <div class="flex justify-between text-lg font-semibold">
        <span>Total:</span>
        <span>$<?php echo number_format($total, 2); ?></span>
    </div>
    <button onclick="cart.checkout()" class="w-full mt-4 bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded">
        Proceed to Checkout
    </button>
</div> 
