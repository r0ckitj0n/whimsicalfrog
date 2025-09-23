// Payment page verification script
// Copy this into your browser console on the /payment page

(function() {
  console.log('[PAYMENT-VERIFY] Starting payment page verification...');

  // Check if we're on the payment page
  const currentPath = window.location.pathname;
  console.log('[PAYMENT-VERIFY] Current path:', currentPath);

  if (!currentPath.includes('/payment')) {
    console.error('[PAYMENT-VERIFY] ❌ Not on payment page! Current path:', currentPath);
    console.log('[PAYMENT-VERIFY] Please navigate to /payment to test shipping methods');
    return;
  }

  console.log('[PAYMENT-VERIFY] ✅ On payment page');

  // Check for key elements
  const elements = {
    paymentPage: document.getElementById('paymentPage'),
    checkoutRoot: document.getElementById('checkoutRoot'),
    shippingMethodSelect: document.getElementById('shippingMethodSelect'),
    orderShipping: document.getElementById('orderShipping'),
    orderTotal: document.getElementById('orderTotal')
  };

  console.log('[PAYMENT-VERIFY] Checking DOM elements:');
  Object.entries(elements).forEach(([name, element]) => {
    if (element) {
      console.log(`- ✅ ${name}: found (${element.tagName})`);
    } else {
      console.error(`- ❌ ${name}: NOT FOUND`);
    }
  });

  // Check if payment page module loaded
  console.log('[PAYMENT-VERIFY] Checking JavaScript objects:');
  console.log('- window.updatePricing:', typeof window.updatePricing);
  console.log('- window.shipMethodSel:', typeof window.shipMethodSel);
  console.log('- window.orderShippingEl:', typeof window.orderShippingEl);

  // Test shipping method change
  if (elements.shippingMethodSelect) {
    const select = elements.shippingMethodSelect;
    console.log('[PAYMENT-VERIFY] Current shipping method:', select.value);

    // Add a test event listener
    const testListener = function(event) {
      console.log('[PAYMENT-VERIFY] 🎉 Event listener triggered! New value:', event.target.value);
      console.log('[PAYMENT-VERIFY] Event type:', event.type);
    };

    select.addEventListener('change', testListener);

    // Test different methods
    const methods = ['USPS', 'FedEx', 'UPS', 'Customer Pickup'];
    let methodIndex = 0;

    const testNextMethod = () => {
      if (methodIndex < methods.length) {
        const method = methods[methodIndex];
        console.log(`[PAYMENT-VERIFY] Testing method: ${method}`);
        select.value = method;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        methodIndex++;

        if (methodIndex < methods.length) {
          setTimeout(testNextMethod, 1000);
        }
      }
    };

    // Start testing after a short delay
    setTimeout(testNextMethod, 1000);

    console.log('[PAYMENT-VERIFY] Test event listener attached. Try changing shipping method manually.');
  }

  console.log('[PAYMENT-VERIFY] Verification complete');
})();
