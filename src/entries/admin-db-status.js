// Vite entry: admin-db-status.js
try {
  await import('../js/admin-db-status.js');
} catch (e) {
  console.warn('[Vite] admin-db-status.js module not found under src/js. Entry stub loaded.');
}
