// Payment modal debugging script
// This will help verify if the DOM elements are being updated correctly

(function() {
  console.log('[PAYMENT-MODAL-DEBUG] Starting payment modal debugging...');

  // Wait for modal to be available
  function waitForModal(callback, maxAttempts = 20) {
    let attempts = 0;
    const check = () => {
      const modal = document.getElementById('paymentModalOverlay');
      if (modal && modal.classList.contains('show')) {
        console.log('[PAYMENT-MODAL-DEBUG] ✅ Payment modal found and visible');
        callback(modal);
      } else if (attempts < maxAttempts) {
        attempts++;
        console.log(`[PAYMENT-MODAL-DEBUG] Waiting for modal... attempt ${attempts}`);
        setTimeout(check, 500);
      } else {
        console.error('[PAYMENT-MODAL-DEBUG] ❌ Payment modal not found after', maxAttempts, 'attempts');
      }
    };
    check();
  }

  // Monitor DOM elements for changes
  function monitorElements() {
    const elements = {
      orderSubtotal: '#pm-orderSubtotal',
      orderShipping: '#pm-orderShipping',
      orderTax: '#pm-orderTax',
      orderTotal: '#pm-orderTotal',
      shippingMethod: '#pm-shippingMethodSelect'
    };

    console.log('[PAYMENT-MODAL-DEBUG] Monitoring DOM elements...');

    // Check initial state
    Object.entries(elements).forEach(([name, selector]) => {
      const el = document.querySelector(selector);
      if (el) {
        console.log(`[PAYMENT-MODAL-DEBUG] ✅ ${name}:`, {
          value: el.textContent || el.value,
          tagName: el.tagName,
          id: el.id,
          className: el.className
        });
      } else {
        console.error(`[PAYMENT-MODAL-DEBUG] ❌ ${name} not found with selector: ${selector}`);
      }
    });

    // Set up mutation observer to watch for changes
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList' || mutation.type === 'characterData') {
          const target = mutation.target;
          if (target.id && target.id.startsWith('pm-order')) {
            console.log('[PAYMENT-MODAL-DEBUG] 🎯 DOM element updated:', {
              id: target.id,
              newValue: target.textContent,
              mutationType: mutation.type
            });
          }
        }
      });
    });

    // Start observing the modal container
    const modal = document.getElementById('paymentModalOverlay');
    if (modal) {
      observer.observe(modal, {
        childList: true,
        subtree: true,
        characterData: true
      });
      console.log('[PAYMENT-MODAL-DEBUG] ✅ Mutation observer started');
    }
  }

  // Test manual updates
  function testManualUpdates() {
    const elements = {
      orderSubtotal: document.querySelector('#pm-orderSubtotal'),
      orderShipping: document.querySelector('#pm-orderShipping'),
      orderTax: document.querySelector('#pm-orderTax'),
      orderTotal: document.querySelector('#pm-orderTotal')
    };

    console.log('[PAYMENT-MODAL-DEBUG] Testing manual DOM updates...');

    // Test updating each element
    if (elements.orderShipping) {
      const testValues = ['$5.00', '$10.00', '$15.00', '$0.00'];

      testValues.forEach((value, index) => {
        setTimeout(() => {
          console.log(`[PAYMENT-MODAL-DEBUG] Testing shipping update to: ${value}`);
          elements.orderShipping.textContent = value;

          // Check if update was successful
          setTimeout(() => {
            const actualValue = elements.orderShipping.textContent;
            console.log(`[PAYMENT-MODAL-DEBUG] Update result: expected ${value}, got ${actualValue}`);
            if (actualValue === value) {
              console.log(`[PAYMENT-MODAL-DEBUG] ✅ DOM update successful`);
            } else {
              console.error(`[PAYMENT-MODAL-DEBUG] ❌ DOM update failed`);
            }
          }, 100);
        }, index * 1000);
      });
    }
  }

  // Test shipping method changes
  function testShippingMethodChanges() {
    const shipMethodSelect = document.querySelector('#pm-shippingMethodSelect');
    if (shipMethodSelect) {
      console.log('[PAYMENT-MODAL-DEBUG] Current shipping method:', shipMethodSelect.value);

      // Add event listener to monitor changes
      shipMethodSelect.addEventListener('change', (event) => {
        console.log('[PAYMENT-MODAL-DEBUG] 🎉 Shipping method changed to:', event.target.value);
      });

      // Test different methods
      const methods = ['USPS', 'FedEx', 'UPS', 'Customer Pickup'];

      methods.forEach((method, index) => {
        setTimeout(() => {
          console.log(`[PAYMENT-MODAL-DEBUG] Testing method change to: ${method}`);
          shipMethodSelect.value = method;
          shipMethodSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }, (index + 1) * 1500);
      });
    } else {
      console.error('[PAYMENT-MODAL-DEBUG] ❌ Shipping method select not found');
    }
  }

  // Main debugging function
  function debugPaymentModal() {
    console.log('[PAYMENT-MODAL-DEBUG] === PAYMENT MODAL DEBUGGING START ===');

    waitForModal((modal) => {
      monitorElements();
      setTimeout(() => {
        testManualUpdates();
        testShippingMethodChanges();
      }, 1000);
    });

    console.log('[PAYMENT-MODAL-DEBUG] === PAYMENT MODAL DEBUGGING END ===');
  }

  // Start debugging
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', debugPaymentModal);
  } else {
    debugPaymentModal();
  }
})();
