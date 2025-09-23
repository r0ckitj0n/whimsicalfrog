// Manual event listener test for shipping method
// This will manually add the event listener to test if it works

(function() {
  console.log('[MANUAL-TEST] Starting manual shipping method test...');

  // Wait for DOM to be ready
  function waitForElement(selector, callback, maxAttempts = 50) {
    let attempts = 0;
    const check = () => {
      const element = document.getElementById(selector);
      if (element) {
        callback(element);
      } else if (attempts < maxAttempts) {
        attempts++;
        setTimeout(check, 100);
      } else {
        console.error(`[MANUAL-TEST] Element ${selector} not found after ${maxAttempts} attempts`);
      }
    };
    check();
  }

  // Test the updatePricing function manually
  async function testUpdatePricing(shippingMethod) {
    console.log(`[MANUAL-TEST] Testing updatePricing with method: ${shippingMethod}`);

    try {
      // Try to use the existing updatePricing function if it exists
      if (typeof window.updatePricing === 'function') {
        console.log('[MANUAL-TEST] Using existing updatePricing function');
        await window.updatePricing();
      } else {
        console.log('[MANUAL-TEST] updatePricing function not found, testing API directly');

        // Test API call directly
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
            shippingMethod: shippingMethod,
            debug: true
          })
        });

        if (response.ok) {
          const data = await response.json();
          console.log('[MANUAL-TEST] API Response:', data);

          if (data.success && data.pricing) {
            // Manually update the DOM elements
            const shippingEl = document.getElementById('orderShipping');
            const totalEl = document.getElementById('orderTotal');

            if (shippingEl) {
              shippingEl.textContent = `$${data.pricing.shipping.toFixed(2)}`;
              console.log(`[MANUAL-TEST] Updated shipping to: $${data.pricing.shipping.toFixed(2)}`);
            }

            if (totalEl) {
              totalEl.textContent = `$${data.pricing.total.toFixed(2)}`;
              console.log(`[MANUAL-TEST] Updated total to: $${data.pricing.total.toFixed(2)}`);
            }
          }
        } else {
          console.error('[MANUAL-TEST] API call failed:', response.status);
        }
      }
    } catch (error) {
      console.error('[MANUAL-TEST] Error testing updatePricing:', error);
    }
  }

  // Main test function
  function runTest() {
    console.log('[MANUAL-TEST] Running manual shipping method test');

    const shipSelect = document.getElementById('shippingMethodSelect');
    if (!shipSelect) {
      console.error('[MANUAL-TEST] Shipping method select not found');
      return;
    }

    console.log('[MANUAL-TEST] Found shipping method select:', shipSelect.value);

    // Add manual event listener
    shipSelect.addEventListener('change', async (event) => {
      console.log('[MANUAL-TEST] Manual listener triggered! New value:', event.target.value);
      await testUpdatePricing(event.target.value);
    });

    // Test with different methods
    const testMethods = ['USPS', 'FedEx', 'UPS', 'Customer Pickup'];

    testMethods.forEach((method, index) => {
      setTimeout(async () => {
        console.log(`[MANUAL-TEST] Testing method: ${method}`);
        shipSelect.value = method;
        shipSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }, index * 1000);
    });
  }

  // Start the test
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runTest);
  } else {
    runTest();
  }
})();
