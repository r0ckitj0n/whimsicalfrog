// Simple test to verify payment page module loading
// This will be injected to test if the module loads correctly

if (typeof window !== 'undefined' && window.location.pathname.includes('/payment')) {
  console.log('[PAYMENT-TEST] Payment page detected, testing module loading...');

  // Test 1: Check if the DOM elements exist
  const elements = {
    shippingMethodSelect: document.getElementById('shippingMethodSelect'),
    orderShipping: document.getElementById('orderShipping'),
    orderTotal: document.getElementById('orderTotal'),
    paymentPage: document.getElementById('paymentPage'),
    checkoutRoot: document.getElementById('checkoutRoot')
  };

  console.log('[PAYMENT-TEST] DOM Elements check:');
  Object.entries(elements).forEach(([name, element]) => {
    console.log(`- ${name}: ${element ? '✅ Found' : '❌ Not found'}`);
  });

  // Test 2: Check if apiClient is available
  console.log('[PAYMENT-TEST] JavaScript objects:');
  console.log('- apiClient:', typeof window.apiClient);
  console.log('- window.apiClient:', typeof window.apiClient);
  console.log('- WF_Cart:', typeof window.WF_Cart);

  // Test 3: Try to manually trigger shipping method change
  setTimeout(() => {
    const shipSelect = document.getElementById('shippingMethodSelect');
    if (shipSelect) {
      console.log('[PAYMENT-TEST] Current shipping method:', shipSelect.value);

      // Change to different method
      const methods = ['FedEx', 'UPS', 'USPS'];
      let methodIndex = 0;

      const changeMethod = () => {
        if (methodIndex < methods.length) {
          const newMethod = methods[methodIndex];
          console.log(`[PAYMENT-TEST] Changing to: ${newMethod}`);
          shipSelect.value = newMethod;
          shipSelect.dispatchEvent(new Event('change', { bubbles: true }));

          methodIndex++;
          setTimeout(changeMethod, 1000);
        }
      };

      changeMethod();
    } else {
      console.error('[PAYMENT-TEST] Shipping method select not found for testing');
    }
  }, 2000);

  // Test 4: Check for console errors by overriding console.error
  const originalError = console.error;
  console.error = function(...args) {
    originalError.apply(console, args);
    console.log('[PAYMENT-TEST] ❌ JavaScript Error detected:', args);
  };

  console.log('[PAYMENT-TEST] Module loading test complete');
}
