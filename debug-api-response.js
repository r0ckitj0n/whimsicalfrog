// Intercept payment modal API calls to see exact response values
// This will show us what the API is returning and why the DOM isn't updating

(function() {
  console.log('[API-DEBUG] Starting payment modal API debugging...');

  // Intercept the API client to see the exact response
  const originalApiClient = window.apiClient;
  if (originalApiClient && originalApiClient.post) {
    const originalPost = originalApiClient.post;

    apiClient.post = async function(url, data, options) {
      if (url.includes('/api/checkout_pricing.php')) {
        console.log('[API-DEBUG] 📡 API call to checkout_pricing.php with data:', data);

        try {
          const response = await originalPost.call(this, url, data, options);

          if (response && response.success && response.pricing) {
            console.log('[API-DEBUG] 📥 Full API response:', response);

            // Check if shipping is 0
            const shipping = response.pricing.shipping;
            console.log('[API-DEBUG] 🚚 Shipping cost from API:', shipping);

            if (shipping === 0 || shipping === '0' || shipping === 0.0) {
              console.error('[API-DEBUG] ❌ SHIPPING IS 0 - This is the problem!');
              console.log('[API-DEBUG] This means your shipping rates in the database are set to 0');
              console.log('[API-DEBUG] Check your business settings for shipping rates');
            } else {
              console.log('[API-DEBUG] ✅ Shipping cost looks correct:', shipping);
            }

            // Check debug info
            if (response.debug) {
              console.log('[API-DEBUG] 🔍 Debug info:', response.debug);
            }
          } else {
            console.error('[API-DEBUG] ❌ API response failed:', response);
          }

          return response;
        } catch (error) {
          console.error('[API-DEBUG] ❌ API call failed:', error);
          throw error;
        }
      } else {
        return originalPost.call(this, url, data, options);
      }
    };

    console.log('[API-DEBUG] ✅ apiClient.post intercepted for debugging');
  }

  // Also check what happens after the updatePricing function runs
  const originalUpdatePricing = window.updatePricing;
  if (typeof originalUpdatePricing === 'function') {
    window.updatePricing = async function() {
      console.log('[API-DEBUG] 🔧 updatePricing function called');

      try {
        const result = await originalUpdatePricing.apply(this, arguments);

        // Check DOM elements after update
        setTimeout(() => {
          const elements = {
            orderSubtotal: document.querySelector('#pm-orderSubtotal'),
            orderShipping: document.querySelector('#pm-orderShipping'),
            orderTax: document.querySelector('#pm-orderTax'),
            orderTotal: document.querySelector('#pm-orderTotal')
          };

          console.log('[API-DEBUG] 📋 DOM elements after updatePricing:');
          Object.entries(elements).forEach(([name, element]) => {
            if (element) {
              const value = element.textContent;
              console.log(`- ${name}: "${value}"`);

              if (name === 'orderShipping' && (value === '$0.00' || value === '0.00')) {
                console.error(`[API-DEBUG] ❌ Shipping still shows ${value} - DOM update failed!`);
              }
            } else {
              console.error(`[API-DEBUG] ❌ ${name} element not found`);
            }
          });
        }, 100);

        return result;
      } catch (error) {
        console.error('[API-DEBUG] updatePricing error:', error);
        throw error;
      }
    };

    console.log('[API-DEBUG] ✅ updatePricing function overridden');
  }

  console.log('[API-DEBUG] Debugging setup complete. Try changing shipping method now.');
})();
