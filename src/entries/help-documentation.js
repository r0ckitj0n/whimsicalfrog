// Vite entry: help-documentation.js
try {
  await import('../js/help-documentation.js');
} catch (e) {
  console.warn('[Vite] help-documentation.js module not found under src/js. Entry stub loaded.');
}
