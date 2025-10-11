// Direct fix for payment modal DOM updates
// This will intercept API responses and ensure DOM updates work correctly

(function() {
  console.log('[PAYMENT-MODAL-FIX] Starting direct DOM update fix...');

  // Override the apiClient to intercept checkout_pricing responses
  const originalApiClient = window.apiClient;
  if (originalApiClient && originalApiClient.post) {
    const originalPost = originalApiClient.post;

    apiClient.post = async function(url, data, options) {
      if (url.includes('/api/checkout_pricing.php')) {
        console.log('[PAYMENT-MODAL-FIX] 📡 Intercepted API call to checkout_pricing.php');

        try {
          const response = await originalPost.call(this, url, data, options);

          if (response && response.success && response.pricing) {
            console.log('[PAYMENT-MODAL-FIX] 📥 API response received:', response.pricing);

            // Force DOM update after a short delay
            setTimeout(() => {
              forceDOMUpdate(response.pricing, data.shippingMethod);
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

  function forceDOMUpdate(pricing, shippingMethod) {
    console.log('[PAYMENT-MODAL-FIX] 🔧 Forcing DOM update with pricing:', pricing);

    const elements = {
      orderSubtotal: document.querySelector('#pm-orderSubtotal'),
      orderShipping: document.querySelector('#pm-orderShipping'),
      orderTax: document.querySelector('#pm-orderTax'),
      orderTotal: document.querySelector('#pm-orderTotal')
    };

    // Check which elements exist
    console.log('[PAYMENT-MODAL-FIX] Available elements:');
    Object.entries(elements).forEach(([name, element]) => {
      if (element) {
        console.log(`- ✅ ${name}: found`);
      } else {
        console.log(`- ❌ ${name}: NOT FOUND`);
      }
    });

    // Update the elements that exist
    if (elements.orderSubtotal) {
      elements.orderSubtotal.textContent = `$${Number(pricing.subtotal || 0).toFixed(2)}`;
      console.log('[PAYMENT-MODAL-FIX] ✅ Updated subtotal:', elements.orderSubtotal.textContent);
    }

    if (elements.orderShipping) {
      elements.orderShipping.textContent = `$${Number(pricing.shipping || 0).toFixed(2)}`;
      console.log('[PAYMENT-MODAL-FIX] ✅ Updated shipping:', elements.orderShipping.textContent);
    }

    if (elements.orderTax) {
      elements.orderTax.textContent = `$${Number(pricing.tax || 0).toFixed(2)}`;
      console.log('[PAYMENT-MODAL-FIX] ✅ Updated tax:', elements.orderTax.textContent);
    }

    if (elements.orderTotal) {
      elements.orderTotal.textContent = `$${Number(pricing.total || 0).toFixed(2)}`;
      console.log('[PAYMENT-MODAL-FIX] ✅ Updated total:', elements.orderTotal.textContent);
    }

    // Verify the updates worked
    setTimeout(() => {
      console.log('[PAYMENT-MODAL-FIX] 📋 Final verification:');
      Object.entries(elements).forEach(([name, element]) => {
        if (element) {
          const value = element.textContent;
          console.log(`- ${name}: "${value}"`);

          if (name === 'orderShipping' && (value === '$0.00' || value === '0.00')) {
            console.error(`[PAYMENT-MODAL-FIX] ❌ Shipping still shows ${value} - issue persists`);
          }
        }
      });

      console.log('[PAYMENT-MODAL-FIX] 🎉 DOM update fix completed for method:', shippingMethod);
    }, 50);
  }

  // Also monitor the original updatePricing function
  const originalUpdatePricing = window.updatePricing;
  if (typeof originalUpdatePricing === 'function') {
    window.updatePricing = async function() {
      console.log('[PAYMENT-MODAL-FIX] 🔧 Original updatePricing called');

      try {
        const result = await originalUpdatePricing.apply(this, arguments);
        console.log('[PAYMENT-MODAL-FIX] ✅ Original updatePricing completed');

        return result;
      } catch (error) {
        console.error('[PAYMENT-MODAL-FIX] ❌ Original updatePricing error:', error);
        throw error;
      }
    };

    console.log('[PAYMENT-MODAL-FIX] ✅ Original updatePricing wrapped with debugging');
  }

  console.log('[PAYMENT-MODAL-FIX] Fix setup complete. Please open the payment modal and try changing shipping methods.');
})();
