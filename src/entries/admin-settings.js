// Vite entry: admin-settings.js
// Load lightweight settings (CSS + minimal handlers)
import '../modules/admin-settings-lightweight.js';
import '../modules/modal-managers.js';
import '../js/admin-settings.js';
// Load the full bridge (heavier UI, pickers, delegates) lazily on first interaction in Settings
(function(){
  try {
    const params = new URLSearchParams(window.location.search || '');
    const noBridge = params.get('wf_diag_no_bridge') === '1';
    if (noBridge) {
      console.warn('[AdminSettings] Skipping admin-settings-bridge due to wf_diag_no_bridge=1');
      return;
    }
    let loaded = false;
    const loadBridge = () => { if (loaded) return; loaded = true; try { import('../js/admin-settings-bridge.js').catch(() => {}); } catch(_) {} };
    const onClick = (e) => { try { const scope = document.querySelector('.settings-page') || document.body; if (scope && scope.contains(e.target)) loadBridge(); } catch(_) {} };
    const onKey = (e) => { try { if (!e) return; const scope = document.querySelector('.settings-page') || document.body; if (!scope) return; const t = e.target; const key = e.key; if ((key === 'Enter' || key === ' ') && t && scope.contains(t)) loadBridge(); } catch(_) {} };
    try { document.addEventListener('click', onClick, true); } catch(_) {}
    try { document.addEventListener('keydown', onKey, true); } catch(_) {}
    // Safety: remove listeners after bridge loads
    const cleanup = () => { try { document.removeEventListener('click', onClick, true); document.removeEventListener('keydown', onKey, true); } catch(_) {} };
    // Hook into bridge load to cleanup
    const tryCleanup = () => { if (loaded) cleanup(); else setTimeout(tryCleanup, 1500); };
    setTimeout(tryCleanup, 1500);
  } catch(_) { /* noop */ }
})();
import '../styles/components/tabs.css';
// Defer heavy legacy module; load on idle or first interaction unless disabled
(function(){
  // Legacy admin-settings is loaded eagerly above via static import to ensure
  // modal logic and scroll lock management are available immediately on this page.
})();

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
        let changed = false;
        for (let i = 0; i < nodes.length; i++) {
          if (nodes[i] !== sorted[i]) { changed = true; break; }
        }
        if (!changed) return;
        try { window.__wfSortingSettings = true; } catch(_) {}
        // Minimal reorder: appending in sorted order moves only when needed
        sorted.forEach(el => section.appendChild(el));
      });
    } catch (_) {}
  }

  // Debounced helper for frequent DOM changes
  let sortTimer = null;
  let sorting = false;
  function scheduleSort(delay = 50){
    if (sorting) return;
    if (sortTimer) clearTimeout(sortTimer);
    sortTimer = setTimeout(() => { sorting = true; try { sortSettingsCardButtons(); } finally { sorting = false; try { window.__wfSortingSettings = false; } catch(_) {} } }, delay);
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
    const obs = new MutationObserver(() => { if (window.__wfSortingSettings) return; scheduleSort(50); });
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
