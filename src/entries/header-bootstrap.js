// Vite entry: header-bootstrap.js
try {
  await import('../js/header-bootstrap.js');
} catch (e) {
  console.warn('[Vite] header-bootstrap.js module not found under src/js. Entry stub loaded.');
}
