// Vite entry: header-bootstrap.js
// Apply background from body[data-bg-url] without inline styles
import '../../body-background-from-data.js';
try {
  await import('../js/header-bootstrap.js');
} catch (e) {
  console.warn('[Vite] header-bootstrap.js module not found under src/js. Entry stub loaded.');
}
