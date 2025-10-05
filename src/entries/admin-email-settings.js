// Vite entry: admin-email-settings.js
try {
  await import('../js/admin-email-settings.js');
} catch (e) {
  console.warn('[Vite] admin-email-settings.js module not found under src/js. Entry stub loaded.');
}
