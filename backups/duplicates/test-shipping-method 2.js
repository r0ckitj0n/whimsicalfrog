// Test script to verify shipping method pricing works
// This will be injected into the payment page to test the fix

if (typeof window !== 'undefined' && window.location.pathname.includes('/payment')) {
  console.log('[SHIPPING-TEST] Payment page detected, running shipping method test');

  // Wait for the payment page JS to load
  setTimeout(() => {
    const shipMethodSel = document.getElementById('shippingMethodSelect');
    if (!shipMethodSel) {
      console.error('[SHIPPING-TEST] Shipping method select not found');
      return;
    }

    console.log('[SHIPPING-TEST] Current shipping method:', shipMethodSel.value);

    // Test changing to different shipping methods
    const testMethods = ['USPS', 'FedEx', 'UPS', 'Customer Pickup'];

    testMethods.forEach(method => {
      console.log(`[SHIPPING-TEST] Testing method: ${method}`);

      // Set the value
      shipMethodSel.value = method;

      // Create a synthetic change event
      const changeEvent = new Event('change', { bubbles: true });
      shipMethodSel.dispatchEvent(changeEvent);

      // Wait a bit and check if pricing updates
      setTimeout(() => {
        const shippingEl = document.getElementById('orderShipping');
        if (shippingEl) {
          console.log(`[SHIPPING-TEST] Shipping cost for ${method}:`, shippingEl.textContent);
        } else {
          console.error(`[SHIPPING-TEST] Shipping element not found for ${method}`);
        }
      }, 500);
    });

  }, 2000); // Wait 2 seconds for the payment page JS to initialize
}
