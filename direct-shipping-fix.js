// Direct database check and fix for shipping rates
// This will check your database and update the shipping rates

(async function() {
  console.log('[SHIPPING-FIX] Starting direct shipping rates check and fix...');

  try {
    // First, check the current shipping rates
    console.log('[SHIPPING-FIX] 1. Checking current shipping rates...');
    const checkData = await window.ApiClient.request('/api/business_settings.php?action=get_settings&category=ecommerce', { method: 'GET' });
    console.log('[SHIPPING-FIX] Current business settings:', checkData);

    if (checkData.success && checkData.settings) {
      const rates = {
        'shipping_rate_usps': checkData.settings.shipping_rate_usps || 0,
        'shipping_rate_fedex': checkData.settings.shipping_rate_fedex || 0,
        'shipping_rate_ups': checkData.settings.shipping_rate_ups || 0,
        'local_delivery_fee': checkData.settings.local_delivery_fee || 0
      };

      console.log('[SHIPPING-FIX] Current shipping rates:', rates);

      // Check if any rates are 0
      const zeroRates = Object.entries(rates).filter(([key, value]) => value === 0 || value === '0' || value === null || value === undefined);

      if (zeroRates.length > 0) {
        console.log('[SHIPPING-FIX] ❌ Found zero rates:', zeroRates);
        console.log('[SHIPPING-FIX] 2. Updating shipping rates...');

        // Update the rates
        const updateData = await window.ApiClient.request('/api/business_settings.php?action=upsert_settings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            settings: {
              shipping_rate_usps: 8.99,
              shipping_rate_fedex: 12.99,
              shipping_rate_ups: 12.99,
              local_delivery_fee: 5.00
            },
            category: 'ecommerce'
          })
        });
        console.log('[SHIPPING-FIX] Update response:', updateData);

        if (updateData.success) {
          console.log('[SHIPPING-FIX] ✅ Shipping rates updated successfully!');

          // Test with a direct API call
          console.log('[SHIPPING-FIX] 3. Testing updated rates...');
          const testData = await window.ApiClient.request('/api/checkout_pricing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              itemIds: ['WF-TS-002'],
              quantities: [1],
              shippingMethod: 'USPS',
              debug: true
            })
          });
          console.log('[SHIPPING-FIX] Test API response:', testData);

          if (testData.success && testData.pricing) {
            console.log('[SHIPPING-FIX] New shipping cost:', testData.pricing.shipping);
            console.log('[SHIPPING-FIX] New total cost:', testData.pricing.total);

            if (testData.pricing.shipping > 0) {
              console.log('[SHIPPING-FIX] 🎉 SUCCESS! Shipping rates are now working.');
              console.log('[SHIPPING-FIX] Please open the payment modal and try changing shipping methods.');
            } else {
              console.error('[SHIPPING-FIX] ❌ Shipping is still 0. There may be another issue.');
            }
          } else {
            console.error('[SHIPPING-FIX] ❌ Test API call failed:', testData);
          }
        } else {
          console.error('[SHIPPING-FIX] ❌ Failed to update shipping rates:', updateData);
        }
      } else {
        console.log('[SHIPPING-FIX] ✅ All shipping rates are already set correctly');
        console.log('[SHIPPING-FIX] The issue might be elsewhere. Let me check...');

        // Test with current rates
        const testData = await window.ApiClient.request('/api/checkout_pricing.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            itemIds: ['WF-TS-002'],
            quantities: [1],
            shippingMethod: 'USPS',
            debug: true
          })
        });
        console.log('[SHIPPING-FIX] Test with current rates:', testData);

        if (testData.pricing?.shipping > 0) {
          console.log('[SHIPPING-FIX] ✅ Current rates are working. The issue might be in the payment modal display.');
        } else {
          console.log('[SHIPPING-FIX] ❌ Current rates still return 0. There is a different issue.');
        }
      }
    } else {
      console.error('[SHIPPING-FIX] ❌ Failed to get business settings:', checkData);
    }
  } catch (error) {
    console.error('[SHIPPING-FIX] ❌ Error:', error);
  }

  console.log('[SHIPPING-FIX] Fix process complete.');
})();
