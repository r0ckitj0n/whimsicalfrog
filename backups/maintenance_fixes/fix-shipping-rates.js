// Fix shipping rates in database - run this to set proper default values
// This will update your business settings with correct shipping rates

window.ApiClient.request('/api/business_settings.php?action=upsert_settings', {
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
}).then(data => {
  console.log('Update shipping rates response:', data);

  if (data.success) {
    console.log('✅ Shipping rates updated successfully!');
    console.log('Please refresh the payment modal to see the changes');

    // Test with a new API call to verify
    setTimeout(async () => {
      try {
        const data2 = await window.ApiClient.request('/api/checkout_pricing.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            itemIds: ['WF-TS-002'],
            quantities: [1],
            shippingMethod: 'USPS',
            debug: true
          })
        });
        console.log('Verification API call result:', data2);
        if (data2.pricing) {
          console.log('New shipping cost:', data2.pricing.shipping);
          console.log('New total:', data2.pricing.total);
        }
      } catch (e) {
        console.error('Verification API call failed:', e);
      }
    }, 1000);
  } else {
    console.error('❌ Failed to update shipping rates:', data);
  }
}).catch(err => {
  console.error('Error updating shipping rates:', err);
});
