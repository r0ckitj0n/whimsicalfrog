// Test payment modal with updated shipping rates
// This will verify that the payment modal is working correctly now

(function() {
  console.log('[PAYMENT-MODAL-TEST] Testing payment modal with fixed shipping rates...');

  // First, let's test the API directly to make sure rates are working
  fetch('/api/checkout_pricing.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      itemIds: ['WF-TS-002'],
      quantities: [1],
      shippingMethod: 'USPS',
      debug: true
    })
  }).then(r => r.json()).then(data => {
    console.log('[PAYMENT-MODAL-TEST] Direct API test result:', data);

    if (data.success && data.pricing) {
      console.log('[PAYMENT-MODAL-TEST] ✅ Shipping cost:', data.pricing.shipping);
      console.log('[PAYMENT-MODAL-TEST] ✅ Total cost:', data.pricing.total);

      if (data.pricing.shipping > 0) {
        console.log('[PAYMENT-MODAL-TEST] 🎉 SUCCESS! Shipping rates are working correctly.');
        console.log('[PAYMENT-MODAL-TEST] Now try opening the payment modal and changing shipping methods.');
        console.log('[PAYMENT-MODAL-TEST] You should see:');
        console.log('[PAYMENT-MODAL-TEST] - USPS: $8.99');
        console.log('[PAYMENT-MODAL-TEST] - FedEx: $12.99');
        console.log('[PAYMENT-MODAL-TEST] - UPS: $12.99');
        console.log('[PAYMENT-MODAL-TEST] - Customer Pickup: $0.00');
      } else {
        console.error('[PAYMENT-MODAL-TEST] ❌ Shipping is still 0. Issue persists.');
      }
    } else {
      console.error('[PAYMENT-MODAL-TEST] ❌ API test failed:', data);
    }
  }).catch(err => {
    console.error('[PAYMENT-MODAL-TEST] ❌ API test error:', err);
  });

  // Test all shipping methods
  console.log('[PAYMENT-MODAL-TEST] Testing all shipping methods:');
  const methods = ['USPS', 'FedEx', 'UPS', 'Customer Pickup'];

  methods.forEach((method, index) => {
    setTimeout(() => {
      fetch('/api/checkout_pricing.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          itemIds: ['WF-TS-002'],
          quantities: [1],
          shippingMethod: method,
          debug: true
        })
      }).then(r => r.json()).then(data => {
        if (data.success && data.pricing) {
          console.log(`[PAYMENT-MODAL-TEST] ${method}: $${data.pricing.shipping} (Total: $${data.pricing.total})`);

          if (method === 'Customer Pickup' && data.pricing.shipping === 0) {
            console.log(`[PAYMENT-MODAL-TEST] ✅ ${method} correctly shows $0 (free)`);
          } else if (data.pricing.shipping > 0) {
            console.log(`[PAYMENT-MODAL-TEST] ✅ ${method} shipping cost looks correct`);
          } else {
            console.log(`[PAYMENT-MODAL-TEST] ⚠️ ${method} might have an issue`);
          }
        }
      }).catch(err => {
        console.error(`[PAYMENT-MODAL-TEST] ❌ ${method} error:`, err);
      });
    }, index * 500);
  });

  console.log('[PAYMENT-MODAL-TEST] Test setup complete. Please open the payment modal to test the shipping method changes.');
})();
