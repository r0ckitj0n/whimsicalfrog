// Vite entry: admin-orders.js
try {
  await import('../js/admin-orders.js');
} catch (e) {
  console.warn('[Vite] admin-orders.js module not found under src/js. Entry stub loaded.');
}
