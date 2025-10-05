// Vite entry: admin-pos.js
import '../css/admin-pos.css';
try {
  await import('../js/admin-pos.js');
} catch (e) {
  console.warn('[Vite] admin-pos.js module not found under src/js. Entry stub loaded.');
}
