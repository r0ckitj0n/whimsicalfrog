// Add debugging to payment modal updatePricing function
// This will show us exactly what values are being returned and applied

(function() {
  console.log('[PAYMENT-MODAL-FIX] Starting payment modal debugging fix...');

  // Override the updatePricing function to add debugging
  const originalUpdatePricing = window.updatePricing;

  if (typeof originalUpdatePricing === 'function') {
    window.updatePricing = async function() {
      console.log('[PAYMENT-MODAL-FIX] 🔧 updatePricing called');

      try {
        // Call the original function
        const result = await originalUpdatePricing.apply(this, arguments);

        // Add our debugging after the original function completes
        setTimeout(() => {
          debugAfterUpdate();
        }, 100);

        return result;
      } catch (error) {
        console.error('[PAYMENT-MODAL-FIX] updatePricing error:', error);
        throw error;
      }
    };

    console.log('[PAYMENT-MODAL-FIX] ✅ updatePricing function overridden with debugging');
  } else {
    console.error('[PAYMENT-MODAL-FIX] ❌ updatePricing function not found');
  }

  function debugAfterUpdate() {
    const elements = {
      orderSubtotal: document.querySelector('#pm-orderSubtotal'),
      orderShipping: document.querySelector('#pm-orderShipping'),
      orderTax: document.querySelector('#pm-orderTax'),
      orderTotal: document.querySelector('#pm-orderTotal'),
      shippingMethod: document.querySelector('#pm-shippingMethodSelect')
    };

    console.log('[PAYMENT-MODAL-FIX] Current DOM element values:');
    Object.entries(elements).forEach(([name, element]) => {
      if (element) {
        const value = element.textContent || element.value;
        console.log(`- ${name}: "${value}"`);

        // Check if this element should have been updated
        if (name === 'orderShipping' && value === '$0.00') {
          console.warn(`[PAYMENT-MODAL-FIX] ⚠️ Shipping is still $0.00 - this might be the issue!`);
        }
      } else {
        console.error(`[PAYMENT-MODAL-FIX] ❌ ${name} element not found`);
      }
    });

    // Check if the modal is visible
    const modal = document.getElementById('paymentModalOverlay');
    if (modal) {
      const isVisible = modal.classList.contains('show');
      console.log('[PAYMENT-MODAL-FIX] Modal visibility:', isVisible ? '✅ Visible' : '❌ Hidden');

      if (!isVisible) {
        console.warn('[PAYMENT-MODAL-FIX] ⚠️ Modal is hidden - updates might not be visible');
      }
    }
  }

  // Also add debugging to the payment modal's updatePricing function directly
  // by intercepting the API response
  const originalApiClient = window.apiClient;
  if (originalApiClient && originalApiClient.post) {
    const originalPost = originalApiClient.post;

    apiClient.post = async function(url, data, options) {
      if (url.includes('/api/checkout_pricing.php')) {
        console.log('[PAYMENT-MODAL-FIX] 📡 API call to checkout_pricing.php with data:', data);

        try {
          const response = await originalPost.call(this, url, data, options);

          if (response && response.success && response.pricing) {
            console.log('[PAYMENT-MODAL-FIX] 📥 API response pricing:', response.pricing);

            // Check if shipping is 0
            if (response.pricing.shipping === 0 || response.pricing.shipping === '0') {
              console.error('[PAYMENT-MODAL-FIX] ❌ API returned shipping: 0 - this is the problem!');
              console.log('[PAYMENT-MODAL-FIX] Shipping method was:', data.shippingMethod);
            } else {
              console.log('[PAYMENT-MODAL-FIX] ✅ API returned shipping:', response.pricing.shipping);
            }
          } else {
            console.error('[PAYMENT-MODAL-FIX] ❌ API response failed or missing pricing:', response);
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

    console.log('[PAYMENT-MODAL-FIX] ✅ apiClient.post intercepted for debugging');
  }

  console.log('[PAYMENT-MODAL-FIX] Debugging setup complete. Try changing shipping method now.');
})();
