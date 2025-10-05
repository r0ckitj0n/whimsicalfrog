// Vite entry: admin-inventory.js
// Import existing admin inventory module if present
try {
  await import('../js/admin-inventory.js');
} catch (e) {
  console.warn('[Vite] admin-inventory.js module not found under src/js. Entry stub loaded.');
}

// Load Option Cascade & Grouping controller
try {
  await import('../js/admin-option-settings.js');
} catch (e) {
  console.warn('[Vite] admin-option-settings.js not found. Option panel will be inactive.', e);
}
