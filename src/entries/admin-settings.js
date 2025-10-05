// Vite entry: admin-settings.js
try {
  await import('../js/admin-settings.js');
} catch (e) {
  console.warn('[Vite] admin-settings.js module not found under src/js. Entry stub loaded.');
}
