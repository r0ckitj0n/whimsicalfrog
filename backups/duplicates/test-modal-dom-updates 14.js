// Test payment modal DOM updates directly
// This will test if the payment modal's DOM update logic is working

(async function() {
  console.log('[MODAL-TEST] Testing payment modal DOM updates...');

  // First, let's check if we can trigger the payment modal
  if (typeof window.WF_PaymentModal !== 'undefined' && window.WF_PaymentModal.open) {
    console.log('[MODAL-TEST] ✅ Payment modal available');

    // Open the payment modal
    console.log('[MODAL-TEST] Opening payment modal...');
    window.WF_PaymentModal.open();

    // Wait a bit for it to load
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Check if modal elements exist
    const modalElements = {
      orderShipping: document.querySelector('#pm-orderShipping'),
      orderTotal: document.querySelector('#pm-orderTotal'),
      shippingMethod: document.querySelector('#pm-shippingMethodSelect')
    };

    console.log('[MODAL-TEST] Modal elements found:');
    Object.entries(modalElements).forEach(([name, element]) => {
      if (element) {
        console.log(`- ✅ ${name}: found (${element.textContent || element.value})`);
      } else {
        console.log(`- ❌ ${name}: NOT FOUND`);
      }
    });

    // Test manual DOM updates
    if (modalElements.orderShipping && modalElements.orderTotal) {
      console.log('[MODAL-TEST] Testing manual DOM updates...');

      const testValues = [
        { shipping: '$5.00', total: '$55.00' },
        { shipping: '$10.00', total: '$60.00' },
        { shipping: '$15.00', total: '$65.00' }
      ];

      for (let i = 0; i < testValues.length; i++) {
        const { shipping, total } = testValues[i];

        setTimeout(() => {
          console.log(`[MODAL-TEST] Setting shipping to ${shipping}, total to ${total}`);
          modalElements.orderShipping.textContent = shipping;
          modalElements.orderTotal.textContent = total;

          // Check if updates were applied
          setTimeout(() => {
            console.log(`[MODAL-TEST] After update - Shipping: ${modalElements.orderShipping.textContent}, Total: ${modalElements.orderTotal.textContent}`);
          }, 100);
        }, i * 1000);
      }
    } else {
      console.log('[MODAL-TEST] ❌ Cannot test DOM updates - elements not found');
    }

  } else {
    console.log('[MODAL-TEST] ❌ Payment modal not available. Try opening it first.');
    console.log('[MODAL-TEST] You can open it by clicking the cart checkout button.');
  }

  console.log('[MODAL-TEST] Payment modal DOM test complete.');
})();
