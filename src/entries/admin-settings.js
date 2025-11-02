// Vite entry: admin-settings.js
// Use the bridge (includes lightweight handlers and fallbacks)
import '../js/admin-settings-bridge.js';
import '../styles/components/tabs.css';
try {
  await import('../js/admin-settings.js');
} catch (e) {
  console.warn('[Vite] admin-settings.js module not found under src/js. Entry stub loaded.');
}

// Lightweight helpers are imported once above. They provide delegated click handlers for:
// - [data-action="open-area-item-mapper"], [data-action="open-background-manager"], [data-action="open-css-catalog"], [data-action="open-room-map-editor"]
// and lazy modal factories.

// Ensure buttons inside each settings card are sorted alphabetically by label
(function(){
  function normalizeLabel(el){
    const t = (el.textContent || '').trim().toLowerCase();
    // Remove common emoji/prefix characters for sorting purposes
    return t.replace(/[\p{Extended_Pictographic}\p{Emoji_Presentation}]/gu, '').replace(/[^a-z0-9&\s]/g, '').trim();
  }

  function sortSettingsCardButtons() {
    try {
      const sections = document.querySelectorAll('.settings-section .section-content');
      sections.forEach(section => {
        const nodes = Array.from(section.querySelectorAll('button.admin-settings-button, a.admin-settings-button'));
        if (nodes.length < 2) return;
        const sorted = nodes.slice().sort((a, b) => normalizeLabel(a).localeCompare(normalizeLabel(b)));
        sorted.forEach(el => section.appendChild(el));
      });
    } catch (_) {}
  }

  // Debounced helper for frequent DOM changes
  let sortTimer = null;
  function scheduleSort(delay = 50){
    if (sortTimer) clearTimeout(sortTimer);
    sortTimer = setTimeout(sortSettingsCardButtons, delay);
  }

  // Run after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      sortSettingsCardButtons();
      scheduleSort(150);
    }, { once: true });
  } else {
    sortSettingsCardButtons();
    scheduleSort(150);
  }

  // Also run after the main module import and on window load
  setTimeout(sortSettingsCardButtons, 0);
  setTimeout(sortSettingsCardButtons, 300);
  window.addEventListener('load', () => scheduleSort(0), { once: true });

  // Observe dynamic mutations inside the settings root to keep order stable
  try {
    const root = document.getElementById('adminSettingsRoot') || document;
    const obs = new MutationObserver(() => scheduleSort(50));
    obs.observe(root, { subtree: true, childList: true });
    // Expose for manual re-run if future code adds more items dynamically
    window.__wfSortSettingsButtons = sortSettingsCardButtons;
  } catch(_) {
    try { window.__wfSortSettingsButtons = sortSettingsCardButtons; } catch(_) {}
  }
})();

(function(){
  const variants = ['btn-primary','btn-secondary','btn-danger','btn-info','btn-warning','btn-success','btn-link','btn-xs','btn-sm','btn-lg'];
  function ensureBtnBase(el){
    if (!el || !el.classList) return;
    if (el.classList.contains('btn')) return;
    for (const v of variants){ if (el.classList.contains(v)) { el.classList.add('btn'); break; } }
  }
  function scan(root){
    try {
      const nodes = root.querySelectorAll('button, a[role="button"], a.btn-primary, a.btn-secondary, a.btn-danger, a.btn-info, a.btn-warning, a.btn-success');
      nodes.forEach(ensureBtnBase);
    } catch(_){ }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => scan(document), { once: true });
  } else {
    scan(document);
  }
  try {
    const root = document.getElementById('adminSettingsRoot') || document;
    const obs = new MutationObserver((muts)=>{
      for (const m of muts){
        m.addedNodes && m.addedNodes.forEach(n=>{ if (n.nodeType===1){ ensureBtnBase(n); try { scan(n); } catch(_){} } });
      }
    });
    obs.observe(root, { subtree: true, childList: true });
    window.__wfEnsureBtnBase = () => scan(document);
  } catch(_){ try { window.__wfEnsureBtnBase = () => scan(document); } catch(_){} }
})();
