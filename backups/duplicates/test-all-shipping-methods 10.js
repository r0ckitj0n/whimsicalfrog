// Simple test to verify shipping rates are working
// Run this after the fix to confirm shipping costs are calculated correctly

(async function() {
  console.log('[SHIPPING-TEST] Testing shipping rates...');

  // Test all shipping methods
  const methods = ['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS'];

  for (const method of methods) {
    try {
      const response = await fetch('/api/checkout_pricing.php', {
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
      });

      const data = await response.json();

      if (data.success && data.pricing) {
        console.log(`[SHIPPING-TEST] ${method}:`);
        console.log(`  - Shipping: $${data.pricing.shipping}`);
        console.log(`  - Total: $${data.pricing.total}`);

        if (data.pricing.shipping > 0 && method !== 'Customer Pickup') {
          console.log(`  - ✅ ${method} shipping cost looks correct`);
        } else if (method === 'Customer Pickup' && data.pricing.shipping === 0) {
          console.log(`  - ✅ ${method} correctly shows $0 (free)`);
        } else {
          console.log(`  - ⚠️ ${method} might have an issue`);
        }
      } else {
        console.log(`[SHIPPING-TEST] ❌ ${method} failed:`, data);
      }
    } catch (error) {
      console.log(`[SHIPPING-TEST] ❌ ${method} error:`, error);
    }

    // Small delay between requests
    await new Promise(resolve => setTimeout(resolve, 100));
  }

  console.log('[SHIPPING-TEST] All shipping method tests complete.');
})();
