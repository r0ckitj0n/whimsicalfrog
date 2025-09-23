// Test shipping configuration directly
// This will check if the issue is with the shipping rates in the database

fetch('/api/checkout_pricing.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  credentials: 'same-origin',
  body: JSON.stringify({
    itemIds: ['WF-TS-002'],
    quantities: [1],
    shippingMethod: 'USPS',
    debug: true
  })
}).then(r => r.json()).then(data => {
  console.log('Direct API test result:', data);

  if (data.success && data.pricing) {
    console.log('Shipping cost:', data.pricing.shipping);
    console.log('Total cost:', data.pricing.total);

    if (data.pricing.shipping === 0) {
      console.error('❌ SHIPPING IS 0 - This is the problem!');
      console.log('Check your shipping rates in the database');
    } else {
      console.log('✅ Shipping cost looks correct');
    }

    if (data.debug) {
      console.log('Debug info:', data.debug);
    }
  } else {
    console.error('API call failed:', data);
  }
}).catch(err => {
  console.error('API test error:', err);
});
