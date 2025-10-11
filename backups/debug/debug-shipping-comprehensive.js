// Comprehensive shipping method debugging script
// This will help identify why shipping prices aren't updating

(function() {
  console.log('[SHIPPING-DEBUG] Starting comprehensive shipping method debugging...');

  // Function to check if an element exists and log its details
  function debugElement(selector, name) {
    const el = document.querySelector(selector) || document.getElementById(selector);
    if (el) {
      console.log(`[SHIPPING-DEBUG] ✅ ${name} found:`, {
        tagName: el.tagName,
        id: el.id,
        className: el.className,
        value: el.value,
        type: el.type,
        events: getEventListeners ? 'Has listeners' : 'Cannot check listeners'
      });
      return el;
    } else {
      console.error(`[SHIPPING-DEBUG] ❌ ${name} NOT FOUND with selector: ${selector}`);
      return null;
    }
  }

  // Function to test API call directly
  async function testApiCall(shippingMethod, itemIds = ['WF-TS-002'], quantities = [1]) {
    console.log(`[SHIPPING-DEBUG] Testing API call with method: ${shippingMethod}`);

    try {
      const payload = {
        itemIds: itemIds,
        quantities: quantities,
        shippingMethod: shippingMethod,
        debug: true
      };

      console.log('[SHIPPING-DEBUG] API payload:', payload);

      // Try to use the apiClient if available
      if (typeof apiClient !== 'undefined' && apiClient.post) {
        console.log('[SHIPPING-DEBUG] Using apiClient.post');
        const response = await apiClient.post('/api/checkout_pricing.php', payload);
        console.log('[SHIPPING-DEBUG] API response:', response);
        return response;
      } else {
        console.warn('[SHIPPING-DEBUG] apiClient not available, using ApiClient.request');
        const data = await window.ApiClient.request('/api/checkout_pricing.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        console.log('[SHIPPING-DEBUG] ApiClient response:', data);
        return data;
      }
    } catch (error) {
      console.error('[SHIPPING-DEBUG] API call error:', error);
      return null;
    }
  }

  // Main debugging function
  async function debugShippingMethod() {
    console.log('[SHIPPING-DEBUG] === SHIPPING METHOD DEBUGGING START ===');

    // 1. Check for key elements
    const shipMethodSel = debugElement('#shippingMethodSelect', 'Shipping Method Select');
    const orderShippingEl = debugElement('#orderShipping', 'Order Shipping Display');
    const orderTotalEl = debugElement('#orderTotal', 'Order Total Display');

    // 2. Check for JavaScript objects
    console.log('[SHIPPING-DEBUG] Checking JavaScript objects:');
    console.log('- apiClient:', typeof apiClient);
    console.log('- window.apiClient:', typeof window.apiClient);
    console.log('- WF_Cart:', typeof window.WF_Cart);

    // 3. Test event listeners
    if (shipMethodSel) {
      console.log('[SHIPPING-DEBUG] Current shipping method value:', shipMethodSel.value);

      // Check if change event listener is attached
      const oldChange = shipMethodSel.onchange;
      console.log('[SHIPPING-DEBUG] Current onchange handler:', oldChange ? 'Present' : 'None');

      // Add our own test listener
      shipMethodSel.addEventListener('change', (event) => {
        console.log('[SHIPPING-DEBUG] 🎉 Shipping method changed via listener! New value:', event.target.value);
      });

      // Trigger test changes
      const testMethods = ['USPS', 'FedEx', 'UPS', 'Customer Pickup'];
      for (const method of testMethods) {
        console.log(`[SHIPPING-DEBUG] Testing change to: ${method}`);
        shipMethodSel.value = method;
        shipMethodSel.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise(resolve => setTimeout(resolve, 100));
      }
    }

    // 4. Test API calls
    console.log('[SHIPPING-DEBUG] === TESTING API CALLS ===');
    for (const method of ['USPS', 'FedEx', 'UPS', 'Customer Pickup']) {
      const response = await testApiCall(method);
      if (response && response.success && response.pricing) {
        console.log(`[SHIPPING-DEBUG] ✅ ${method} API result:`, {
          shipping: response.pricing.shipping,
          total: response.pricing.total
        });
      } else {
        console.error(`[SHIPPING-DEBUG] ❌ ${method} API failed`);
      }
      await new Promise(resolve => setTimeout(resolve, 100));
    }

    // 5. Check if pricing elements get updated
    if (orderShippingEl) {
      console.log('[SHIPPING-DEBUG] Current shipping display text:', orderShippingEl.textContent);
    }

    console.log('[SHIPPING-DEBUG] === SHIPPING METHOD DEBUGGING END ===');
  }

  // Run debugging
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', debugShippingMethod);
  } else {
    debugShippingMethod();
  }
})();
