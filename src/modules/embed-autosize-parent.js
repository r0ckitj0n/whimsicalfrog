// Parent autosize controller for embedded admin iframes
// - One global message handler (origin-validated)
// - Per-iframe dynamic height via a shared <style id="wf-embed-dynamic-heights">
// - Clamp to 95vh minus header/padding
// - Store data-wf-last-height on iframe
// - Disable same-origin fallback as soon as message-based sizing is active

let __wfEmbedMsgInited = false;
let __wfEmbedAutoId = 0;
let __wfEmbedPanelAutoId = 0;
const __wfEmbedRules = new Map();
const __wfPanelRules = new Map();

function ensureStyleTag() {
  const id = 'wf-embed-dynamic-heights';
  let tag = document.getElementById(id);
  if (!tag) {
    tag = document.createElement('style');
    tag.id = id;
    document.head.appendChild(tag);
  }
  return tag;
}

function sameHost(url) {
  try {
    const u = new URL(url);
    return u.hostname === window.location.hostname;
  } catch (_) {
    return false;
  }
}

function findIframeBySourceWindow(win) {
  try {
    const frames = document.querySelectorAll('iframe, .wf-admin-embed-frame');
    for (const f of frames) {
      // contentWindow comparison works across origins
      if (f && f.contentWindow === win) return f;
    }
  } catch (_) {}
  return null;
}

function updateStyleTag() {
  try {
    const styleTag = ensureStyleTag();
    const esc = (s) => {
      try {
        if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(String(s));
      } catch (_) {}
      try { return String(s).replace(/[^\w-]/g, '\\$&'); } catch(_) { return String(s); }
    };
    const cssFromEmbed = Array.from(__wfEmbedRules.entries()).map(([k,v]) => {
      const a = `#${esc(k)}{height:${v.h}px !important;min-height:0 !important;max-height:none !important;}`;
      const panelWidth = (v.pw && v.pw > 0) ? `${Math.max(0, v.pw)}px` : 'auto';
      const b = (v.p) ? `#${esc(v.p)}{max-width:${Math.max(0, v.pmw||0)}px !important;max-height:${Math.max(0, v.pmh||0)}px !important;width:${panelWidth} !important;height:auto !important;overflow:hidden !important;}` : '';
      // Higher specificity: overlay + panel id
      const b2 = (v.p && v.ov) ? `#${esc(v.ov)} #${esc(v.p)}{max-width:${Math.max(0, v.pmw||0)}px !important;max-height:${Math.max(0, v.pmh||0)}px !important;width:${panelWidth} !important;height:auto !important;overflow:hidden !important;}` : '';
      // Body rule: conditional based on whether content overflows
      let c = '';
      if (v.p) {
        if (v.scroll) {
          c = `#${esc(v.p)} .modal-body{height:auto !important;max-height:${Math.max(0, v.pbmax||0)}px !important;overflow-y:auto !important;overscroll-behavior:contain !important;-webkit-overflow-scrolling:touch !important;}`;
        } else {
          c = `#${esc(v.p)} .modal-body{max-height:none !important;height:auto !important;overflow:visible !important;display:block !important;flex:initial !important;}`;
        }
      }
      // Bind iframe height under its panel too, to crush any generic > iframe height rules
      const d = (v.p) ? `#${esc(v.p)} .modal-body > #${esc(k)}{height:${v.h}px !important;max-height:none !important;}` : '';
      return a + b + b2 + c + d;
    }).join('');
    const cssFromPanels = Array.from(__wfPanelRules.entries()).map(([pid,vals]) => {
      const p = `#${esc(pid)}{max-height:${Math.max(0, vals.pmh||0)}px !important;${vals.pmw?`max-width:${Math.max(0, vals.pmw)}px !important;`:''}overflow:hidden !important;}`;
      const b = `#${esc(pid)} .modal-body{max-height:${Math.max(0, vals.pbmax||0)}px !important;overflow-y:auto !important;overscroll-behavior:contain !important;-webkit-overflow-scrolling:touch !important;}`;
      return p + b;
    }).join('');
    const cssAll = cssFromPanels + cssFromEmbed;
    try { styleTag.textContent = cssAll; } catch(_) { try { styleTag.innerText = cssAll; } catch(_) {} }
  } catch(_) {}
}

function getMaxVwForOverlay(overlayEl) {
  try {
    const id = overlayEl && overlayEl.id ? String(overlayEl.id) : '';
    if (id === 'areaItemMapperModal' || id === 'costBreakdownModal') return 0.90; // wider workspace
    // Default: allow up to 90vw for responsive content-driven panels
    return 0.90;
  } catch(_) {}
  return 0.90;
}

function applyHeightToIframe(iframe, contentHeight, contentWidth) {
  if (!iframe) return;
  try {
    const panel = iframe.closest('.admin-modal');
    const body = panel ? panel.querySelector('.modal-body') : null;

    const headerH = panel && panel.querySelector('.modal-header') ? panel.querySelector('.modal-header').offsetHeight : 0;
    let padY = 0;
    try {
      if (body) {
        const cs = getComputedStyle(body);
        padY = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
      }
    } catch (_) {}
    let panelPadY = 0;
    let panelMarginY = 0;
    try {
      if (panel) {
        const cs = getComputedStyle(panel);
        panelPadY = (parseFloat(cs.paddingTop) || 0) + (parseFloat(cs.paddingBottom) || 0);
        panelMarginY = (parseFloat(cs.marginTop) || 0) + (parseFloat(cs.marginBottom) || 0);
      }
    } catch(_) {}

    const maxVh = 0.98;
    const maxPanel = Math.floor(window.innerHeight * maxVh);
    const available = Math.max(200, maxPanel - headerH - padY - panelPadY - panelMarginY);
    const desired = Math.max(80, Math.min(Math.round(Number(contentHeight) || 0), available));

    let padX = 0;
    try {
      if (body) {
        const cs = getComputedStyle(body);
        padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
      }
    } catch(_) {}
    const ov = panel ? panel.closest('.admin-modal-overlay') : null;
    let overlayId = ov && ov.getAttribute('id');
    if (!overlayId && ov) { overlayId = 'wf-overlay-auto-' + (++__wfEmbedPanelAutoId); try { ov.setAttribute('id', overlayId); } catch(_) {} }
    const maxVw = getMaxVwForOverlay(ov);
    const maxPanelW = Math.floor(window.innerWidth * (isFinite(maxVw) ? maxVw : 0.70));
    const availableW = Math.max(200, maxPanelW - padX);
    const rawW = Math.round(Number(contentWidth) || 0);
    const desiredW = rawW > 0 ? Math.max(160, Math.min(rawW, availableW)) : 0;

    try { if (window.__WF_DEBUG) console.debug('[wf-autosize] measure', {
      iframeId: iframe.id || null,
      panelId: panel && panel.id || null,
      headerH, padY, panelPadY, panelMarginY,
      maxPanel, available, reportedH: Number(contentHeight)||0, desiredH: desired,
      padX, maxPanelW, availableW, reportedW: Number(contentWidth)||0, desiredW
    }); } catch(_) {}

    const lastH = Number(iframe.getAttribute('data-wf-last-height') || '0');
    const lastW = Number(iframe.getAttribute('data-wf-last-width') || '0');
    if (lastH && Math.abs(desired - lastH) < 1 && (!desiredW || Math.abs((desiredW || 0) - (lastW || 0)) < 1)) return;

    let id = iframe.getAttribute('id');
    if (!id) {
      id = 'wf-embed-auto-' + (++__wfEmbedAutoId);
      try { iframe.setAttribute('id', id); } catch(_) {}
    }
    let panelId = panel && panel.getAttribute('id');
    if (!panelId && panel) {
      panelId = 'wf-panel-auto-' + (++__wfEmbedPanelAutoId);
      try { panel.setAttribute('id', panelId); } catch(_) {}
    }
    const applyPanelWidth = (desiredW && isFinite(desiredW) && desiredW >= 280);
    const panelMaxPx = Math.max(200, maxPanel - panelMarginY);
    // Will the modal body need to scroll? If content exceeds available space, scroll=true
    const fits = (Number(contentHeight) || 0) <= available;
    const finalPanelW = applyPanelWidth ? Math.max(280, Math.min(desiredW || 0, maxPanelW)) : 0;
    __wfEmbedRules.set(id, { h: desired, w: desiredW, p: panelId, ov: overlayId, pw: finalPanelW, pmw: maxPanelW, pbh: desired, pbmax: available, pmh: panelMaxPx, scroll: !fits });
    updateStyleTag();

    // Width clamping handled via dynamic CSS rules in updateStyleTag()

    if (body) {
      body.classList.toggle('wf-modal-body--autoheight', !!fits);
      body.classList.toggle('wf-modal-body--scroll', !fits);
      try { iframe.classList.remove('wf-embed--fill','wf-embed-h-s','wf-embed-h-m','wf-embed-h-l','wf-embed-h-xl','wf-embed-h-xxl','wf-admin-embed-frame--tall'); } catch (_) {}
      try { if (panel) panel.classList.remove('admin-modal--xs','admin-modal--sm','admin-modal--md','admin-modal--lg','admin-modal--xl','admin-modal--full','admin-modal--square-200','admin-modal--square-260','admin-modal--square-300'); } catch(_) {}
      try { if (window.__WF_DEBUG) console.debug('[wf-autosize] apply', { iframeId: id, panelId, fits, appliedH: desired, appliedW: desiredW, maxPanel, maxPanelW, bodyClasses: body.className }); } catch(_) {}
    }

    iframe.setAttribute('data-wf-last-height', String(desired));
    try { if (desiredW) iframe.setAttribute('data-wf-last-width', String(desiredW)); } catch(_) {}
  } catch (_) {}
}

function initEmbedAutosizeParent() {
  if (__wfEmbedMsgInited) return;
  try { window.__wfEmbedAutosizePrimary = true; } catch(_) {}
  window.addEventListener('message', (ev) => {
    try {
      const d = ev && ev.data;
      if (!d || d.source !== 'wf-embed-size') return;
      // Origin/security: accept same-origin or same-host (dev/prod)
      if (ev.origin && ev.origin !== window.location.origin && !sameHost(ev.origin)) return;

      const iframe = findIframeBySourceWindow(ev.source);
      if (!iframe) return;

      const reported = Number(d.height) || 0;
      const reportedW = Number(d.width) || 0;
      let applied = reported;
      let appliedW = reportedW;
      try {
        const doc = iframe.contentDocument;
        if (doc) {
          const altH = computeDocHeight(doc);
          const altW = computeDocWidth(doc);
          // Only treat as too small when the child clearly under-reports (<100px)
          // Do NOT inflate to scrollHeight just because it's <80% — prevents false positives
          const tooSmall = (reported < 100);
          if (tooSmall || applied === 0) applied = Math.max(applied, altH);
          if (appliedW === 0) appliedW = altW;
          if (tooSmall) {
            applyHeightToIframe(iframe, applied, appliedW);
            try { if (window.__WF_DEBUG) console.debug('[wf-embed-size] parent-message (ignored-early)', { h: reported, altH, appliedH: applied, w: reportedW, altW, appliedW, origin: ev.origin }); } catch(_) {}
            return; // keep fallback active
          }
        }
      } catch (_) {}

      // Valid value: switch to message-based sizing and disable fallback
      try { iframe.dataset.wfUseMsgSizing = '1'; } catch (_) {}
      try { if (iframe.__wfEmbedRO && typeof iframe.__wfEmbedRO.disconnect === 'function') iframe.__wfEmbedRO.disconnect(); } catch (_) {}

      applyHeightToIframe(iframe, applied, appliedW);
      try { if (window.__WF_DEBUG) console.debug('[wf-embed-size] parent-message', { h: applied, w: appliedW, origin: ev.origin }); } catch (_) {}
    } catch (_) {}
  });
  __wfEmbedMsgInited = true;
}

function computeDocHeight(doc) {
  try {
    if (!doc) return 0;
    const b = doc.body, de = doc.documentElement;
    return Math.max(
      b ? b.scrollHeight : 0,
      de ? de.scrollHeight : 0,
      b ? b.offsetHeight : 0,
      de ? de.offsetHeight : 0
    );
  } catch (_) { return 0; }
}

function computeDocWidth(doc) {
  try {
    if (!doc) return 0;
    const b = doc.body, de = doc.documentElement;
    return Math.max(
      b ? b.scrollWidth : 0,
      de ? de.scrollWidth : 0,
      b ? b.offsetWidth : 0,
      de ? de.offsetWidth : 0
    );
  } catch (_) { return 0; }
}

function attachSameOriginFallback(iframe, overlayEl) {
  try {
    if (!iframe) return;
    const markResponsive = () => {
      try { markOverlayResponsive(overlayEl || iframe.closest('.admin-modal-overlay')); } catch (_) {}
    };
    markResponsive();

    const bind = () => {
      try {
        const doc = iframe.contentDocument;
        if (!doc) return;
        const set = () => {
          if (iframe.dataset && iframe.dataset.wfUseMsgSizing === '1') return;
          applyHeightToIframe(iframe, computeDocHeight(doc), computeDocWidth(doc));
        };
        set();
        try {
          const times = [0, 50, 100, 200, 350, 500, 800, 1200];
          const run = () => { if (iframe.dataset && iframe.dataset.wfUseMsgSizing === '1') return; set(); };
          times.forEach((t)=>{ setTimeout(run, t); });
        } catch(_) {}
        try {
          const ro = new ResizeObserver(() => set());
          if (doc.body) ro.observe(doc.body);
          iframe.__wfEmbedRO = ro;
        } catch (_) {}
      } catch (_) {}
    };

    if (iframe.contentDocument) bind();
    try { iframe.addEventListener('load', bind, { once: false }); } catch (_) {}
  } catch (_) {}
}

function markOverlayResponsive(overlayEl) {
  try {
    if (!overlayEl) return;
    overlayEl.classList.remove('wf-modal--content-scroll');
    overlayEl.classList.remove('wf-modal--body-scroll');
    const panel = overlayEl.querySelector('.admin-modal');
    if (panel && !panel.classList.contains('admin-modal--responsive')) panel.classList.add('admin-modal--responsive');
    try { if (panel) panel.classList.remove('wf-modal-auto'); } catch(_) {}
    try { if (panel) panel.classList.remove('admin-modal--xs','admin-modal--sm','admin-modal--md','admin-modal--lg','admin-modal--xl','admin-modal--full','admin-modal--square-200','admin-modal--square-260','admin-modal--square-300'); } catch(_) {}
    try { if (panel) panel.classList.remove('wf-embed--fill','wf-embed-h-s','wf-embed-h-m','wf-embed-h-l','wf-embed-h-xl','wf-embed-h-xxl','wf-admin-embed-frame--tall'); } catch(_) {}
    let body = panel ? panel.querySelector('.modal-body') : null;
    const header = panel ? panel.querySelector('.modal-header') : null;
    if (panel && !body) {
      body = document.createElement('div');
      body.className = 'modal-body';
      if (header && header.nextSibling) panel.insertBefore(body, header.nextSibling); else panel.appendChild(body);
    }
    if (panel && body) {
      // Ensure order: header then body
      try { if (header && body.previousElementSibling !== header) panel.insertBefore(body, header.nextSibling); } catch(_) {}
      // Move any stray direct children into body
      try {
        const kids = Array.from(panel.children);
        kids.forEach((k) => {
          try { if (k !== header && k !== body) body.appendChild(k); } catch(_) {}
        });
      } catch(_) {}
      body.classList.add('wf-modal-body--autoheight');
      body.classList.remove('wf-modal-body--scroll');
      // Compute viewport-constrained heights for native (non-iframe-driven) panels using dynamic CSS rules
      try {
        const getF = (n, f) => { try { const cs = getComputedStyle(n); return (parseFloat(cs[f])||0); } catch(_) { return 0; } };
        const panelMarginY = (getF(panel,'marginTop') + getF(panel,'marginBottom'));
        const panelPadY = (getF(panel,'paddingTop') + getF(panel,'paddingBottom'));
        const bodyPadY = (getF(body,'paddingTop') + getF(body,'paddingBottom'));
        const headerH = header ? header.offsetHeight : 0;
        const maxVh = 0.98;
        const maxPanel = Math.floor(window.innerHeight * maxVh);
        const panelMaxPx = Math.max(200, maxPanel - panelMarginY);
        const maxPanelW = Math.floor(window.innerWidth * getMaxVwForOverlay(overlayEl));
        const available = Math.max(160, maxPanel - headerH - panelPadY - bodyPadY);
        let pid = panel.getAttribute('id');
        if (!pid) { pid = 'wf-panel-auto-' + (++__wfEmbedPanelAutoId); try { panel.setAttribute('id', pid); } catch(_) {} }
        const hasFrame = !!overlayEl.querySelector('iframe, .wf-admin-embed-frame');
        if (hasFrame) {
          // For iframe-driven modals, let per-iframe rules manage body/panel sizing
          try { __wfPanelRules.delete(pid); } catch(_) {}
        } else {
          __wfPanelRules.set(pid, { pmh: panelMaxPx, pbmax: available, pmw: maxPanelW });
        }
        updateStyleTag();
        // Width clamping handled via dynamic CSS rules in updateStyleTag()
      } catch(_) {}
    }
    document.body.classList.add('wf-embed-responsive-mode');
  } catch (_) {}
}

export { initEmbedAutosizeParent, attachSameOriginFallback, markOverlayResponsive };

// Centrally wire overlays regardless of opener path
function wireOverlay(overlayEl) {
  try {
    const el = (typeof overlayEl === 'string') ? document.getElementById(overlayEl) : overlayEl;
    if (!el || el.__wfWired) return;
    markOverlayResponsive(el);
    const frames = el.querySelectorAll('iframe, .wf-admin-embed-frame');
    frames.forEach((f) => {
      try { if (!f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
      try { f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}
      try { f.classList.remove('wf-embed--fill','wf-embed-h-s','wf-embed-h-m','wf-embed-h-l','wf-embed-h-xl','wf-embed-h-xxl','wf-admin-embed-frame--tall'); } catch(_) {}
      try { attachSameOriginFallback(f, el); } catch(_) {}
    });
    el.__wfWired = true;
  } catch(_) {}
}

function initOverlayAutoWire() {
  try {
    // Wire any existing overlays immediately
    try { document.querySelectorAll('.admin-modal-overlay').forEach((o)=> wireOverlay(o)); } catch(_) {}
    // Observe future overlays added or shown
    const mo = new MutationObserver((muts) => {
      try {
        for (const m of muts) {
          try {
            if (m.type === 'childList') {
              m.addedNodes.forEach((n) => {
                try {
                  if (!n || n.nodeType !== 1) return;
                  if (n.matches && n.matches('.admin-modal-overlay')) wireOverlay(n);
                  else if (n.querySelectorAll) n.querySelectorAll('.admin-modal-overlay').forEach((x)=> wireOverlay(x));
                } catch(_) {}
              });
            } else if (m.type === 'attributes' && m.attributeName === 'class') {
              const t = m.target;
              if (t && t.classList && t.classList.contains('admin-modal-overlay') && t.classList.contains('show')) wireOverlay(t);
            }
          } catch(_) {}
        }
      } catch(_) {}
    });
    mo.observe(document.body || document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    // Recompute on viewport changes for visible overlays
    const recomputeVisible = () => {
      try {
        document.querySelectorAll('.admin-modal-overlay.show').forEach((ov) => {
          try { markOverlayResponsive(ov); } catch(_) {}
        });
      } catch(_) {}
    };
    try { window.addEventListener('resize', recomputeVisible, { passive: true }); } catch(_) {}
    try { window.addEventListener('orientationchange', recomputeVisible, { passive: true }); } catch(_) {}
    // Expose for diagnostics
    try { window.__WFOverlayAutoWireMO = mo; } catch(_) {}
  } catch(_) {}
}

// Expose a minimal global helper for immediate resize from open handlers
try {
  if (typeof window !== 'undefined') {
    window.__wfEmbedAutosize = window.__wfEmbedAutosize || {};
    if (typeof window.__wfEmbedAutosize.resize !== 'function') {
      window.__wfEmbedAutosize.resize = function(iframe){
        try {
          if (!iframe) return;
          const doc = iframe.contentDocument;
          let h = 0, w = 0;
          try {
            if (doc) {
              const node = doc.getElementById('admin-section-content');
              if (node) {
                const r = node.getBoundingClientRect();
                h = Math.round(r.height);
                w = Math.round(r.width);
              }
            }
          } catch(_) {}
          if (h === 0 || w === 0) {
            h = doc ? computeDocHeight(doc) : 0;
            w = doc ? computeDocWidth(doc) : 0;
          }
          applyHeightToIframe(iframe, h, w);
        } catch(_) {}
      };
    }
  }
} catch(_) {}

export { wireOverlay, initOverlayAutoWire };
