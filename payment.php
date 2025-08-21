<?php
// payment.php — Protected payment step placeholder
// This page leverages server-side auth to redirect unauthenticated users to /login
// and, after successful login, brings them back here.

if (!defined('INCLUDED_FROM_INDEX')) {
    // Fallback if accessed directly (should normally be included via index.php)
    define('INCLUDED_FROM_INDEX', true);
    require_once __DIR__ . '/api/config.php';
    require_once __DIR__ . '/includes/auth.php';
}

// Require authentication; on failure, set redirect and send to /login
requireAuth('/payment');

?>
<section id="paymentPage" class="container max-w-5xl mx-auto p-6">
  <h1 class="text-2xl font-semibold text-brand-primary mb-2">Checkout</h1>
  <p class="text-brand-secondary mb-6">Review your details and complete your purchase.</p>

  <div id="checkoutRoot" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left: Details -->
    <div class="lg:col-span-2 space-y-6">

      <!-- Shipping Address -->
      <div class="card-standard p-4" id="addressSection">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-medium">Shipping address</h2>
          <div id="addressActions" class="flex items-center gap-2">
            <button id="addAddressToggle" type="button" class="btn-secondary btn-sm">Add address</button>
          </div>
        </div>
        <div id="addressList" class="space-y-2 text-sm">
          <div class="text-brand-secondary">Loading addresses…</div>
        </div>
        <div id="addAddressForm" class="mt-4 hidden">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="form-label">Address name</label>
              <input type="text" id="addr_name" class="form-input" placeholder="Home, Office" />
            </div>
            <div>
              <label class="form-label">Address line 1</label>
              <input type="text" id="addr_line1" class="form-input" />
            </div>
            <div>
              <label class="form-label">Address line 2</label>
              <input type="text" id="addr_line2" class="form-input" />
            </div>
            <div>
              <label class="form-label">City</label>
              <input type="text" id="addr_city" class="form-input" />
            </div>
            <div>
              <label class="form-label">State</label>
              <input type="text" id="addr_state" class="form-input" />
            </div>
            <div>
              <label class="form-label">ZIP code</label>
              <input type="text" id="addr_zip" class="form-input" />
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
              <input id="addr_default" type="checkbox" class="form-checkbox" />
              <label for="addr_default" class="text-sm">Set as default</label>
            </div>
          </div>
          <div class="mt-3 flex gap-2">
            <button id="saveAddressBtn" type="button" class="btn-brand btn-sm">Save address</button>
            <button id="cancelAddressBtn" type="button" class="btn-secondary btn-sm">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Shipping Method -->
      <div class="card-standard p-4">
        <h2 class="text-lg font-medium mb-3">Shipping method</h2>
        <select id="shippingMethodSelect" class="form-select">
          <option value="Customer Pickup" selected>Customer Pickup (free)</option>
          <option value="Local Delivery">Local Delivery</option>
          <option value="USPS">USPS</option>
          <option value="FedEx">FedEx</option>
          <option value="UPS">UPS</option>
        </select>
      </div>

      <!-- Payment Method -->
      <div class="card-standard p-4">
        <h2 class="text-lg font-medium mb-3">Payment method</h2>
        <div id="paymentMethods" class="space-y-2">
          <label class="flex items-center gap-2">
            <input type="radio" name="paymentMethod" value="Square" id="pm-square" class="form-radio" disabled />
            <span>Credit/Debit via Square</span>
            <span id="pmSquareNote" class="text-xs text-brand-secondary hidden">(Unavailable)</span>
          </label>
          <div id="cardContainerWrap" class="mt-3 hidden">
            <div id="card-container" class="border rounded p-3"></div>
            <div id="card-errors" class="text-sm text-red-600 mt-2 hidden"></div>
          </div>
          <label class="flex items-center gap-2">
            <input type="radio" name="paymentMethod" value="Cash" class="form-radio" />
            <span>Cash</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" name="paymentMethod" value="Check" class="form-radio" />
            <span>Check</span>
          </label>
        </div>
      </div>

    </div>

    <!-- Right: Summary -->
    <aside class="lg:col-span-1">
      <div class="card-standard p-4 sticky top-4">
        <h2 class="text-lg font-medium mb-3">Order summary</h2>
        <div id="orderItems" class="divide-y"></div>
        <div class="mt-4 space-y-2">
          <div class="flex items-center justify-between text-sm">
            <span class="text-brand-secondary">Subtotal</span>
            <span id="orderSubtotal">$0.00</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-brand-secondary">Shipping</span>
            <span id="orderShipping">$0.00</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-brand-secondary">Tax</span>
            <span id="orderTax">$0.00</span>
          </div>
          <div class="border-t pt-2 flex items-center justify-between">
            <span class="font-medium">Total</span>
            <span id="orderTotal" class="text-xl font-semibold">$0.00</span>
          </div>
        </div>
        <div id="checkoutError" class="mt-3 text-sm text-red-600 hidden"></div>
        <div class="mt-4 flex gap-3">
          <a href="/cart" class="btn-secondary w-1/2">Back to Cart</a>
          <button id="placeOrderBtn" type="button" class="btn-brand w-1/2" disabled>Place order</button>
        </div>
      </div>
    </aside>
  </div>
</section>
<script>
  // Clear pending checkout flag once we reach the payment step
  try { localStorage.removeItem('pendingCheckout'); } catch(e) {}
</script>
