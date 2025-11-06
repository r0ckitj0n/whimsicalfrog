// Admin Settings fallbacks (migrated from inline <script> blocks)
import { ApiClient } from '../core/api-client.js';
// - Modal close handlers (X button, delegated close, backdrop click, ESC)
// - Auto-size admin modals
// - Auto-resize same-origin iframes inside modals + cross-iframe message sizing
// - Open Shopping Cart modal action
// - Save Cart Settings action

(function(){
  try {
    // Kill any lingering room overlay that could steal clicks
    const ro = document.getElementById('roomModalOverlay');
    if (ro) {
      ro.classList.add('hidden');
      ro.classList.remove('show');
      ro.classList.add('pointer-events-none');
    }

    // Delegated close handler for settings modals (X buttons and dedicated close triggers)
    const onCloseClick = function(ev){
      const btn = ev.target && ev.target.closest ? ev.target.closest('.admin-modal-close,[data-action="close-admin-modal"]') : null;
      if (!btn) return;
      ev.preventDefault(); ev.stopPropagation();
      const overlay = btn.closest('.admin-modal-overlay');
      if (overlay) { overlay.classList.remove('show'); overlay.classList.add('hidden'); overlay.setAttribute('aria-hidden','true'); }
    };
    document.addEventListener('click', onCloseClick, true);

    // MutationObserver: auto-tag any inserted overlays as closable
    try {
      const __wfOverlayObserver = new MutationObserver((muts) => {
        muts.forEach((m) => {
          (m.addedNodes || []).forEach((n) => {
            if (!(n instanceof Element)) return;
            if (n.matches && n.matches('.admin-modal-overlay') && !n.classList.contains('wf-modal-closable')) {
              n.classList.add('wf-modal-closable');
            }
            n.querySelectorAll && n.querySelectorAll('.admin-modal-overlay').forEach((el) => {
              if (!el.classList.contains('wf-modal-closable')) el.classList.add('wf-modal-closable');
            });
          });
        });
      });
      __wfOverlayObserver.observe(document.body || document.documentElement, { childList: true, subtree: true });
    } catch(_) {}

    // Backdrop click-to-close for modal overlays marked as closable
    document.addEventListener('click', function(ev){
      const target = ev.target;
      if (target && target.classList && target.classList.contains('wf-modal-closable') && target.classList.contains('admin-modal-overlay')){
        ev.preventDefault(); ev.stopPropagation();
        target.classList.remove('show');
        target.classList.add('hidden');
        target.setAttribute('aria-hidden','true');
      }
    }, true);

    // ESC key closes the topmost visible closable modal
    document.addEventListener('keydown', function(ev){
      if (ev.key !== 'Escape') return;
      const overlays = Array.from(document.querySelectorAll('.admin-modal-overlay.wf-modal-closable')).filter((el) => !el.classList.contains('hidden'));
      if (!overlays.length) return;
      const top = overlays[overlays.length - 1];
      top.classList.remove('show');
      top.classList.add('hidden');
      top.setAttribute('aria-hidden','true');
      ev.preventDefault(); ev.stopPropagation();
    }, true);

    // Auto-size all settings modals to content
    function applyAutoSize(root){
      const scope = root || document;
      const panels = scope.querySelectorAll('.admin-modal.admin-modal-content');
      panels.forEach((p) => {
        if (p.closest('[data-size="fixed"]')) return;
        p.classList.remove('w-[90vw]', 'h-[85vh]');
        // Do not apply legacy auto panel helper to responsive modals
        if (p.classList.contains('admin-modal--responsive')) {
          p.classList.remove('wf-modal-auto');
          return;
        }
        if (!p.classList.contains('wf-modal-auto')) p.classList.add('wf-modal-auto');
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function(){ applyAutoSize(document); }, { once: true });
    } else {
      applyAutoSize(document);
    }
    try {
      const mo = new MutationObserver((muts) => {
        muts.forEach((m) => {
          (m.addedNodes || []).forEach((n) => {
            if (!(n instanceof Element)) return;
            if (n.matches && n.matches('.admin-modal.admin-modal-content')) applyAutoSize(n.parentNode || n);
            if (n.querySelectorAll) applyAutoSize(n);
          });
        });
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
    } catch(_) {}

    // Auto-resize same-origin iframes inside modals (data-auto-resize)
    function sizeIframe(iframe){
      try {
        if (!iframe || !iframe.contentDocument) return;
        const doc = iframe.contentDocument;
        const body = doc.body; const html = doc.documentElement;
        const h = Math.max(body ? body.scrollHeight : 0, body ? body.offsetHeight : 0, html ? html.scrollHeight : 0, html ? html.offsetHeight : 0);
        const px = Math.max(260, Math.min(h + 12, Math.floor(window.innerHeight * 0.85)));
        try { iframe.setAttribute('height', String(px)); } catch(_) {}
      } catch(_) {}
    }
    function bindAutoResize(iframe){
      if (!iframe || iframe.__wfAutoSized) return;
      iframe.__wfAutoSized = true;
      const onLoad = function(){
        sizeIframe(iframe);
        try {
          const body = iframe.contentDocument && iframe.contentDocument.body;
          if (body && 'ResizeObserver' in window) {
            const ro = new ResizeObserver(function(){ sizeIframe(iframe); });
            ro.observe(body);
            iframe.__wfResizeObserver = ro;
          }
        } catch(_) {}
      };
      iframe.addEventListener('load', onLoad);
      setTimeout(function(){ sizeIframe(iframe); }, 100);
    }
    function initIframeAuto(){
      document.querySelectorAll('iframe[data-auto-resize="true"]').forEach(bindAutoResize);
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initIframeAuto, { once: true });
    } else {
      initIframeAuto();
    }
    try {
      const mo2 = new MutationObserver((muts) => {
        muts.forEach((m) => {
          (m.addedNodes || []).forEach((n) => {
            if (!(n instanceof Element)) return;
            if (n.matches && n.matches('iframe[data-auto-resize="true"]')) bindAutoResize(n);
            if (n.querySelectorAll) n.querySelectorAll('iframe[data-auto-resize="true"]').forEach(bindAutoResize);
          });
        });
      });
      mo2.observe(document.documentElement, { childList: true, subtree: true });
    } catch(_) {}

    // Cross-iframe messaging: exact sizing from child when available
    window.addEventListener('message', function(ev){
      try {
        const d = ev.data || {};
        if (d && d.type === 'wf-iframe-size' && typeof d.height === 'number') {
          if (d.key === 'categories') {
            const f = document.getElementById('categoriesFrame');
            if (f) {
              const px = Math.max(160, Math.min(d.height + 12, Math.floor(window.innerHeight * 0.85)));
              try { f.setAttribute('height', String(px)); } catch(_) {}
              const p = f.closest('.admin-modal.admin-modal-content');
              if (p && !p.classList.contains('wf-modal-auto')) p.classList.add('wf-modal-auto');
            }
          }
        }
      } catch(_) {}
    });

    function getFocusable(root){
      try {
        const sel = 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])';
        const nodes = Array.from((root || document).querySelectorAll(sel));
        return nodes.filter((el)=>!el.hasAttribute('disabled') && !el.getAttribute('aria-hidden'));
      } catch(_) { return []; }
    }
    function enableFocusTrap(overlay){
      try {
        if (!overlay || overlay.__wfTrap) return; overlay.__wfTrap = true;
        const handler = function(e){
          if (e.key !== 'Tab') return;
          const focusables = getFocusable(overlay);
          if (!focusables.length) return;
          const first = focusables[0];
          const last = focusables[focusables.length - 1];
          const active = document.activeElement;
          if (e.shiftKey) {
            if (active === first || !overlay.contains(active)) { last.focus(); e.preventDefault(); }
          } else {
            if (active === last || !overlay.contains(active)) { first.focus(); e.preventDefault(); }
          }
        };
        overlay.addEventListener('keydown', handler, true);
        const initial = getFocusable(overlay)[0];
        try { initial && initial.focus && initial.focus(); } catch(_) {}
      } catch(_) {}
    }
    function simplifyCloudFrames(root){
      try {
        const scope = root || document;
        scope.querySelectorAll('.wf-cloud-card').forEach((card)=>{
          try {
            const content = card.querySelector('.content');
            if (!content) return;
            const wrap = document.createElement('div');
            wrap.innerHTML = content.innerHTML;
            card.replaceWith(wrap);
          } catch(_) {}
        });
      } catch(_) {}
    }

    function applySlimCartLayout(root){
      try {
        const scope = root || document;
        const panels = scope.querySelector('#cartSimulationPanels');
        if (!panels || panels.__wfSlimApplied) return;
        const kids = panels.children || [];
        const profile = kids[0];
        const recs = kids[1];
        if (!profile || !recs) return;
        panels.__wfSlimApplied = true;
        // Use utility classes instead of inline styles
        panels.classList.add('md:flex','md:items-start','md:gap-3');
        profile.classList.remove('p-3');
        profile.classList.add('w-48','md:w-56','shrink-0','p-2');
        recs.classList.add('md:flex-1','min-w-0');
      } catch(_) {}
    }

    // Ensure slim layout applies even if modal is already present/loaded
    (function ensureSlimInit(){
      try {
        applySlimCartLayout(document);
        const mo = new MutationObserver(function(){
          try { applySlimCartLayout(document); } catch(_){ }
        });
        mo.observe(document.documentElement, { childList: true, subtree: true });
      } catch(_) {}
    })();

    // Delegated handler: open Shopping Cart Settings modal
    if (!window.__wfBoundOpenShoppingCart) {
      window.__wfBoundOpenShoppingCart = true;
      document.addEventListener('click', (ev) => {
        const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="open-shopping-cart"]') : null;
        if (!t) return;
        ev.preventDefault(); ev.stopPropagation();
        const scm = document.getElementById('shoppingCartModal');
        if (scm) {
          if (scm.parentElement && scm.parentElement !== document.body) {
            document.body.appendChild(scm);
          }
          scm.classList.add('over-header');
          scm.classList.remove('hidden');
          scm.classList.add('show');
          scm.setAttribute('aria-hidden','false');
          scm.classList.remove('pointer-events-none');
          scm.classList.add('pointer-events-auto');
          try { simplifyCloudFrames(scm); } catch(_) {}
          try { applySlimCartLayout(scm); } catch(_) {}
          try { enableFocusTrap(scm); } catch(_) {}
          try { if (typeof window.__wfInitCartSimUI === 'function') window.__wfInitCartSimUI(); } catch(_) {}
        }
      }, true);
    }

    // Save Cart Settings action
    (function(){
      const btn = document.getElementById('saveCartSettingsBtn');
      if (!btn || btn.__wfBound) return;
      btn.__wfBound = true;
      btn.addEventListener('click', async function(){
        try {
          const openAdd = !!(document.getElementById('openCartOnAddCheckbox') && document.getElementById('openCartOnAddCheckbox').checked);
          const mergeDupes = !!(document.getElementById('mergeDuplicatesCheckbox') && document.getElementById('mergeDuplicatesCheckbox').checked);
          const showUpsells = !!(document.getElementById('showUpsellsCheckbox') && document.getElementById('showUpsellsCheckbox').checked);
          const confirmClear = !!(document.getElementById('confirmClearCheckbox') && document.getElementById('confirmClearCheckbox').checked);
          const minTotalEl = document.getElementById('minimumTotalInput');
          let minTotal = minTotalEl ? parseFloat(minTotalEl.value) : 0;
          if (!isFinite(minTotal) || minTotal < 0) minTotal = 0;

          const payload = {
            category: 'ecommerce',
            settings: {
              ecommerce_open_cart_on_add: openAdd,
              ecommerce_cart_merge_duplicates: mergeDupes,
              ecommerce_cart_show_upsells: showUpsells,
              ecommerce_cart_confirm_clear: confirmClear,
              ecommerce_cart_minimum_total: minTotal
            }
          };
          const body = { action: 'upsert_settings', ...payload };
          const j = await (window.WhimsicalFrog && WhimsicalFrog.api ? WhimsicalFrog.api.post('/api/business_settings.php', body) : ApiClient.post('/api/business_settings.php', body));
          if (j) {
            try {
              window.__WF_OPEN_CART_ON_ADD = openAdd;
              window.__WF_CART_MERGE_DUPES = mergeDupes;
              window.__WF_CART_SHOW_UPSELLS = showUpsells;
              window.__WF_CART_CONFIRM_CLEAR = confirmClear;
              window.__WF_CART_MIN_TOTAL = minTotal;
            } catch(_){ }
            if (window.wfNotifications && typeof window.wfNotifications.success === 'function') window.wfNotifications.success('Cart settings saved');
            else if (typeof window.showNotification === 'function') window.showNotification('Cart settings saved', 'success');
            else alert('Cart settings saved');
          } else {
            if (window.wfNotifications && typeof window.wfNotifications.error === 'function') window.wfNotifications.error('Failed to save settings');
            else if (typeof window.showNotification === 'function') window.showNotification('Failed to save settings', 'error');
            else alert('Failed to save settings');
          }
        } catch (e) {
          if (window.wfNotifications && typeof window.wfNotifications.error === 'function') window.wfNotifications.error('Error saving settings');
          else alert('Error saving settings');
        }
      });
    })();

    // Cart Simulation: delegated handlers and init
    (function(){
      if (window.__wfBoundCartSimHandlers) return; window.__wfBoundCartSimHandlers = true;

      
      function setStatus(msg, ok){
        const el = document.getElementById('cartSimulationStatus');
        if (!el) return;
        el.textContent = msg || '';
        el.classList.remove('text-green-700','text-red-700');
        el.classList.add(ok ? 'text-green-700' : 'text-red-700');
      }
      function fmtPrice(n){ try { const v = Number(n||0); return isFinite(v) ? `$${v.toFixed(2)}` : '$0.00'; } catch(_) { return '$0.00'; } }
      function escapeHtml(s){ try { return String(s||'').replace(/[&<>"']/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); } catch(_) { return String(s||''); } }
      function renderProfile(p){
        const profileEl = document.getElementById('cartSimulationProfile'); if (!profileEl) return;
        try {
          const rows = [ ['Preferred Category', p.preferredCategory || '—'], ['Budget', p.budget || '—'], ['Intent', p.intent || '—'], ['Device', p.device || '—'], ['Region', p.region || '—'] ];
          profileEl.innerHTML = '<ul class="list-none space-y-0.5">'+rows.map(([k,v])=>`<li><span class="text-gray-500">${escapeHtml(k)}:</span> ${escapeHtml(v)}</li>`).join('')+'</ul>';
        } catch(_) { profileEl.textContent = '—'; }
      }
      function renderSeed(skus){ const seedEl = document.getElementById('cartSimulationSeedSkus'); if (seedEl) { try { seedEl.textContent = Array.isArray(skus) && skus.length ? skus.join(', ') : '—'; } catch(_) { seedEl.textContent = '—'; } } }
      function renderRecs(recs, rationales){
        const recsEl = document.getElementById('cartSimulationRecs'); if (!recsEl) return;
        try {
          if (!Array.isArray(recs) || !recs.length) { recsEl.innerHTML = '<div class="text-sm text-gray-500">No recommendations.</div>'; return; }
          // Present a 4-column grid with details under each thumbnail
          try { recsEl.className = 'grid grid-cols-4 gap-3'; } catch(_) {}
          // Precompute a unique primary reason per item using priority order
          const normalizeReason = (s)=>{
            const t = String(s||'').toLowerCase();
            if (t.includes('top seller') && !t.includes('second')) return 'Site top seller';
            if (t.includes('second') && t.includes('seller')) return 'Site second-best seller';
            if (t.includes('category leader')) return 'Category leader';
            if (t.includes('strong performer')) return 'Strong performer in category';
            if (t.includes('preferred category')) return "Matches shopper's preferred category";
            if (t.includes('budget')) return 'Fits shopper budget';
            if (t.includes('high-performing')) return 'High-performing item in catalog';
            return s;
          };
          const priority = ['Site top seller','Category leader',"Matches shopper's preferred category",'Strong performer in category','Site second-best seller','Fits shopper budget','High-performing item in catalog'];
          const used = new Set();
          const primaryBySku = {};
          recs.forEach((r)=>{
            const sku = (r.sku||'').toString().toUpperCase();
            const rs = (rationales && rationales[sku]) ? rationales[sku] : [];
            const norm = rs.map(normalizeReason);
            let chosen = null;
            for (const label of priority) {
              if (norm.includes(label) && !used.has(label)) { chosen = label; break; }
            }
            if (!chosen) {
              // Try backups not yet used
              for (const label of ['Fits shopper budget','High-performing item in catalog']) {
                if (norm.includes(label) && !used.has(label)) { chosen = label; break; }
              }
            }
            if (!chosen) {
              // As a last resort, pick a generic unique label
              const fallback = 'Popular pick';
              chosen = used.has(fallback) ? (norm[0] || 'Recommended') : fallback;
            }
            used.add(chosen);
            primaryBySku[sku] = chosen;
          });
          recsEl.innerHTML = recs.map((r)=>{
            const sku = (r.sku||'').toString().toUpperCase();
            const name = r.name ? String(r.name) : sku;
            const price = fmtPrice(r.price);
            const title = `${name} (${sku}) · ${price}`;
            const img = r.image ? String(r.image) : '';
            const isPlaceholder = /placeholder/i.test(img || '');
            const cands = [];
            if (img && !isPlaceholder) {
              cands.push(img);
            } else if (sku) {
              const bases = [];
              const pushBase = (b) => { if (b && !bases.includes(b)) bases.push(b); };
              const raw = sku;
              pushBase(raw);
              pushBase(raw.toLowerCase());
              pushBase(raw.replace(/-/g, '_'));
              pushBase(raw.replace(/_/g, '-'));
              pushBase(raw.replace(/[-_]/g, ''));
              pushBase(raw.toLowerCase().replace(/[-_]/g, ''));
              // Progressive trimming by last hyphen/underscore
              const addTrims = (s) => {
                let cur = s;
                for (let i = 0; i < 4; i++) {
                  const m = cur.lastIndexOf('-');
                  const n = cur.lastIndexOf('_');
                  const idx = Math.max(m, n);
                  if (idx <= 0) break;
                  cur = cur.slice(0, idx);
                  pushBase(cur);
                  pushBase(cur.toLowerCase());
                  pushBase(cur.replace(/-/g, '_'));
                  pushBase(cur.replace(/_/g, '-'));
                }
              };
              addTrims(raw);
              addTrims(raw.toLowerCase());
              bases.forEach((b) => {
                const p = `/images/items/${encodeURIComponent(b)}`;
                cands.push(
                  `${p}.webp`, `${p}.png`,
                  `${p}A.webp`, `${p}A.png`, `${p}a.webp`, `${p}a.png`,
                  `${p}B.webp`, `${p}B.png`, `${p}b.webp`, `${p}b.png`
                );
              });
            }
            cands.push('/images/items/placeholder.webp');
            const first = cands[0] || '/images/items/placeholder.webp';
            const candidatesAttr = cands.map(escapeHtml).join('|');
            const thumbHtml = `
              <div class="w-16 h-16 rounded border overflow-hidden">
                <img src="${first}" alt="${escapeHtml(name)}" class="w-full h-full object-cover" width="64" height="64" loading="lazy" data-candidates="${candidatesAttr}" data-cursor="0" decoding="async" />
              </div>`;
            const primary = primaryBySku[sku];
            const allReasons = (rationales && rationales[sku] && Array.isArray(rationales[sku])) ? rationales[sku] : [];
            let intentR = '';
            try { intentR = allReasons.find((rx)=>/^Matches shopping intent/i.test(String(rx||''))) || ''; } catch(_){ intentR = ''; }
            const badges = [];
            if (primary) badges.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px] mr-1 mb-1">${escapeHtml(primary)}</span>`);
            if (intentR && intentR !== primary) badges.push(`<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200 text-[11px] mr-1 mb-1">${escapeHtml(intentR)}</span>`);
            const reasonHtml = badges.length ? (`<div class="mt-1">${badges.join('')}</div>`) : '';
            return `<div class=\"reco-thumb\" title=\"${escapeHtml(title)}\" aria-label=\"${escapeHtml(title)}\">
              ${thumbHtml}
              <div class=\"mt-1 text-sm font-medium truncate\" title=\"${escapeHtml(name)}\">${escapeHtml(name)}</div>
              <div class=\"text-xs text-gray-600\">${price}</div>
              ${reasonHtml}
            </div>`;
          }).join('');
          try {
            recsEl.querySelectorAll('img[data-candidates]').forEach((imgEl)=>{
              if (imgEl.__wfErrBound) return; imgEl.__wfErrBound = true;
              imgEl.addEventListener('error', function(){
                try {
                  const list = (imgEl.getAttribute('data-candidates') || '').split('|').filter(Boolean);
                  let cur = parseInt(imgEl.getAttribute('data-cursor') || '0', 10);
                  if (isNaN(cur)) cur = 0;
                  const next = cur + 1;
                  if (next < list.length) {
                    imgEl.setAttribute('data-cursor', String(next));
                    imgEl.src = list[next];
                  }
                } catch(_) {}
              });
            });
          } catch(_) {}
        } catch(_) { recsEl.innerHTML = '<div class="text-sm text-gray-500">Unable to render.</div>'; }
      }
      function getProfileFromControls(){
        const cat = (document.getElementById('cartSimPrefCategory')||{}).value || '';
        const budget = (document.getElementById('cartSimBudget')||{}).value || '';
        const intent = (document.getElementById('cartSimIntent')||{}).value || '';
        const device = (document.getElementById('cartSimDevice')||{}).value || '';
        const region = (document.getElementById('cartSimRegion')||{}).value || '';
        const p = {}; if (cat) p.preferredCategory = cat; if (budget) p.budget = budget; if (intent) p.intent = intent; if (device) p.device = device; if (region) p.region = region; return p;
      }

      // Expose init so other blocks can call it after opening modal
      window.__wfInitCartSimUI = async function(){
        try {
          const sel = document.getElementById('cartSimPrefCategory');
          if (sel && !sel.__wfFilled) {
            const j = await (window.WhimsicalFrog && WhimsicalFrog.api ? WhimsicalFrog.api.get('/api/cart_upsell_metadata.php') : ApiClient.get('/api/cart_upsell_metadata.php'));
            const d = (j && j.data) ? j.data : j; const cats = Array.isArray(d && d.categories) ? d.categories : [];
            const frag = document.createDocumentFragment(); const first = document.createElement('option'); first.value=''; first.textContent='Auto'; frag.appendChild(first);
            cats.forEach((c)=>{ const opt = document.createElement('option'); opt.value = c; opt.textContent = c; frag.appendChild(opt); });
            sel.innerHTML = ''; sel.appendChild(frag); sel.__wfFilled = true;
          }
          // Bind direct click listeners as a safety net
          const r = document.getElementById('cartSimulationRefreshBtn');
          if (r && !r.__wfBound) { r.__wfBound = true; r.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); document.dispatchEvent(new CustomEvent('wf:cart-sim-refresh', { bubbles: true })); }); }
          const h = document.querySelector('[data-action="load-cart-sim-history"]');
          if (h && !h.__wfBound) { h.__wfBound = true; h.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); document.dispatchEvent(new CustomEvent('wf:cart-sim-history', { bubbles: true })); }); }
        } catch(_) { }
      };

      // Populate categories when Shopping Cart modal opens
      document.addEventListener('click', function(ev){
        const t = ev.target && ev.target.closest ? ev.target.closest('[data-action="open-shopping-cart"]') : null; if (!t) return;
        setTimeout(()=>{ try { window.__wfInitCartSimUI(); } catch(_) {} }, 0);
      }, true);

      // Delegated: Refresh recommendations
      document.addEventListener('click', async function(ev){
        const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action="refresh-cart-simulation"]') : null; if (!btn) return;
        ev.preventDefault(); ev.stopPropagation();
        try {
          setStatus('Generating shopper and recommendations…', true);
          const profile = getProfileFromControls();
          const body = { limit: 4 }; if (Object.keys(profile).length) body.profile = profile;
          const j = await (window.WhimsicalFrog && WhimsicalFrog.api ? WhimsicalFrog.api.post('/api/cart_upsell_simulation.php', body) : ApiClient.post('/api/cart_upsell_simulation.php', body));
          const d = (j && j.data) ? j.data : j;
          renderProfile(d && d.profile ? d.profile : {});
          renderSeed(d && d.cart_skus ? d.cart_skus : []);
          renderRecs(d && d.recommendations ? d.recommendations : [], d && d.rationales ? d.rationales : {});
          setStatus('Recommendations updated' + (d && d.id ? ` (Simulation #${d.id})` : ''), true);
          // Reveal history box if present (but do not load now)
          const hb = document.getElementById('cartSimulationHistoryBox'); if (hb) hb.classList.add('hidden');
        } catch(_) { setStatus('Error generating recommendations', false); }
      }, true);

      // Support custom events from direct bindings
      document.addEventListener('wf:cart-sim-refresh', async function(){
        try {
          setStatus('Generating shopper and recommendations…', true);
          const profile = getProfileFromControls();
          const body = { limit: 4 }; if (Object.keys(profile).length) body.profile = profile;
          const j = await (window.WhimsicalFrog && WhimsicalFrog.api ? WhimsicalFrog.api.post('/api/cart_upsell_simulation.php', body) : ApiClient.post('/api/cart_upsell_simulation.php', body));
          const d = (j && j.data) ? j.data : j;
          renderProfile(d && d.profile ? d.profile : {}); renderSeed(d && d.cart_skus ? d.cart_skus : []);
          renderRecs(d && d.recommendations ? d.recommendations : [], d && d.rationales ? d.rationales : {});
          setStatus('Recommendations updated' + (d && d.id ? ` (Simulation #${d.id})` : ''), true);
        } catch(_) { setStatus('Error generating recommendations', false); }
      });

      // Delegated: Load history
      document.addEventListener('click', async function(ev){
        const btn = ev.target && ev.target.closest ? ev.target.closest('[data-action="load-cart-sim-history"]') : null; if (!btn) return;
        ev.preventDefault(); ev.stopPropagation();
        try {
          const hb = document.getElementById('cartSimulationHistoryBox'); const list = document.getElementById('cartSimulationHistory'); if (!hb || !list) return;
          hb.classList.remove('hidden'); list.innerHTML = '<div class="text-sm text-gray-500">Loading…</div>';
          const j = await (window.WhimsicalFrog && WhimsicalFrog.api ? WhimsicalFrog.api.get('/api/cart_upsell_history.php', { limit: 20 }) : ApiClient.get('/api/cart_upsell_history.php', { limit: 20 }));
          const d = (j && j.data) ? j.data : j; const items = Array.isArray(d && d.items) ? d.items : [];
          if (!items.length) { list.innerHTML = '<div class="text-sm text-gray-500">No simulations yet.</div>'; return; }
          list.innerHTML = items.map((it)=>{
            const id = it.id; const ts = escapeHtml(it.created_at || ''); const prof = it.profile || {}; const cat = prof.preferredCategory || '—';
            const first = Array.isArray(it.recommendations) && it.recommendations[0] ? it.recommendations[0] : null;
            const firstName = first ? escapeHtml(first.name || first.sku || '') : '—';
            return `<div class="rounded border p-2 text-sm flex items-center justify-between gap-3">
              <div>
                <div class="font-medium">Simulation #${id}</div>
                <div class="text-xs text-gray-500">${ts} · Pref Cat: ${escapeHtml(cat)}</div>
              </div>
              <div class="text-xs text-gray-600">Top Rec: ${firstName}</div>
            </div>`;
          }).join('');
        } catch(_) { /* ignore */ }
      }, true);

      // History via custom event
      document.addEventListener('wf:cart-sim-history', async function(){
        try {
          const hb = document.getElementById('cartSimulationHistoryBox'); const list = document.getElementById('cartSimulationHistory'); if (!hb || !list) return;
          hb.classList.remove('hidden'); list.innerHTML = '<div class="text-sm text-gray-500">Loading…</div>';
          const j = await (window.WhimsicalFrog && WhimsicalFrog.api ? WhimsicalFrog.api.get('/api/cart_upsell_history.php', { limit: 20 }) : ApiClient.get('/api/cart_upsell_history.php', { limit: 20 }));
          const d = (j && j.data) ? j.data : j; const items = Array.isArray(d && d.items) ? d.items : [];
          if (!items.length) { list.innerHTML = '<div class="text-sm text-gray-500">No simulations yet.</div>'; return; }
          list.innerHTML = items.map((it)=>{
            const id = it.id; const ts = escapeHtml(it.created_at || ''); const prof = it.profile || {}; const cat = prof.preferredCategory || '—';
            const first = Array.isArray(it.recommendations) && it.recommendations[0] ? it.recommendations[0] : null; const firstName = first ? escapeHtml(first.name || first.sku || '') : '—';
            return `<div class="rounded border p-2 text-sm flex items-center justify-between gap-3"><div><div class="font-medium">Simulation #${id}</div><div class="text-xs text-gray-500">${ts} · Pref Cat: ${escapeHtml(cat)}</div></div><div class="text-xs text-gray-600">Top Rec: ${firstName}</div></div>`;
          }).join('');
        } catch(_) { }
      });

      // Final fallback: initialize on DOM ready
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ()=>{ try { window.__wfInitCartSimUI(); } catch(_) {} }, { once: true }); else { try { window.__wfInitCartSimUI(); } catch(_) {} }
    })();

  } catch (_) { /* noop root */ }
})();
