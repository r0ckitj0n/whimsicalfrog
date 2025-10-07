// Quick check of shipping rates in database
// This will show us what shipping rates are currently configured

window.ApiClient.request('/api/business_settings.php?action=get_settings&category=ecommerce', { method: 'GET' })
.then(data => {
  console.log('Business settings response:', data);

  if (data.success && data.settings) {
    const settings = data.settings;
    const shippingRates = {
      'shipping_rate_usps': settings.shipping_rate_usps,
      'shipping_rate_fedex': settings.shipping_rate_fedex,
      'shipping_rate_ups': settings.shipping_rate_ups,
      'local_delivery_fee': settings.local_delivery_fee
    };

    console.log('Current shipping rates:');
    Object.entries(shippingRates).forEach(([key, value]) => {
      console.log(`- ${key}: $${value || 'NOT SET'}`);
    });

    // Check if any are 0 or missing
    const zeroRates = Object.entries(shippingRates).filter(([key, value]) => value === 0 || value === '0' || !value);

    if (zeroRates.length > 0) {
      console.error('❌ FOUND ZERO RATES - This is the problem!');
      console.log('Zero rates found:', zeroRates);
    } else {
      console.log('✅ All shipping rates look good');
    }
  } else {
    console.error('Failed to get business settings:', data);
  }
})
.catch(err => {
  console.error('Error checking business settings:', err);
});
