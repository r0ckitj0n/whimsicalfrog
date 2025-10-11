// Vite entry: admin-pos.js (deprecated) -> forwards to pos.js
try { console.warn('[POS Entry] admin-pos.js is deprecated; forwarding to pos.js'); } catch(_) {}
import '../css/admin-pos.css';
import '../styles/components/pos.css';
import '../styles/components/modal.css';
import '../styles/components/detailed-item-modal.css';
import '../styles/site-base.css';
import '../styles/z-index.css';
import '../styles/components/qty-button.css';
import '../styles/components/room-modal.css';
import '../styles/components/popup.css';
import '../styles/main.css';
import '../js/global-item-modal.js';
import('../js/pos.js')
  .then(() => { try { console.log('[POS Entry] pos.js imported via admin-pos shim'); } catch(_) {} })
  .catch((e) => { try { console.warn('[POS Entry] shim failed to import pos.js', e); } catch(_) {} });
