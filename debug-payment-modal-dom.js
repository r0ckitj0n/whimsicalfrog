// Debug payment modal DOM updates in real-time
// This will show us exactly what values are being set and when

(function() {
  console.log('[PAYMENT-MODAL-DEBUG] Starting real-time DOM update debugging...');

  // Monitor the payment modal elements for changes
  function monitorPaymentModalElements() {
    const elements = {
      orderSubtotal: document.querySelector('#pm-orderSubtotal'),
      orderShipping: document.querySelector('#pm-orderShipping'),
      orderTax: document.querySelector('#pm-orderTax'),
      orderTotal: document.querySelector('#pm-orderTotal'),
      shippingMethod: document.querySelector('#pm-shippingMethodSelect')
    };

    // Check initial values
    console.log('[PAYMENT-MODAL-DEBUG] Initial element values:');
    Object.entries(elements).forEach(([name, element]) => {
      if (element) {
        const value = element.textContent || element.value;
        console.log(`- ${name}: "${value}"`);
      } else {
        console.error(`[PAYMENT-MODAL-DEBUG] ❌ ${name} element not found`);
      }
    });

    // Set up mutation observer to watch for changes
    const modal = document.getElementById('paymentModalOverlay');
    if (modal) {
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.type === 'childList' || mutation.type === 'characterData') {
            const target = mutation.target;
            if (target.id && target.id.startsWith('pm-order')) {
              const elementName = target.id.replace('pm-', '');
              console.log(`[PAYMENT-MODAL-DEBUG] 🎯 DOM element updated: ${elementName} = "${target.textContent}"`);
            }
          }
        });
      });

      observer.observe(modal, {
        childList: true,
        subtree: true,
        characterData: true
      });

      console.log('[PAYMENT-MODAL-DEBUG] ✅ Mutation observer started on payment modal');
    } else {
      console.log('[PAYMENT-MODAL-DEBUG] ⚠️ Payment modal not found - trying again in 2 seconds...');

      setTimeout(() => {
        const modalRetry = document.getElementById('paymentModalOverlay');
        if (modalRetry) {
          console.log('[PAYMENT-MODAL-DEBUG] ✅ Payment modal found on retry');
          monitorPaymentModalElements();
        } else {
          console.error('[PAYMENT-MODAL-DEBUG] ❌ Payment modal still not found');
        }
      }, 2000);
    }

    return elements;
  }

  // Override the payment modal's updatePricing function to add debugging
  const originalUpdatePricing = window.updatePricing;
  if (typeof originalUpdatePricing === 'function') {
    window.updatePricing = async function() {
      console.log('[PAYMENT-MODAL-DEBUG] 🔧 updatePricing function called');

      try {
        const result = await originalUpdatePricing.apply(this, arguments);

        // Check what happened after the update
        setTimeout(() => {
          console.log('[PAYMENT-MODAL-DEBUG] 📋 Checking DOM after updatePricing...');
          const elements = document.querySelectorAll('[id^="pm-order"]');
          elements.forEach(el => {
            console.log(`- ${el.id}: "${el.textContent}"`);
          });
        }, 100);

        return result;
      } catch (error) {
        console.error('[PAYMENT-MODAL-DEBUG] updatePricing error:', error);
        throw error;
      }
    };

    console.log('[PAYMENT-MODAL-DEBUG] ✅ updatePricing function overridden with debugging');
  }

  // Start monitoring
  monitorPaymentModalElements();

  console.log('[PAYMENT-MODAL-DEBUG] Debugging setup complete. Please open the payment modal and try changing shipping methods.');
})();
