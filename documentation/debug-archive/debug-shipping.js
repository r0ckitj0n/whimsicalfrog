// Debug version of shipping method change handler
function debugShippingMethodChange() {
  const shipMethodSel = document.getElementById('shippingMethodSelect');
  if (!shipMethodSel) {
    console.error('[DEBUG] Shipping method select element not found');
    return;
  }

  console.log('[DEBUG] Current shipping method value:', shipMethodSel.value);

  // Add event listener for debugging
  shipMethodSel.addEventListener('change', async () => {
    const selectedValue = shipMethodSel.value;
    console.log('[DEBUG] Shipping method changed to:', selectedValue);

    // Check if API client is available
    if (typeof apiClient === 'undefined') {
      console.error('[DEBUG] apiClient is not available');
      return;
    }

    // Try to make the API call
    try {
      console.log('[DEBUG] Making pricing API call with shipping method:', selectedValue);
      const payload = {
        itemIds: ['WF-TS-002'], // Test item
        quantities: [1],
        shippingMethod: selectedValue,
        debug: true
      };

      const response = await apiClient.post('/api/checkout_pricing.php', payload);
      console.log('[DEBUG] API response:', response);

      if (response && response.success && response.pricing) {
        console.log('[DEBUG] Shipping cost from API:', response.pricing.shipping);
      } else {
        console.error('[DEBUG] API call failed or returned invalid response');
      }
    } catch (error) {
      console.error('[DEBUG] API call error:', error);
    }
  });

  // Trigger initial change to test
  console.log('[DEBUG] Triggering initial change event');
  shipMethodSel.dispatchEvent(new Event('change', { bubbles: true }));
}

// Call debug function when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', debugShippingMethodChange);
} else {
  debugShippingMethodChange();
}
