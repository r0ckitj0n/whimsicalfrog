// Vite entry: app.js
// Import global CSS so Vite serves and bundles styles in dev and prod
import "../styles/main.css";
import "../styles/site-base.css";
import "../styles/admin-modals.css";
import { ApiClient } from "../core/api-client.js";
import { normalizeAssetUrl, attachStrictImageGuards, removeBrokenImage } from "../core/asset-utils.js";
// Global item popup (must load before delegated listeners attach)
// Install global popup and delegated handlers conditionally (diagnostics can disable)
// Moved to conditional dynamic import below to support minimal gating and diag flags
// Ensure storefront modals are available on all pages (for automated smoke tests and global shortcuts)

// Minimal mode gating for app-wide features via query params
const __wfQS = (() => { try { return new URLSearchParams(window.location.search || ''); } catch(_) { return new URLSearchParams(''); } })();
const __wfAppMinimal = (() => { try { return __wfQS.get('wf_app_minimal') === '1'; } catch(_) { return false; } })();
const __wfAllow = (name) => { try { return __wfQS.get(`wf_enable_${name}`) === '1'; } catch(_) { return false; } };
const __wfNoRouter = (() => { try { return __wfQS.get('wf_diag_no_router') === '1'; } catch(_) { return false; } })();
const __wfForceRoomModal = (() => { try { return __wfQS.get('wf_diag_force_roommodal') === '1'; } catch(_) { return false; } })();
const __wfForcePage = (() => { try { return (__wfQS.get('wf_diag_force_page') || '').toLowerCase(); } catch(_) { return ''; } })();
const __wfIsAdmin = (() => { try { const dp = (document.body && (document.body.getAttribute('data-page')||'')) || ''; return dp.startsWith('admin') || (location.pathname||'').startsWith('/admin'); } catch(_) { return false; } })();
const __wfIsAdminSettings = (() => {
  try {
    const path = (location.pathname||'').toLowerCase();
    const search = (location.search||'').toLowerCase();
    const byParam = /(^|[?&])section=settings\b/.test(search);
    const byPath = /(^|\/)admin\/settings(\/|$)/.test(path) || /admin_settings|admin-settings/.test(path);
    const byDom = !!document.querySelector('.settings-page');
    return (__wfIsAdmin && (byParam || byPath || byDom));
  } catch(_) { return false; }
})();
const __wfDiagNoPopup = (() => { try { return __wfQS.get('wf_diag_no_global_popup') === '1'; } catch(_) { return false; } })();
const __wfDiagNoDelegated = (() => { try { return __wfQS.get('wf_diag_no_delegated') === '1'; } catch(_) { return false; } })();
const __wfDiagNoAutosize = (() => { try { return __wfQS.get('wf_diag_no_autosize') === '1'; } catch(_) { return false; } })();
const __wfDiagNoAccount = (() => { try { return __wfQS.get('wf_diag_no_account_settings') === '1'; } catch(_) { return false; } })();
const __wfDiagNoAdminStd = (() => { try { return __wfQS.get('wf_diag_no_admin_std') === '1'; } catch(_) { return false; } })();
const __wfDiagNoReceipt = (() => { try { return __wfQS.get('wf_diag_no_receipt_modal') === '1'; } catch(_) { return false; } })();
const __wfDiagBlockClicks = (() => { try { return __wfQS.get('wf_diag_block_clicks') === '1'; } catch(_) { return false; } })();

// Optional global click blocker (diagnostics)
if (__wfDiagBlockClicks) {
  try {
    const __wfClickBlocker = (e) => { try { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); } catch(_) {} };
    document.addEventListener('click', __wfClickBlocker, true);
  } catch(_) {}
}

// Conditionally load event-manager (delegated hover/click for icons) - public pages only
if (!__wfIsAdmin && (!__wfAppMinimal || __wfAllow('events'))) {
  try { await import('../room/event-manager.js'); } catch(_) {}
}

// Conditionally load global popup on all pages (disable via wf_diag_no_global_popup=1)
if (!__wfDiagNoPopup) {
  try { await import('../ui/global-popup.js'); } catch(_) {}
}

// Conditionally load delegated handlers (load on all admin pages by default; disable via wf_diag_no_delegated=1)
if (!__wfDiagNoDelegated && __wfIsAdmin) {
  try { await import('../modules/delegated-handlers.js'); } catch(_) {}
}

// Conditionally load admin button standardizer (disable via wf_diag_no_admin_std=1)
if (!__wfDiagNoAdminStd && __wfIsAdmin) {
  try { await import('../modules/admin-button-standardizer.js'); } catch(_) {}
}

// Conditionally load account settings modal controller (disable via wf_diag_no_account_settings=1)
if (!__wfDiagNoAccount) {
  try {
    const hasOpener = !!document.querySelector('[data-action="open-account-settings"], #accountSettingsBtn, #accountSettingsModalTemplate');
    if (__wfIsAdmin || hasOpener) {
      await import('../js/account-settings-modal.js');
    }
  } catch(_) {}
}

// Conditionally load receipt modal helper (disable via wf_diag_no_receipt_modal=1)
if (!__wfDiagNoReceipt) {
  try { await import('../js/receipt-modal.js'); } catch(_) {}
}

// Icons are loaded at top-level

// Diagnostics: Allow forcing specific modules even when router is disabled
if (__wfForceRoomModal) {
  try {
    const { default: RoomModalManager } = await import(
      "../modules/room-modal-manager.js"
    );
    if (!window.__roomModalManager) {
      window.__roomModalManager = new RoomModalManager();
    }
  } catch(_) {}
}

if (__wfForcePage) {
  try {
    if (__wfForcePage === 'room_main') {
      await import('../js/room-main.js');
    } else if (__wfForcePage === 'shop') {
      await import('../js/shop.js');
    }
  } catch(_) {}
}

// Ensure admin modal autosizing is available on all admin routes by default; disable via wf_diag_no_autosize=1
const __wfAllowAutosizeSettings = (() => { try { return __wfQS.get('wf_enable_autosize_settings') === '1'; } catch(_) { return false; } })();
if (__wfIsAdmin && ((!__wfAppMinimal || __wfAllow('embed')) && !__wfDiagNoAutosize)) {
  try {
    const mod = await import('../modules/embed-autosize-parent.js');
    try { mod.initEmbedAutosizeParent && mod.initEmbedAutosizeParent(); } catch(_) {}
    try { mod.initOverlayAutoWire && mod.initOverlayAutoWire(); } catch(_) {}
  } catch (_) {}
}

// Ensure Settings bridge features (fallback showModal, wiring helpers) are present on Admin Settings
if (__wfIsAdminSettings) {
  try { await import('../js/admin-settings-bridge.js'); } catch(_) {}
  try { await import('../modules/attributes-inline-loader.js'); } catch(_) {}
}

// If this page is rendered inside an admin modal iframe, enable intrinsic sizing and child autosize emitter
try {
  const isEmbed = (document.body && document.body.getAttribute('data-embed') === '1');
  if (isEmbed && (!__wfAppMinimal || __wfAllow('embed'))) {
    await import('../styles/embed-iframe.css');
    await import('../modules/embed-autosize-child.js');
  }
} catch (_) {}

// Fallback CSS neutralizer in case legacy bundle overrides coordinates or pointer-events
try {
  const __wfFallbackStyle = document.createElement("style");
  __wfFallbackStyle.id = "wf-fallback-globalpopup-style";
  __wfFallbackStyle.innerHTML = `
    .item-popup{pointer-events:auto !important;}
  `;
  document.head.appendChild(__wfFallbackStyle);
} catch (_) {}

 

// Safety: briefly ensure positioned popup is visible if hidden due to races
// Run only when explicitly enabled via wf_enable_popup_fallback=1 and not on Admin Settings
if (__wfAllow('popup_fallback') && !__wfIsAdminSettings) {
  try {
    (function(){
      let tries = 0;
      const t = setInterval(() => {
        tries++;
        const p = document.getElementById("itemPopup");
        if (p && p.dataset && p.dataset.wfGpPosClass) {
          if (!p.classList.contains("visible")) {
            try { p.classList.remove("hidden", "measuring"); } catch(_) {}
            try { p.classList.add("visible"); } catch(_) {}
            try { p.setAttribute("aria-hidden", "false"); } catch(_) {}
          }
        }
        if (tries > 160) clearInterval(t);
      }, 50);
    })();
  } catch (_) {}
}

// Page router: conditionally load modules for each page
(async () => {
  try {
    const page = document.body?.getAttribute("data-page") || "";
    const path = (location.pathname || "").toLowerCase();
    if (__wfNoRouter) { try { console.log('[Vite] router disabled by wf_diag_no_router'); } catch(_) {} return; }

    // Always-on core utilities (safe to load everywhere)
    try {
      await import("../core/image-error-handler.js");
    } catch (_) {}
    try {
      await import("../core/ui-helpers.js");
    } catch (_) {}
    try {
      const isRoomMain = !!(document.getElementById("mainRoomPage") || /(^|\/)room_main(\.php)?$/i.test(path));
      if (isRoomMain) { await import("../js/room-main.js"); }
    } catch (_) {}
    // Global item popup already imported at top-level

    // Fallback: enforce popup persistence when modern popup is not available
    (function installPopupFallbackPersistence() {
      const ENABLE_POPUP_FALLBACK = false; // keep code for reference, disabled by default
      // Skip entirely when flag disabled or modern popup is present
      if (!ENABLE_POPUP_FALLBACK || window.__WF_MODERN_POPUP) return;
      if (window.__WF_POPUP_FALLBACK_INSTALLED) return;
      window.__WF_POPUP_FALLBACK_INSTALLED = true;
      const log = (...args) => {
        try {
          console.log("[ViteEntry/Fallback]", ...args);
        } catch (_) {}
      };

      // Handlers are defined here so we can uninstall later if needed
      const cancel = () => {
        try {
          window.cancelHideGlobalPopup && window.cancelHideGlobalPopup();
        } catch (_) {}
      };
      const schedule = () => {
        try {
          window.scheduleHideGlobalPopup && window.scheduleHideGlobalPopup(500);
        } catch (_) {}
      };
      const delayHide = () => {
        try {
          window.scheduleHideGlobalPopup && window.scheduleHideGlobalPopup(500);
        } catch (_) {}
      };

      const bindOnce = () => {
        const popup =
          document.getElementById("itemPopup") ||
          document.querySelector(".item-popup");
        if (!popup) return false;
        if (popup.__wfFallbackBound) return true;
        popup.__wfFallbackBound = true;
        popup.addEventListener("mouseenter", cancel);
        popup.addEventListener("mouseleave", schedule);
        // Also mark positioned in case the runtime relies on it to neutralize legacy absolute CSS
        try {
          popup.classList.add("positioned");
        } catch (_) {}
        log("Popup fallback persistence bound");
        return true;
      };

      const bindIframeBoundaries = () => {
        document
          .querySelectorAll(
            '.room-modal-overlay iframe, iframe[data-room], .room-modal-overlay [data-role="room-frame"]',
          )
          .forEach((ifr) => {
            if (ifr.__wfPopupBoundaryFallback) return;
            ifr.addEventListener("mouseover", cancel);
            ifr.addEventListener("mouseout", delayHide);
            ifr.__wfPopupBoundaryFallback = true;
            log("Iframe boundary fallback bound", ifr);
          });
      };

      const uninstall = () => {
        try {
          const popup =
            document.getElementById("itemPopup") ||
            document.querySelector(".item-popup");
          if (popup && popup.__wfFallbackBound) {
            popup.removeEventListener("mouseenter", cancel);
            popup.removeEventListener("mouseleave", schedule);
            popup.__wfFallbackBound = false;
            log("Popup fallback persistence uninstalled");
          }
          document
            .querySelectorAll(
              '.room-modal-overlay iframe, iframe[data-room], .room-modal-overlay [data-role="room-frame"]',
            )
            .forEach((ifr) => {
              if (ifr.__wfPopupBoundaryFallback) {
                ifr.removeEventListener("mouseover", cancel);
                ifr.removeEventListener("mouseout", delayHide);
                ifr.__wfPopupBoundaryFallback = false;
              }
            });
        } catch (_) {}
      };

      // If modern popup becomes ready later, uninstall fallback
      try {
        window.addEventListener("wf:modern-popup-ready", uninstall, {
          once: true,
        });
      } catch (_) {}

    // Policy modal: intercept privacy/terms/policy links and open in a brand-styled modal
    try {
      (function installPolicyModal(){
        if (__wfAppMinimal && !__wfAllow('policy')) return;
        if (window.__WF_POLICY_MODAL_INSTALLED) return; window.__WF_POLICY_MODAL_INSTALLED = true;
        const STYLE_ID = 'wf-policy-modal-styles';
        function injectStyles(){
          if (document.getElementById(STYLE_ID)) return;
          const css = `
            #wfPolicyModalOverlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease;z-index:11050}
            #wfPolicyModalOverlay.show{opacity:1;visibility:visible}
            #wfPolicyModal{background:linear-gradient(135deg, var(--brand-primary, #87ac3a), var(--brand-secondary, #BF5700));color:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);width:min(96vw,1100px);max-width:min(96vw,1100px);max-height:92vh;display:flex;flex-direction:column;overflow:hidden}
            #wfPolicyModal .policy-modal-header{background:transparent;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:10px 14px;font-weight:700}
            #wfPolicyModal .policy-modal-title{margin:0;font-size:1.1rem}
            #wfPolicyModal .policy-modal-close{background:none;border:0;color:#fff;font-size:22px;cursor:pointer}
            #wfPolicyModal .policy-modal-body{padding:16px;height:auto;max-height:calc(92vh - 46px);overflow:auto}
            #wfPolicyModalContent{line-height:1.6;color:#fff}
            #wfPolicyModalContent .policy-panel{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.18);box-shadow:0 10px 30px rgba(0,0,0,.15) inset, 0 10px 30px rgba(0,0,0,.15);border-radius:12px;padding:18px}
            #wfPolicyModalContent .policy-panel.loading{opacity:.85}
            #wfPolicyModalContent h1,#wfPolicyModalContent h2,#wfPolicyModalContent h3,#wfPolicyModalContent h4,#wfPolicyModalContent h5,#wfPolicyModalContent h6{color:#fff;margin:0 0 .5rem}
            #wfPolicyModalContent p{margin:.5rem 0}
            #wfPolicyModalContent a{color:#fff;text-decoration:underline}
            #wfPolicyModalContent ul{margin:.5rem 0 0 1rem}
            #wfPolicyModalContent .wf-cloud-title{color:#fff}
          `;
          const style = document.createElement('style'); style.id = STYLE_ID; style.textContent = css; (document.head||document.documentElement).appendChild(style);
        }
        function ensureModal(){
          let overlay = document.getElementById('wfPolicyModalOverlay');
          if (overlay) return overlay;
          overlay = document.createElement('div'); overlay.id='wfPolicyModalOverlay'; overlay.className = 'overlay'; overlay.setAttribute('role','dialog'); overlay.setAttribute('aria-hidden','true');
          const modal = document.createElement('div'); modal.id='wfPolicyModal'; modal.setAttribute('role','dialog'); modal.setAttribute('aria-modal','true'); modal.setAttribute('aria-label','Policy');
          const header = document.createElement('div'); header.className='policy-modal-header';
          const title = document.createElement('h3'); title.className='policy-modal-title'; title.textContent='Policy';
          const close = document.createElement('button'); close.className='policy-modal-close'; close.type='button'; close.setAttribute('aria-label','Close'); close.textContent='×';
          header.appendChild(title); header.appendChild(close);
          const body = document.createElement('div'); body.className='policy-modal-body';
          const content = document.createElement('div'); content.id='wfPolicyModalContent'; body.appendChild(content);
          modal.appendChild(header); modal.appendChild(body);
          overlay.appendChild(modal);
          document.body.appendChild(overlay);
          function getFocusables(){
            return overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
          }
          function trap(e){
            if (e.key !== 'Tab') return;
            const focusables = Array.from(getFocusables()).filter(el=>!el.hasAttribute('disabled') && el.offsetParent !== null);
            if (!focusables.length) return;
            const first = focusables[0];
            const last = focusables[focusables.length-1];
            if (e.shiftKey){
              if (document.activeElement === first){ e.preventDefault(); last.focus(); }
            } else {
              if (document.activeElement === last){ e.preventDefault(); first.focus(); }
            }
          }
          function hide(){ overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); try{ window.WFModals&&WFModals.unlockScrollIfNoneOpen&&WFModals.unlockScrollIfNoneOpen(); }catch(_){} document.removeEventListener('keydown', trap, true); try{ overlay.__wfLastFocus && overlay.__wfLastFocus.focus && overlay.__wfLastFocus.focus(); } catch(_){} }
          close.addEventListener('click', hide);
          overlay.addEventListener('click', (e)=>{ if (e.target===overlay) hide(); });
          document.addEventListener('keydown', (e)=>{ if (e.key==='Escape' && overlay.classList.contains('show')) hide(); });
          overlay.__wfPolicyHide = hide;
          overlay.__wfPolicyTrap = trap;
          overlay.__wfPolicyRememberFocus = ()=>{ try{ overlay.__wfLastFocus = document.activeElement; }catch(_){} };
          overlay.__wfPolicyRestoreFocus = ()=>{ try{ overlay.__wfLastFocus && overlay.__wfLastFocus.focus && overlay.__wfLastFocus.focus(); }catch(_){} };
          return overlay;
        }
        function openPolicy(url,label){
          injectStyles();
          const overlay = ensureModal();
          const t = overlay.querySelector('.policy-modal-title'); if (t) t.textContent = label || 'Policy';
          const content = overlay.querySelector('#wfPolicyModalContent');
          try{ window.WFModals&&WFModals.lockScroll&&WFModals.lockScroll(); }catch(_){}
          overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false');
          if (content) content.innerHTML = '<div class="policy-panel loading">Loading…</div>';
          // Remember focus and start trap
          if (overlay.__wfPolicyRememberFocus) overlay.__wfPolicyRememberFocus();
          document.addEventListener('keydown', overlay.__wfPolicyTrap, true);
          // Focus the close button
          try { (overlay.querySelector('.policy-modal-close')||overlay).focus(); } catch(_) {}
          const target = url + (url.indexOf('?')>-1?'&':'?') + 'modal=1';
          ApiClient.request(target, { method: 'GET' })
            .then(html=>{
              try {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const contentNode = doc.querySelector('.wf-cloud-card .content') || doc.querySelector('.page-content .wf-cloud-card .content') || doc.querySelector('.page-content') || doc.body;
                const inner = contentNode ? contentNode.innerHTML : html;
                if (content) content.innerHTML = '<div class="policy-panel">'+ inner +'</div>';
              } catch(_) { if (content) content.innerHTML = html; }
            })
            .catch(()=>{ if (content) content.textContent = 'Failed to load.'; });
        }
        window.openPolicyModal = openPolicy;
        document.addEventListener('click', (e)=>{
          try {
            if (e.defaultPrevented) return;
            // Ignore modified clicks
            if (e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0) return;
            const a = e.target && e.target.closest && e.target.closest('a[href]');
            if (!a) return;
            // Avoid triggering inside the modal itself
            if (a.closest && a.closest('#wfPolicyModalOverlay')) return;
            const href = (a.getAttribute('href')||'').toLowerCase();
            if (!href) return;
            // Match internal policy pages or explicit data-open-policy triggers
            const isPolicyLink = (a.hasAttribute && a.hasAttribute('data-open-policy')) || /(\/privacy(\.php)?(\?|$)|\/terms(\.php)?(\?|$)|\/policy(\.php)?(\?|$))/i.test(href);
            if (isPolicyLink){
              e.preventDefault();
              const label = (a.textContent||'').trim() || 'Policy';
              try { openPolicy(href, label); } catch(_) { try{ window.location.href = href; } catch(__){} }
            }
          } catch(_) {}
        }, true);
      })();
    } catch (_) {}

    // Ensure policy modal is installed even if fallback IIFE exits early
    try {
      (function ensurePolicyModalInstalled(){
        if (__wfAppMinimal && !__wfAllow('policy')) return;
        if (window.__WF_POLICY_MODAL_INSTALLED) return; // already installed by earlier block
        const STYLE_ID = 'wf-policy-modal-styles';
        function injectStyles(){
          if (document.getElementById(STYLE_ID)) return;
          const css = `
            #wfPolicyModalOverlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease;z-index:var(--z-overlay-topmost, var(--z-index-cart-overlay, 10080))}
            #wfPolicyModalOverlay.show{opacity:1;visibility:visible}
            #wfPolicyModal{background:linear-gradient(135deg, var(--brand-primary, #87ac3a), var(--brand-secondary, #BF5700));color:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);width:min(96vw,1100px);max-width:min(96vw,1100px);max-height:92vh;display:flex;flex-direction:column;overflow:hidden}
            #wfPolicyModal .policy-modal-header{background:transparent;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:10px 14px;font-weight:700}
            #wfPolicyModal .policy-modal-title{margin:0;font-size:1.1rem}
            #wfPolicyModal .policy-modal-close{background:none;border:0;color:#fff;font-size:22px;cursor:pointer}
            #wfPolicyModal .policy-modal-body{padding:16px;height:auto;max-height:calc(92vh - 46px);overflow:auto}
            #wfPolicyModalContent{line-height:1.6;color:#fff}
            #wfPolicyModalContent .policy-panel{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.18);box-shadow:0 10px 30px rgba(0,0,0,.15) inset, 0 10px 30px rgba(0,0,0,.15);border-radius:12px;padding:18px}
            #wfPolicyModalContent h1,#wfPolicyModalContent h2,#wfPolicyModalContent h3,#wfPolicyModalContent h4,#wfPolicyModalContent h5,#wfPolicyModalContent h6{color:#fff;margin:0 0 .5rem}
            #wfPolicyModalContent p{margin:.5rem 0}
            #wfPolicyModalContent a{color:#fff;text-decoration:underline}
            #wfPolicyModalContent ul{margin:.5rem 0 0 1rem}
            #wfPolicyModalContent .wf-cloud-title{color:#fff}
          `;
          const style = document.createElement('style'); style.id = STYLE_ID; style.textContent = css; (document.head||document.documentElement).appendChild(style);
        }
        function ensureModal(){
          let overlay = document.getElementById('wfPolicyModalOverlay');
          if (overlay) return overlay;
          overlay = document.createElement('div'); overlay.id='wfPolicyModalOverlay';
          const modal = document.createElement('div'); modal.id='wfPolicyModal'; modal.setAttribute('role','dialog'); modal.setAttribute('aria-modal','true'); modal.setAttribute('aria-label','Policy');
          const header = document.createElement('div'); header.className='policy-modal-header';
          const title = document.createElement('h3'); title.className='policy-modal-title'; title.textContent='Policy';
          const close = document.createElement('button'); close.className='policy-modal-close'; close.type='button'; close.setAttribute('aria-label','Close'); close.textContent='×';
          header.appendChild(title); header.appendChild(close);
          const body = document.createElement('div'); body.className='policy-modal-body';
          const content = document.createElement('div'); content.id='wfPolicyModalContent'; body.appendChild(content);
          modal.appendChild(header); modal.appendChild(body);
          overlay.appendChild(modal);
          document.body.appendChild(overlay);
          function getFocusables(){ return overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'); }
          function trap(e){ if (e.key !== 'Tab') return; const focusables = Array.from(getFocusables()).filter(el=>!el.hasAttribute('disabled') && el.offsetParent !== null); if (!focusables.length) return; const first = focusables[0]; const last = focusables[focusables.length-1]; if (e.shiftKey){ if (document.activeElement === first){ e.preventDefault(); last.focus(); } } else { if (document.activeElement === last){ e.preventDefault(); first.focus(); } } }
          function hide(){ overlay.classList.remove('show'); try{ window.WFModals&&WFModals.unlockScrollIfNoneOpen&&WFModals.unlockScrollIfNoneOpen(); }catch(_){} document.removeEventListener('keydown', trap, true); try{ overlay.__wfLastFocus && overlay.__wfLastFocus.focus && overlay.__wfLastFocus.focus(); } catch(_){} }
          close.addEventListener('click', hide);
          overlay.addEventListener('click', (e)=>{ if (e.target===overlay) hide(); });
          document.addEventListener('keydown', (e)=>{ if (e.key==='Escape' && overlay.classList.contains('show')) hide(); });
          overlay.__wfPolicyHide = hide;
          overlay.__wfPolicyTrap = trap;
          overlay.__wfPolicyRememberFocus = ()=>{ try{ overlay.__wfLastFocus = document.activeElement; }catch(_){} };
          return overlay;
        }
        function openPolicy(url,label){
          injectStyles();
          const overlay = ensureModal();
          const t = overlay.querySelector('.policy-modal-title'); if (t) t.textContent = label || 'Policy';
          const content = overlay.querySelector('#wfPolicyModalContent');
          try{ window.WFModals&&WFModals.lockScroll&&WFModals.lockScroll(); }catch(_){}
          overlay.classList.add('show');
          if (content) content.innerHTML = '<div class="policy-panel loading">Loading…</div>';
          if (overlay.__wfPolicyRememberFocus) overlay.__wfPolicyRememberFocus();
          document.addEventListener('keydown', overlay.__wfPolicyTrap, true);
          try { (overlay.querySelector('.policy-modal-close')||overlay).focus(); } catch(_) {}
          const target = url + (url.indexOf('?')>-1?'&':'?') + 'modal=1';
          ApiClient.request(target, { method: 'GET' })
            .then(html=>{
              try {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const contentNode = doc.querySelector('.wf-cloud-card .content') || doc.querySelector('.page-content .wf-cloud-card .content') || doc.querySelector('.page-content') || doc.body;
                const inner = contentNode ? contentNode.innerHTML : html;
                if (content) content.innerHTML = '<div class="policy-panel">'+ inner +'</div>';
              } catch(_) { if (content) content.innerHTML = html; }
            })
            .catch(()=>{ if (content) content.textContent = 'Failed to load.'; });
        }
        window.openPolicyModal = openPolicy;
        document.addEventListener('click', (e)=>{
          try {
            if (e.defaultPrevented) return;
            if (e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0) return;
            const a = e.target && e.target.closest && e.target.closest('a[href]');
            if (!a) return;
            if (a.closest && a.closest('#wfPolicyModalOverlay')) return;
            const href = (a.getAttribute('href')||'').toLowerCase();
            if (!href) return;
            const isPolicyLink = (a.hasAttribute && a.hasAttribute('data-open-policy')) || /(\/privacy(\.php)?(\?|$)|\/terms(\.php)?(\?|$)|\/policy(\.php)?(\?|$))/i.test(href);
            if (isPolicyLink){
              e.preventDefault();
              const label = (a.textContent||'').trim() || 'Policy';
              openPolicy(href, label);
            }
          } catch(_) {}
        }, true);
      })();
    } catch (_) {}

      // Try immediately, then on DOM ready, then periodically for dynamically inserted DOM
      const tryBind = () => {
        const ok = bindOnce();
        bindIframeBoundaries();
        return ok;
      };
      if (!tryBind()) {
        document.addEventListener("DOMContentLoaded", tryBind, { once: true });
        window.addEventListener("load", tryBind, { once: true });
        // Poll briefly to catch late-inserted popups/iframes during modal open
        let tries = 0;
        const maxTries = 40; // ~4s total
        const t = setInterval(() => {
          if (tryBind() || ++tries >= maxTries) clearInterval(t);
        }, 100);
      }
    })();
    // Global item details modal (opened from popup or product icon clicks)
    if (!__wfAppMinimal || __wfAllow('item_modal')) {
      try { await import("../js/detailed-item-modal.js"); } catch (_) {}
    }

    // Ensure payment modal is available so Checkout opens a modal not a page
    if (!__wfAppMinimal || __wfAllow('payment_modal')) {
      try { await import("../js/payment-modal.js"); } catch (_) {}
    }

    // Ensure cart modal is available on all pages (header cart button should open a modal)
    if (!__wfAppMinimal || __wfAllow('cart_modal')) {
      try { await import("../js/cart-modal.js"); } catch (_) {}
    }

    // Ensure cart system and WF_Cart adapter are available on ALL pages (room_main needs it)
    try {
      if (!window.WF_Cart && (!__wfAppMinimal || __wfAllow('cart'))) {
        const mod = await import("../commerce/cart-system.js");
        const cart = mod?.cart || window.cart;
        if (cart && !window.WF_Cart) {
          window.__WF_STRICT_NO_FALLBACKS = true;
          const currency = (v) => `$${(Number(v) || 0).toFixed(2)}`;
          const updateUI = () => {
            try {
              const count = cart.getCount ? cart.getCount() : 0;
              const total = cart.getTotal ? cart.getTotal() : 0;
              document
                .querySelectorAll(".cart-count, .cart-counter, #cart-count")
                .forEach((el) => {
                  try {
                    el.textContent = String(count);
                    el.classList.toggle("hidden", count === 0);
                  } catch (_) {}
                });
              document.querySelectorAll("#cartCount").forEach((el) => {
                try {
                  el.textContent = `${count} ${count === 1 ? "item" : "items"}`;
                } catch (_) {}
              });
              document.querySelectorAll("#cartTotal").forEach((el) => {
                try {
                  el.textContent = `$${Number(total).toFixed(2)}`;
                } catch (_) {}
              });
              try {
                window.dispatchEvent(
                  new CustomEvent("cartUpdated", {
                    detail: {
                      state: {
                        count,
                        total,
                        items: cart.getItems ? cart.getItems() : [],
                      },
                    },
                  }),
                );
              } catch (_) {}
            } catch (_) {}
          };
          const renderCartDOM = () => {
            try {
              const itemsEl = document.getElementById("cartModalItems");
              const footerEl = document.getElementById("cartModalFooter");
              if (!itemsEl) return;
              const items = cart.getItems ? cart.getItems() : [];
              const total = cart.getTotal ? cart.getTotal() : 0;
              if (!items.length) {
                itemsEl.innerHTML =
                  '<div class="p-6 text-center text-gray-600">Your cart is empty.</div>';
                if (footerEl)
                  footerEl.innerHTML = `
                  <div class="cart-footer-bar">
                    <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                    <a class="cart-checkout-btn is-disabled" aria-disabled="true">Checkout</a>
                  </div>
                `;
                return;
              }
              const itemsHtml = items
                .map((item) => {
                  const lineTotal =
                    (Number(item.price) || 0) * (Number(item.quantity) || 0);
                  const src = normalizeAssetUrl(item.image);
                  const img = src
                    ? `<img src="${src}" alt="${item.name || item.sku || ""}" class="cart-item-image" />`
                    : "";
                  const optionBits = [];
                  if (item.optionGender) optionBits.push(item.optionGender);
                  if (item.optionSize) optionBits.push(item.optionSize);
                  if (item.optionColor) optionBits.push(item.optionColor);
                  const optionsHtml = optionBits.length
                    ? `<div class="cart-item-options text-sm text-gray-500">${optionBits.join(" • ")}</div>`
                    : "";
                  return `
                  <div class="cart-item" data-sku="${item.sku}">
                    ${img}
                    <div class="cart-item-details">
                      <div class="cart-item-title">${item.name || item.sku}</div>
                      ${optionsHtml}
                      <div class="cart-item-price">${currency(item.price)}</div>
                    </div>
                    <div class="cart-item-quantity">
                      <input type="number" min="0" class="cart-quantity-input" data-sku="${item.sku}" value="${item.quantity}" />
                    </div>
                    <div class="cart-item-remove remove-from-cart" data-sku="${item.sku}" aria-label="Remove item" title="Remove">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-trash" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
                      </svg>
                    </div>
                    <div class="cart-item-line-total">${currency(lineTotal)}</div>
                  </div>
                `;
                })
                .join("");
              // Ensure we capture image errors immediately on render
              try {
                attachStrictImageGuards(itemsEl, 'img.cart-item-image');
              } catch (_) {}

              itemsEl.innerHTML = itemsHtml;

              // Post-render sweep: remove already-failed images (error may have fired before listeners)
              try {
                itemsEl.querySelectorAll('img.cart-item-image').forEach((img) => {
                  if (img.complete && (!img.naturalWidth || img.naturalWidth === 0)) {
                    removeBrokenImage(img);
                  } else {
                    img.addEventListener('error', () => { removeBrokenImage(img); }, { once: true });
                  }
                });
              } catch (_) {}
              if (footerEl) {
                const disabledClass =
                  cart.getCount && cart.getCount() > 0 ? "" : "is-disabled";
                const checkoutHref =
                  cart.getCount && cart.getCount() > 0
                    ? ' href="/payment"'
                    : "";
                footerEl.innerHTML = `
                  <div class="cart-footer-bar">
                    <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                    <a class="cart-checkout-btn ${disabledClass}"${checkoutHref}>Checkout</a>
                  </div>
                `;
              }
            } catch (e) {
              console.warn("[ViteEntry] renderCartDOM failed", e);
            }
          };
          window.WF_Cart = {
            addItem: (item) => {
              try {
                console.log("[ViteEntry] WF_Cart.addItem()", {
                  sku: item?.sku,
                  qty: item?.quantity,
                });
                cart.add(item, item?.quantity || 1);
                updateUI();
                try {
                  renderCartDOM();
                } catch (_) {}
              } catch (e) {
                console.warn("[ViteEntry] WF_Cart.addItem adapter failed", e);
              }
            },
            removeItem: (sku) => {
              try {
                console.log("[ViteEntry] WF_Cart.removeItem()", { sku });
                cart.remove && cart.remove(sku);
                updateUI();
                renderCartDOM();
              } catch (e) {
                console.warn(
                  "[ViteEntry] WF_Cart.removeItem adapter failed",
                  e,
                );
              }
            },
            updateItem: (sku, qty) => {
              try {
                console.log("[ViteEntry] WF_Cart.updateItem()", { sku, qty });
                cart.updateQuantity && cart.updateQuantity(sku, qty);
                updateUI();
                renderCartDOM();
              } catch (e) {
                console.warn(
                  "[ViteEntry] WF_Cart.updateItem adapter failed",
                  e,
                );
              }
            },
            clearCart: () => {
              try {
                console.log("[ViteEntry] WF_Cart.clearCart()");
                cart.clear && cart.clear();
                updateUI();
                renderCartDOM();
              } catch (e) {
                console.warn("[ViteEntry] WF_Cart.clearCart adapter failed", e);
              }
            },
            getItems: () => {
              try {
                return cart.getItems ? cart.getItems() : [];
              } catch (_) {
                return [];
              }
            },
            getTotal: () => {
              try {
                return cart.getTotal ? cart.getTotal() : 0;
              } catch (_) {
                return 0;
              }
            },
            getCount: () => {
              try {
                return cart.getCount ? cart.getCount() : 0;
              } catch (_) {
                return 0;
              }
            },
            renderCart: () => {
              try {
                renderCartDOM();
              } catch (_) {}
            },
            refreshFromStorage: () => {
              try {
                cart.load && cart.load();
                cart.save && cart.save();
                updateUI();
                renderCartDOM();
                return {
                  items: cart.getItems?.() || [],
                  total: cart.getTotal?.() || 0,
                  count: cart.getCount?.() || 0,
                };
              } catch (_) {
                return { items: [], total: 0, count: 0 };
              }
            },
          };
          console.log(
            "[ViteEntry] WF_Cart adapter installed globally (bridged to commerce/cart-system)",
          );
          try {
            updateUI();
            renderCartDOM();
          } catch (_) {}
        }
      }
    } catch (_) {}

    // Always-on modal capture to prevent navigation and open modal for doors
    if (!__wfAppMinimal || __wfAllow('roommodal')) {
      try {
        if (!window.__roomModalManager) {
          window.__roomModalManager = { __wf_lazy: true };
        }
        const ensureMgr = async () => {
          if (window.__roomModalManager && !window.__roomModalManager.__wf_lazy) return window.__roomModalManager;
          const { default: RoomModalManager } = await import("../modules/room-modal-manager.js");
          const mgr = new RoomModalManager();
          window.__roomModalManager = mgr;
          try { document.removeEventListener('click', __wfDoorShim, true); } catch(_) {}
          return mgr;
        };
        const __wfDoorShim = async (e) => {
          try {
            const inOpenModal = !!(e.target.closest && e.target.closest('.room-modal-overlay.show'));
            if (inOpenModal) return;
            const doorLink = e.target.closest && e.target.closest('.room-door, .door-area, .door-link, [data-room-number], a[data-room]');
            if (!doorLink) return;
            e.preventDefault();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
            const roomNumber = doorLink.dataset?.room || (doorLink.href && doorLink.href.match(/room=(\d+)/)?.[1]) || doorLink.getAttribute('data-room-number');
            if (!roomNumber) return;
            const mgr = await ensureMgr();
            if (mgr && typeof mgr.openRoom === 'function') mgr.openRoom(roomNumber);
          } catch(_) {}
        };
        document.addEventListener('click', __wfDoorShim, true);
      } catch (_) {}
    }

    // Login page: wire standalone login form
    if (page === "login" || document.getElementById("loginPage")) {
      try {
        await import("../js/pages/login-page.js");
      } catch (_) {}
    }

    // Contact page: reveal company info modal
    if (
      page === "contact" ||
      document.getElementById("wf-reveal-company-btn")
    ) {
      try {
        await import("../js/reveal-company-modal.js");
      } catch (_) {}
    }

    // Landing page: background + coordinate-based elements (e.g., welcome sign)
    if (page === "landing" || document.getElementById("landingPage")) {
      try {
        await import("../core/dynamic-background.js");
      } catch (_) {}
      // Landing specific behaviors (welcome sign positioning, etc.)
      try {
        await import("../js/landing-page.js");
      } catch (_) {}
    }

    // Main room page: full-screen background and door coordinate mapping
    if (page === "room_main" || document.getElementById("mainRoomPage")) {
      try {
        await import("../js/room-main.js");
      } catch (_) {}
    }

    // (Removed element-conditional import; modal manager is now always loaded.)

    // Note: We intentionally avoid initializing RoomCoordinator here to prevent
    // scripted navigation. RoomModalManager handles door clicks and opens modals.

    // Shop page: cart + sales + shop interactions
    if (page === "shop" || document.getElementById("shopPage")) {
      // Load ESM cart singleton (exports `cart` and auto-exposes window.cart)
      try {
        const mod = await import("../commerce/cart-system.js");
        // Bridge to legacy WF_Cart API if missing
        try {
          if (!window.WF_Cart) {
            const cart = mod?.cart || window.cart;
            const currency = (v) => `$${(Number(v) || 0).toFixed(2)}`;
            const updateUI = () => {
              try {
                const count = cart.getCount ? cart.getCount() : 0;
                const total = cart.getTotal ? cart.getTotal() : 0;
                // Generic numeric badges
                document
                  .querySelectorAll(".cart-count, .cart-counter, #cart-count")
                  .forEach((el) => {
                    try {
                      el.textContent = String(count);
                      el.classList.toggle("hidden", count === 0);
                    } catch (_) {}
                  });
                // Header labels
                document.querySelectorAll("#cartCount").forEach((el) => {
                  try {
                    el.textContent = `${count} ${count === 1 ? "item" : "items"}`;
                  } catch (_) {}
                });
                document.querySelectorAll("#cartTotal").forEach((el) => {
                  try {
                    el.textContent = `$${Number(total).toFixed(2)}`;
                  } catch (_) {}
                });
                // Fire a DOM event for any listeners (cart modal, checkout page, etc.)
                try {
                  window.dispatchEvent(
                    new CustomEvent("cartUpdated", {
                      detail: {
                        state: {
                          count,
                          total,
                          items: cart.getItems ? cart.getItems() : [],
                        },
                      },
                    }),
                  );
                } catch (_) {}
              } catch (_) {}
            };
            const renderCartDOM = () => {
              try {
                const itemsEl = document.getElementById("cartModalItems");
                const footerEl = document.getElementById("cartModalFooter");
                if (!itemsEl) return;
                const items = cart.getItems ? cart.getItems() : [];
                const total = cart.getTotal ? cart.getTotal() : 0;
                if (!items.length) {
                  itemsEl.innerHTML =
                    '<div class="p-6 text-center text-gray-600">Your cart is empty.</div>';
                  if (footerEl)
                    footerEl.innerHTML = `
                    <div class="cart-footer-bar">
                      <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                      <a class="cart-checkout-btn is-disabled" aria-disabled="true">Checkout</a>
                    </div>
                  `;
                  return;
                }
                const itemsHtml = items
                  .map((item) => {
                    const lineTotal =
                      (Number(item.price) || 0) * (Number(item.quantity) || 0);
                    const img = item.image
                      ? `<img src="${item.image}" alt="${item.name || item.sku || ""}" class="cart-item-image"/>`
                      : "";
                    const optionBits = [];
                    if (item.optionGender) optionBits.push(item.optionGender);
                    if (item.optionSize) optionBits.push(item.optionSize);
                    if (item.optionColor) optionBits.push(item.optionColor);
                    const optionsHtml = optionBits.length
                      ? `<div class="cart-item-options text-sm text-gray-500">${optionBits.join(" • ")}</div>`
                      : "";
                    return `
                    <div class="cart-item" data-sku="${item.sku}">
                      ${img}
                      <div class="cart-item-details">
                        <div class="cart-item-title">${item.name || item.sku}</div>
                        ${optionsHtml}
                        <div class="cart-item-price">${currency(item.price)}</div>
                      </div>
                      <div class="cart-item-quantity">
                        <input type="number" min="0" class="cart-quantity-input" data-sku="${item.sku}" value="${item.quantity}" />
                      </div>
                      <div class="cart-item-remove remove-from-cart" data-sku="${item.sku}" aria-label="Remove item" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-trash" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                          <path d="M10 11v6"></path>
                          <path d="M14 11v6"></path>
                          <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
                        </svg>
                      </div>
                      <div class="cart-item-line-total">${currency(lineTotal)}</div>
                    </div>
                  `;
                  })
                  .join("");
                itemsEl.innerHTML = itemsHtml;
                if (footerEl) {
                  const disabledClass =
                    cart.getCount && cart.getCount() > 0 ? "" : "is-disabled";
                  const checkoutHref =
                    cart.getCount && cart.getCount() > 0
                      ? ' href="/payment"'
                      : "";
                  footerEl.innerHTML = `
                    <div class="cart-footer-bar">
                      <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                      <a class="cart-checkout-btn ${disabledClass}"${checkoutHref}>Checkout</a>
                    </div>
                  `;
                }
              } catch (e) {
                console.warn("[ViteEntry] renderCartDOM failed", e);
              }
            };
            if (cart) {
              window.WF_Cart = {
                addItem: (item) => {
                  try {
                    console.log("[ViteEntry] WF_Cart.addItem()", {
                      sku: item?.sku,
                      qty: item?.quantity,
                    });
                    cart.add(item, item?.quantity || 1);
                    updateUI();
                    try {
                      renderCartDOM();
                    } catch (_) {}
                  } catch (e) {
                    console.warn(
                      "[ViteEntry] WF_Cart.addItem adapter failed",
                      e,
                    );
                  }
                },
                removeItem: (sku) => {
                  try {
                    console.log("[ViteEntry] WF_Cart.removeItem()", { sku });
                    cart.remove && cart.remove(sku);
                    updateUI();
                    renderCartDOM();
                  } catch (e) {
                    console.warn(
                      "[ViteEntry] WF_Cart.removeItem adapter failed",
                      e,
                    );
                  }
                },
                updateItem: (sku, qty) => {
                  try {
                    console.log("[ViteEntry] WF_Cart.updateItem()", {
                      sku,
                      qty,
                    });
                    cart.updateQuantity && cart.updateQuantity(sku, qty);
                    updateUI();
                    renderCartDOM();
                  } catch (e) {
                    console.warn(
                      "[ViteEntry] WF_Cart.updateItem adapter failed",
                      e,
                    );
                  }
                },
                clearCart: () => {
                  try {
                    console.log("[ViteEntry] WF_Cart.clearCart()");
                    cart.clear && cart.clear();
                    updateUI();
                    renderCartDOM();
                  } catch (e) {
                    console.warn(
                      "[ViteEntry] WF_Cart.clearCart adapter failed",
                      e,
                    );
                  }
                },
                getItems: () => {
                  try {
                    return cart.getItems ? cart.getItems() : [];
                  } catch (_) {
                    return [];
                  }
                },
                getTotal: () => {
                  try {
                    return cart.getTotal ? cart.getTotal() : 0;
                  } catch (_) {
                    return 0;
                  }
                },
                getCount: () => {
                  try {
                    return cart.getCount ? cart.getCount() : 0;
                  } catch (_) {
                    return 0;
                  }
                },
                renderCart: () => {
                  try {
                    renderCartDOM();
                  } catch (_) {}
                },
                refreshFromStorage: () => {
                  try {
                    cart.load && cart.load();
                    cart.save && cart.save();
                    updateUI();
                    renderCartDOM();
                    return {
                      items: cart.getItems?.() || [],
                      total: cart.getTotal?.() || 0,
                      count: cart.getCount?.() || 0,
                    };
                  } catch (_) {
                    return { items: [], total: 0, count: 0 };
                  }
                },
              };
              console.log(
                "[ViteEntry] WF_Cart adapter installed (bridged to commerce/cart-system)",
              );
              // Initial UI sync on install
              try {
                updateUI();
                renderCartDOM();
              } catch (_) {}
            }
          }
        } catch (_) {
          /* noop */
        }
      } catch (_) {}
      try {
        await import("../commerce/sales-checker.js");
      } catch (_) {}
      // (Loaded globally above)
      try {
        await import("../js/shop.js");
      } catch (_) {}
    }

    // Admin settings bridge is loaded lazily by entries/admin-settings.js when needed

    console.log("[Vite] app.js entry loaded for page:", page || path);
  } catch (e) {
    console.warn("[Vite] app.js router error", e);
  }
})();

// Global delegated handler: remove-from-cart buttons (NOT on Admin Settings)
// Works for any dynamically rendered cart list using the same class and data-sku
try {
  if (!__wfIsAdminSettings) {
    document.addEventListener(
      "click",
      (e) => {
        const btn =
          e.target &&
          e.target.closest &&
          (e.target.closest(".remove-from-cart") ||
            e.target.closest(".cart-item-remove") ||
            e.target.closest('[data-action="remove-from-cart"]'));
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        let sku =
          btn.getAttribute("data-sku") ||
          (btn.dataset && btn.dataset.sku) ||
          (btn.parentElement &&
            btn.parentElement.getAttribute &&
            btn.parentElement.getAttribute("data-sku"));
        if (!sku) {
          try {
            const itemEl = btn.closest(".cart-item");
            if (itemEl) sku = itemEl.getAttribute("data-sku");
          } catch (_) {}
        }
        if (!sku) return;
        try {
          const mod = window.WF_Cart || window.cart || null;
          if (mod) {
            if (typeof mod.removeItem === "function") {
              mod.removeItem(sku);
            } else if (typeof mod.remove === "function") {
              mod.remove(sku);
            } else if (typeof mod.updateItem === "function") {
              // Fallback: set quantity to 0 to simulate removal
              mod.updateItem(sku, 0);
            }
          }
        } catch (_) {}
      },
      true,
    );
  }
} catch (_) {}
