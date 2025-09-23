// Fix payment modal DOM update conflict
// This will ensure pricing updates work correctly

(function() {
  console.log('[PAYMENT-MODAL-FIX] Starting DOM update conflict fix...');

  // The issue is that both updatePricing() and renderOrderSummary() update the same DOM elements
  // This creates a race condition. Let me fix this by ensuring they use the same pricing data.

  // Override the renderOrderSummary function to use the current pricing
  const originalRenderOrderSummary = window.renderOrderSummary;

  if (typeof originalRenderOrderSummary === 'function') {
    // Find the renderOrderSummary function in the payment modal
    // It updates DOM elements but might use stale pricing data

    // Override it to use current pricing data
    window.renderOrderSummary = function() {
      console.log('[PAYMENT-MODAL-FIX] renderOrderSummary called');

      // Get current pricing from the payment modal's pricing variable
      // This should be updated by updatePricing()
      const currentPricing = window.pricing || { subtotal: 0, shipping: 0, tax: 0, total: 0 };

      console.log('[PAYMENT-MODAL-FIX] Current pricing in renderOrderSummary:', currentPricing);

      // Update DOM elements with current pricing
      const elements = {
        orderSubtotal: document.querySelector('#pm-orderSubtotal'),
        orderShipping: document.querySelector('#pm-orderShipping'),
        orderTax: document.querySelector('#pm-orderTax'),
        orderTotal: document.querySelector('#pm-orderTotal')
      };

      if (elements.orderSubtotal) {
        elements.orderSubtotal.textContent = `$${Number(currentPricing.subtotal || 0).toFixed(2)}`;
        console.log('[PAYMENT-MODAL-FIX] Updated subtotal to:', elements.orderSubtotal.textContent);
      }

      if (elements.orderShipping) {
        elements.orderShipping.textContent = `$${Number(currentPricing.shipping || 0).toFixed(2)}`;
        console.log('[PAYMENT-MODAL-FIX] Updated shipping to:', elements.orderShipping.textContent);
      }

      if (elements.orderTax) {
        elements.orderTax.textContent = `$${Number(currentPricing.tax || 0).toFixed(2)}`;
        console.log('[PAYMENT-MODAL-FIX] Updated tax to:', elements.orderTax.textContent);
      }

      if (elements.orderTotal) {
        elements.orderTotal.textContent = `$${Number(currentPricing.total || 0).toFixed(2)}`;
        console.log('[PAYMENT-MODAL-FIX] Updated total to:', elements.orderTotal.textContent);
      }

      // Call the original function if it exists
      if (originalRenderOrderSummary) {
        return originalRenderOrderSummary.apply(this, arguments);
      }
    };

    console.log('[PAYMENT-MODAL-FIX] ✅ renderOrderSummary overridden to use current pricing');
  }

  // Also intercept API responses to ensure DOM updates happen
  const originalApiClient = window.apiClient;
  if (originalApiClient && originalApiClient.post) {
    const originalPost = originalApiClient.post;

    apiClient.post = async function(url, data, options) {
      if (url.includes('/api/checkout_pricing.php')) {
        console.log('[PAYMENT-MODAL-FIX] 📡 API call to checkout_pricing.php detected');

        try {
          const response = await originalPost.call(this, url, data, options);

          if (response && response.success && response.pricing) {
            console.log('[PAYMENT-MODAL-FIX] 📥 API response received:', response.pricing);

            // Force DOM update after API response
            setTimeout(() => {
              const elements = {
                orderSubtotal: document.querySelector('#pm-orderSubtotal'),
                orderShipping: document.querySelector('#pm-orderShipping'),
                orderTax: document.querySelector('#pm-orderTax'),
                orderTotal: document.querySelector('#pm-orderTotal')
              };

              console.log('[PAYMENT-MODAL-FIX] 🔧 Force updating DOM elements:');

              if (elements.orderSubtotal) {
                elements.orderSubtotal.textContent = `$${Number(response.pricing.subtotal || 0).toFixed(2)}`;
                console.log('[PAYMENT-MODAL-FIX] ✅ Force updated subtotal:', elements.orderSubtotal.textContent);
              }

              if (elements.orderShipping) {
                elements.orderShipping.textContent = `$${Number(response.pricing.shipping || 0).toFixed(2)}`;
                console.log('[PAYMENT-MODAL-FIX] ✅ Force updated shipping:', elements.orderShipping.textContent);
              }

              if (elements.orderTax) {
                elements.orderTax.textContent = `$${Number(response.pricing.tax || 0).toFixed(2)}`;
                console.log('[PAYMENT-MODAL-FIX] ✅ Force updated tax:', elements.orderTax.textContent);
              }

              if (elements.orderTotal) {
                elements.orderTotal.textContent = `$${Number(response.pricing.total || 0).toFixed(2)}`;
                console.log('[PAYMENT-MODAL-FIX] ✅ Force updated total:', elements.orderTotal.textContent);
              }

              // Final verification
              setTimeout(() => {
                console.log('[PAYMENT-MODAL-FIX] 📋 Final verification:');
                Object.entries(elements).forEach(([name, element]) => {
                  if (element) {
                    const value = element.textContent;
                    console.log(`- ${name}: "${value}"`);

                    if (name === 'orderShipping' && (value === '$0.00' || value === '0.00')) {
                      console.error('[PAYMENT-MODAL-FIX] ❌ Shipping still shows $0.00');
                    }
                  }
                });
              }, 100);
            }, 100);
          }

          return response;
        } catch (error) {
          console.error('[PAYMENT-MODAL-FIX] ❌ API call failed:', error);
          throw error;
        }
      } else {
        return originalPost.call(this, url, data, options);
      }
    };

    console.log('[PAYMENT-MODAL-FIX] ✅ apiClient.post intercepted for DOM updates');
  }

  console.log('[PAYMENT-MODAL-FIX] Fix setup complete. Please open the payment modal and try changing shipping methods.');
})();
