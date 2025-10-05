// Vite entry: admin-customers.js
try {
  await import('../js/admin-customers.js');
} catch (e) {
  console.warn('[Vite] admin-customers.js module not found under src/js. Entry stub loaded.');
}
