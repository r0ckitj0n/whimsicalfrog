// Admin Settings fallbacks (migrated from inline <script> blocks)
// - Modal close handlers (X button, delegated close, backdrop click, ESC)
// - Auto-size admin modals
// - Auto-resize same-origin iframes inside modals + cross-iframe message sizing
// - Open Shopping Cart modal action
// - Save Cart Settings action

(function(){
  try {
    // Kill any lingering room overlay that could steal clicks
    const ro = document.getElementById('roomModalOverlay');
    if (ro) { ro.style.display = 'none'; ro.classList.remove('show'); ro.style.pointerEvents = 'none'; }

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
        iframe.style.height = Math.max(260, Math.min(h + 12, Math.floor(window.innerHeight * 0.85))) + 'px';
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
              f.style.height = Math.max(160, Math.min(d.height + 12, Math.floor(window.innerHeight * 0.85))) + 'px';
              const p = f.closest('.admin-modal.admin-modal-content');
              if (p && !p.classList.contains('wf-modal-auto')) p.classList.add('wf-modal-auto');
            }
          }
        }
      } catch(_) {}
    });

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
          scm.style.removeProperty('z-index');
          scm.classList.remove('hidden');
          scm.classList.add('show');
          scm.setAttribute('aria-hidden','false');
          scm.style.pointerEvents = 'auto';
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
          const origin = (window.__WF_BACKEND_ORIGIN && typeof window.__WF_BACKEND_ORIGIN==='string') ? window.__WF_BACKEND_ORIGIN : window.location.origin;
          const res = await fetch(origin.replace(/\/$/, '') + '/api/business_settings.php?action=upsert_settings', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), credentials: 'include'
          });
          if (res.ok) {
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

  } catch (_) { /* noop root */ }
})();
