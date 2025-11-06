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
const __wfLastApply = new Map(); // key: panelId or iframeId -> { w, h, ts }

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
      const a = `#${esc(k)}{height:${Math.max(0, v.h||0)}px !important;min-height:0 !important;max-height:none !important;}`;
      const ovf = v.ovf ? 'visible' : 'hidden';
      // For autowide or viewport-fill overlays, do not force explicit width. Let CSS size content-driven and clamp via max/min.
      const panelWidthDecl = (v.autowide || v.vf) ? '' : `width:${(v.pw && v.pw > 0) ? Math.max(0, v.pw)+"px" : 'auto'} !important;`;
      const minWidthDecl = (v.minw && v.minw > 0) ? `min-width:${Math.max(0, v.minw)}px !important;` : '';
      const basePanelDecl = (v.p)
        ? `#${esc(v.p)}{max-width:${Math.max(0, v.pmw||0)}px !important;max-height:${Math.max(0, v.pmh||0)}px !important;${minWidthDecl}${panelWidthDecl}height:auto !important;overflow:${ovf} !important;box-sizing:border-box !important;}`
        : '';
      // Higher specificity: overlay + panel id
      const basePanelDecl2 = (v.p && v.ov)
        ? `#${esc(v.ov)} #${esc(v.p)}{max-width:${Math.max(0, v.pmw||0)}px !important;max-height:${Math.max(0, v.pmh||0)}px !important;${minWidthDecl}${panelWidthDecl}height:auto !important;overflow:${ovf} !important;box-sizing:border-box !important;}`
        : '';
      // Ensure overlay itself never scrolls; modal-body will handle vertical scrolling
      const b3 = (v.ov) ? `#${esc(v.ov)}{overflow:hidden !important;}` : '';
      // Scroll strategy: parent-scroll for Area Mapper and AI (including child AI overlay).
      // For viewport-fill overlays, keep parent-scroll (iframe overflow hidden), and only enable modal-body scroll when content exceeds the cap.
      const parentScrollIds = ['areaItemMapperModal','aiUnifiedModal','aiUnifiedChildModal'];
      let isParentScroll = (v && v.ov && parentScrollIds.indexOf(v.ov) !== -1);
      if (v && v.vf) { isParentScroll = true; }
      // Body rule: clamp to available; for viewport-fill, keep overflow hidden until scrolling is actually needed
      let c = '';
      if (v.p) {
        const innerMh = (v && v.vf) ? Math.max(0, (v.pbmax||0) - Math.max(0, v.bpy||0) - 8) : Math.max(0, v.pbmax||0);
        const mh = `${innerMh}px`;
        let oy = 'auto';
        if (v && v.vf) { oy = v.scroll ? 'auto' : 'hidden'; }
        const sg = (oy === 'auto') ? 'stable both-edges' : 'auto';
        c = `#${esc(v.p)} .modal-body{height:auto !important;max-height:${mh} !important;overflow-y:${oy} !important;overflow-x:hidden !important;overscroll-behavior:contain !important;-webkit-overflow-scrolling:touch !important;scrollbar-gutter:${sg} !important;}`;
      }
      // For iframe-scroller: pick the smaller of content height and available.
      // For parent-scroll overlays (AI Unified/Child), clamp the iframe to the modal-body's inner box (subtract padding for viewport-fill).
      const contentH = Math.max(0, v.h||0);
      const capBase = Math.max(0, v.pbmax||0);
      const capH = (v && v.vf) ? Math.max(0, capBase - Math.max(0, v.bpy||0) - 8) : capBase;
      const ifrH = capH ? Math.min(contentH || capH, capH) : contentH;
      const ifrOverflow = isParentScroll ? 'hidden' : 'auto';
      const d = (v.p) ? `#${esc(v.p)} .modal-body #${esc(k)}{height:${ifrH}px !important;max-height:none !important;width:100% !important;display:block !important;overflow:${ifrOverflow} !important;}` : '';
      // Buttons-specific: hide outer scrollbar (iframe-scroll)
      const bodyOverflowButtons = (v.p && v.ov === 'actionIconsManagerModal') ? `#${esc(v.p)} .modal-body{overflow-y:hidden !important;}` : '';
      return a + basePanelDecl + basePanelDecl2 + b3 + c + bodyOverflowButtons + d;
    }).join('');
    const cssFromPanels = Array.from(__wfPanelRules.entries()).map(([pid,vals]) => {
      const p = `#${esc(pid)}{max-height:${Math.max(0, vals.pmh||0)}px !important;${vals.pmw?`max-width:${Math.max(0, vals.pmw)}px !important;`:''}overflow:hidden !important;}`;
      const b = `#${esc(pid)} .modal-body{max-height:${Math.max(0, vals.pbmax||0)}px !important;height:auto !important;flex:initial !important;overflow-y:auto !important;overscroll-behavior:contain !important;-webkit-overflow-scrolling:touch !important;}`;
      return p + b;
    }).join('');
    const cssAll = cssFromPanels + cssFromEmbed;
    try { styleTag.textContent = cssAll; } catch(_) { try { styleTag.innerText = cssAll; } catch(_) {} }
  } catch(_) {}
}

function getMaxVwForOverlay(overlayEl) {
  try {
    if (overlayEl && overlayEl.classList && overlayEl.classList.contains('wf-modal-autowide')) {
      return 0.95;
    }
    // Default for overlays without autowide flag
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

    const maxVh = 0.95;
    const maxPanel = Math.floor(window.innerHeight * maxVh);
    let available = Math.max(200, maxPanel - headerH - padY - panelPadY - panelMarginY);
    // Refinement: re-measure same-origin DOM using optional selectors to avoid over/under-estimation
    const ov = panel ? panel.closest('.admin-modal-overlay') : null;
    let overlayId = ov && ov.getAttribute('id');
    if (!overlayId && ov) { overlayId = 'wf-overlay-auto-' + (++__wfEmbedPanelAutoId); try { ov.setAttribute('id', overlayId); } catch(_) {} }

    let refinedH = Math.round(Number(contentHeight) || 0);
    let refinedW = Math.round(Number(contentWidth) || 0);
    const doc = (iframe && iframe.contentDocument) ? iframe.contentDocument : null;
    let docH = 0;
    try {
      if (iframe.contentWindow && doc) {
        const selAttr = (iframe.getAttribute('data-measure-selector') || '').trim();
        const selectors = selAttr ? selAttr.split(',').map(s=>s.trim()).filter(Boolean)
          : ['#admin-section-content','.tool-wrap','.sc-redesign-grid','.attributes-grid','.admin-page','.admin-card'];
        const cH = [];
        const cW = [];
        selectors.forEach((sel)=>{
          try {
            const el = doc.querySelector(sel);
            if (!el) return;
            const r = el.getBoundingClientRect();
            if (r && r.height) cH.push(Math.round(r.height));
            if (r && r.width) cW.push(Math.round(r.width));
          } catch(_) {}
        });
        // Always consider the section container as a fallback if present
        try {
          const sc = doc.getElementById('admin-section-content');
          if (sc) { const r = sc.getBoundingClientRect(); if (r){ cH.push(Math.round(r.height)); cW.push(Math.round(r.width)); } }
        } catch(_) {}
        try { docH = computeDocHeight(doc); } catch(_) {}
        const maxCandH = cH.filter(n=>isFinite(n)&&n>0).reduce((a,b)=>Math.max(a,b), 0);
        const maxCandW = cW.filter(n=>isFinite(n)&&n>0).reduce((a,b)=>Math.max(a,b), 0);
        if (maxCandH) refinedH = Math.min(refinedH || maxCandH, maxCandH);
        if (maxCandW) refinedW = Math.min(refinedW || maxCandW, maxCandW);
      }
    } catch(_) {}

    // Prefer accurate doc height for deciding scroll state
    const accurateH = Math.max(refinedH || 0, docH || 0);
    // Clamp iframe height to available modal body space so only the modal body scrolls
    let desired = Math.max(80, Math.min(accurateH, available));
    // If nothing measurable yet, pre-fill to available to avoid tiny panel
    const reportedH = Math.round(Number(contentHeight) || 0);
    let forceScrollInit = false;
    if ((refinedH <= 60) && (reportedH <= 60)) {
      desired = available; // fill viewport cap so content shows
      forceScrollInit = true; // treat as needs scroll until real height arrives
    }

    let padX = 0;
    try {
      if (body) {
        const cs = getComputedStyle(body);
        padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
      }
    } catch(_) {}
    // overlayId computed above
    const maxVw = getMaxVwForOverlay(ov);
    const vpw = (document.documentElement && document.documentElement.clientWidth) ? document.documentElement.clientWidth : window.innerWidth;
    const maxPanelW = Math.max(0, Math.floor(vpw * (isFinite(maxVw) ? maxVw : 0.70)) - 1); // subtract 1px to avoid accidental overflow
    // Account for overlay + panel horizontal padding, margin, and borders when clamping width
    let panelPadX = 0, panelMarginX = 0, panelBorderX = 0;
    let overlayPadX = 0, overlayBorderX = 0;
    let overlayPadY = 0, overlayBorderY = 0;
    try {
      if (panel) {
        const pcs = getComputedStyle(panel);
        panelPadX = (parseFloat(pcs.paddingLeft)||0) + (parseFloat(pcs.paddingRight)||0);
        panelMarginX = (parseFloat(pcs.marginLeft)||0) + (parseFloat(pcs.marginRight)||0);
        panelBorderX = (parseFloat(pcs.borderLeftWidth)||0) + (parseFloat(pcs.borderRightWidth)||0);
      }
      if (ov) {
        const ocs = getComputedStyle(ov);
        overlayPadX = (parseFloat(ocs.paddingLeft)||0) + (parseFloat(ocs.paddingRight)||0);
        overlayBorderX = (parseFloat(ocs.borderLeftWidth)||0) + (parseFloat(ocs.borderRightWidth)||0);
        overlayPadY = (parseFloat(ocs.paddingTop)||0) + (parseFloat(ocs.paddingBottom)||0);
        overlayBorderY = (parseFloat(ocs.borderTopWidth)||0) + (parseFloat(ocs.borderBottomWidth)||0);
      }
    } catch(_) {}
    // Reduce vertical available space by overlay padding/borders to avoid off-by-1 overflow
    available = Math.max(160, available - overlayPadY - overlayBorderY);
    const availableW = Math.max(200, maxPanelW - overlayPadX - overlayBorderX - panelMarginX);
    const rawW = Math.round(Number(refinedW || contentWidth) || 0);
    let desiredPanelW = rawW > 0
      ? Math.max(280, Math.min(rawW + padX + panelPadX + panelBorderX, availableW))
      : 0;
    // If overlay declares a minimum columns requirement, ensure a baseline width for multi-col grids
    let minCols = 0;
    try {
      if (ov && ov.classList) {
        for (const cls of ov.classList) {
          const m = /^wf-modal-mincols-(\d+)$/.exec(cls);
          if (m) { minCols = parseInt(m[1], 10) || 0; break; }
        }
      }
    } catch(_) {}
    let minWidthFloor = 0;
    if (minCols >= 2) {
      let COL_MIN = 320; // default px per column minimum
      try {
        if (ov) {
          const cs = getComputedStyle(ov);
          const varVal = cs.getPropertyValue('--wf-modal-col-min').trim();
          if (varVal) {
            const m = /([0-9]+\.?[0-9]*)/.exec(varVal);
            if (m) COL_MIN = Math.max(200, Math.round(parseFloat(m[1])));
          }
        }
      } catch(_) {}
      const GAP = 12;      // px between columns
      const baseline = (minCols * COL_MIN) + ((minCols - 1) * GAP) + padX + panelPadX + panelBorderX;
      minWidthFloor = Math.min(baseline, availableW);
      desiredPanelW = Math.max(desiredPanelW || 0, minWidthFloor);
    }
    // Autowide overlays: do NOT force-fill viewport; width should be content-driven up to the 95vw clamp

    

    const lastH = Number(iframe.getAttribute('data-wf-last-height') || '0');
    const lastW = Number(iframe.getAttribute('data-wf-last-width') || '0');
    if (lastH && Math.abs(desired - lastH) < 1 && (!desiredPanelW || Math.abs((desiredPanelW || 0) - (lastW || 0)) < 1)) return;

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
    // If overlay is "autowide" and no minCols requested, do not set explicit width
    // When minCols is present, we do allow a baseline explicit width so columns can realize
    const isAutoWide = !!(ov && ov.classList && ov.classList.contains('wf-modal-autowide'));
    const isViewportFill = !!(ov && ov.classList && ov.classList.contains('wf-modal-viewport-fill')) || (overlayId === 'areaItemMapperModal');
    // For viewport-fill overlays, compute a generous available height that ignores inner paddings
    if (isViewportFill) {
      const generous = Math.max(200, maxPanel - headerH - overlayPadY - overlayBorderY - 2);
      if (generous > available) available = generous;
    }
    if (isAutoWide && !minCols) {
      desiredPanelW = 0;
    }
    const applyPanelWidth = (desiredPanelW && isFinite(desiredPanelW) && desiredPanelW >= 280);
    const panelMaxPx = Math.max(200, maxPanel - panelMarginY - overlayPadY - overlayBorderY);
    // If the child reports an overlay open, prefer autoheight with visible overflow
    const ovOpen = !!(iframe.dataset && iframe.dataset.wfOverlayOpen);
    let finalPanelW = applyPanelWidth ? Math.max(280, Math.min(desiredPanelW || 0, maxPanelW)) : 0;
    // Viewport-fill overlays: keep width content-driven (do not force-fill viewport).
    // Freeze width briefly after first settle to avoid flash while content stabilizes.
    if (isViewportFill) {
      const prevRec = __wfLastApply.get(panelId || id);
      const vpStable = prevRec && typeof prevRec.vpw === 'number'
        ? Math.abs(prevRec.vpw - vpw) <= 8
        : true;
      if (prevRec && prevRec.w && vpStable) {
        desiredPanelW = prevRec.w;
        finalPanelW = prevRec.w;
      } else {
        finalPanelW = Math.max(280, Math.min(desiredPanelW || 0, maxPanelW));
      }
    }

    // Determine scroll behavior with hysteresis to avoid rapid toggles
    const key = panelId || id;
    const prev = __wfLastApply.get(key) || { w: 0, h: 0, ts: 0, scroll: undefined };
    let fits = (Number(accurateH || refinedH || contentHeight) || 0) <= available;
    const fitsOrig = fits; // capture the raw fit before hysteresis adjustments
    if (forceScrollInit) fits = false;
    else desired = Math.max(80, Math.min(refinedH || desired, available));
    if (ovOpen) {
      fits = true; // force autoheight when inner overlay is open
    } else {
      const HYST = (ov && ov.classList && ov.classList.contains('wf-modal-autowide')) ? 16 : 12; // px buffer before flipping scroll state
      const delta = (Number(accurateH || refinedH || contentHeight) || 0) - available; // >0 means needs scroll
      if (prev.scroll === true) {
        // Previously scrolling: only stop scrolling when well under threshold
        fits = (delta <= -HYST);
      } else if (prev.scroll === false) {
        // Previously fit: only start scrolling when well over threshold
        fits = !(delta >= HYST);
      } else {
        fits = fitsOrig;
      }
    }

    // Viewport-fill overlays: prefer exact content height up to available; fallback to half-viewport only until content measures
    if (isViewportFill) {
      const baseline = Math.max(80, Math.round(available * 0.5));
      if (accurateH && accurateH > 0) {
        desired = Math.min(accurateH, available);
      } else {
        desired = baseline;
      }
      // Capture small outer margins/gaps that bounding boxes may miss
      desired = Math.min(available, desired + 16);
      // Epsilon to ignore tiny rounding/gap mismatches (avoid premature scrollbars)
      const EPS_CAP = 8;
      const measure = Number(accurateH || desired) || 0;
      fits = (measure <= (available + EPS_CAP));
      // Clamp desired to available regardless
      desired = Math.min(desired, available);
    }

    const EPS_W = 2; let EPS_H = 2, COOL_MS = 300, BIG_W = 16, BIG_H = 6; // defaults
    if (isViewportFill) {
      EPS_H = 10;     // ignore tiny height ripples
      COOL_MS = 600;  // longer cooldown
      BIG_W = 24;     // require larger width change to react during cooldown
      BIG_H = 20;     // require larger height change to react during cooldown
    }
    const nowTs = Date.now();
    if (Math.abs((finalPanelW||0) - (prev.w||0)) <= EPS_W && Math.abs(desired - (prev.h||0)) <= EPS_H) {
      return; // no meaningful change
    }
    // During cooldown, still allow noticeable height changes to apply (e.g., 1->2 column reflow)
    if ((nowTs - (prev.ts||0)) < COOL_MS && Math.abs((finalPanelW||0) - (prev.w||0)) <= BIG_W && Math.abs(desired - (prev.h||0)) <= BIG_H) {
      return; // cool-down period for small width adjustments
    }
    // Sticky shrink guard: allow quick growth, but only shrink if stable or significant
    const shrinking = (finalPanelW || 0) < (prev.w || 0);
    if (shrinking) {
      const SHRINK_COOL = 500;  // ms
      const SHRINK_MIN = 24;    // px
      if ((nowTs - (prev.ts||0)) < SHRINK_COOL && ((prev.w || 0) - (finalPanelW || 0)) <= SHRINK_MIN) {
        return; // ignore small/rapid shrink requests
      }
    }
    // Fill-flag: for Size/Color Redesign, always use the 95vh-driven clamps so panel grows to viewport cap
    const fillFlag = (overlayId === 'sizeColorRedesignModal');
    __wfEmbedRules.set(id, {
      h: Math.max(80, Math.round(desired)),
      w: Math.max(0, Math.round(rawW || desiredPanelW || 0)),
      p: panelId,
      ov: overlayId,
      pw: finalPanelW,
      pmw: maxPanelW,
      pbh: desired,
      pbmax: available,
      bpy: padY,
      pmh: panelMaxPx,
      scroll: !fits,
      ovf: !!ovOpen,
      ff: !!fillFlag,
      autowide: isAutoWide,
      minw: minWidthFloor,
      vf: !!isViewportFill
    });
    updateStyleTag();

    // Width clamping handled via dynamic CSS rules in updateStyleTag()

    if (body) {
      if (isViewportFill) {
        try { body.classList.remove('wf-modal-body--autoheight','wf-modal-body--scroll'); } catch(_) {}
      } else {
        body.classList.toggle('wf-modal-body--autoheight', !!fits);
        body.classList.toggle('wf-modal-body--scroll', !fits);
      }
      try { iframe.classList.remove('wf-embed--fill','wf-embed-h-s','wf-embed-h-m','wf-embed-h-l','wf-embed-h-xl','wf-embed-h-xxl','wf-admin-embed-frame--tall'); } catch (_) {}
      try { if (panel) panel.classList.remove('admin-modal--xs','admin-modal--sm','admin-modal--md','admin-modal--lg','admin-modal--xl','admin-modal--full','admin-modal--square-200','admin-modal--square-260','admin-modal--square-300'); } catch(_) {}
      
    }

    iframe.setAttribute('data-wf-last-height', String(desired));
    try { if (desiredPanelW) iframe.setAttribute('data-wf-last-width', String(desiredPanelW)); } catch(_) {}
    try { __wfLastApply.set(key, { w: (finalPanelW||0), h: desired, ts: nowTs, scroll: !fits, vpw }); } catch(_) {}
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
      // Throttle per-iframe message handling to avoid churn
      try {
        let id = iframe.getAttribute('id');
        if (!id) { id = 'wf-embed-auto-' + (++__wfEmbedAutoId); try { iframe.setAttribute('id', id); } catch(_) {} }
        window.__wfMsgThrottle = window.__wfMsgThrottle || new Map();
        const now = Date.now();
        const last = window.__wfMsgThrottle.get(id) || 0;
        if ((now - last) < 100) return;
        window.__wfMsgThrottle.set(id, now);
      } catch(_) {}
      try {
        const ov = iframe.closest('.admin-modal-overlay');
        const ovId = ov && ov.id ? ov.id : '';
        const parentScroll = ['areaItemMapperModal','aiUnifiedModal','aiUnifiedChildModal'].includes(ovId);
        if (iframe.contentDocument && iframe.contentDocument.head) {
          let tag = iframe.contentDocument.getElementById('wf-viewport-fill-inject');
          if (!tag) { tag = iframe.contentDocument.createElement('style'); tag.id = 'wf-viewport-fill-inject'; iframe.contentDocument.head.appendChild(tag); }
          if (parentScroll) {
            tag.textContent = 'html,body{overflow:hidden!important;height:auto!important;}#admin-section-content,.tool-wrap,.aim-tab-panel,.admin-card,.icons-table-wrap{overflow:visible!important;max-height:none!important;}';
          } else {
            tag.textContent = 'html,body{overflow:auto!important;height:auto!important;}#admin-section-content,.tool-wrap,.aim-tab-panel,.admin-card{max-height:none!important;}';
          }
        }
      } catch(_) {}

      // Overlay hint from child (e.g., inner editor modal open)
      try {
        if (Object.prototype.hasOwnProperty.call(d, 'overlay')) {
          if (d.overlay) {
            iframe.dataset.wfOverlayOpen = String(d.overlay);
          } else {
            delete iframe.dataset.wfOverlayOpen;
          }
        }
      } catch(_) {}

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
            return; // keep fallback active
          }
          // Viewport-fill overlays: always prefer the document height when it's larger
          try {
            const ov = iframe.closest('.admin-modal-overlay');
            const isVF = !!(ov && ov.classList && ov.classList.contains('wf-modal-viewport-fill'));
            if (isVF) applied = Math.max(applied, altH);
          } catch(_) {}
        }
      } catch (_) {}

      // Valid value: switch to message-based sizing and disable fallback
      try { iframe.dataset.wfUseMsgSizing = '1'; } catch (_) {}
      try { if (iframe.__wfEmbedRO && typeof iframe.__wfEmbedRO.disconnect === 'function') iframe.__wfEmbedRO.disconnect(); } catch (_) {}

      applyHeightToIframe(iframe, applied, appliedW);
      try { } catch (_) {}
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
        try {
          const ovId = overlayEl && overlayEl.id ? overlayEl.id : '';
          const parentScroll = ['areaItemMapperModal','aiUnifiedModal','aiUnifiedChildModal'].includes(ovId);
          let tag = doc.getElementById('wf-viewport-fill-inject');
          if (!tag) { tag = doc.createElement('style'); tag.id = 'wf-viewport-fill-inject'; doc.head.appendChild(tag); }
          if (parentScroll) {
            tag.textContent = 'html,body{overflow:hidden!important;height:auto!important;}#admin-section-content,.tool-wrap,.aim-tab-panel,.admin-card,.icons-table-wrap{overflow:visible!important;max-height:none!important;}';
          } else {
            tag.textContent = 'html,body{overflow:auto!important;height:auto!important;}#admin-section-content,.tool-wrap,.aim-tab-panel,.admin-card{max-height:none!important;}';
          }
        } catch (_) {}
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
          let __wfLastROTick = 0;
          const ro = new ResizeObserver(() => {
            const n = Date.now();
            if ((n - __wfLastROTick) < 100) return;
            __wfLastROTick = n;
            set();
          });
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
      const isViewportFill = !!(overlayEl && overlayEl.classList && overlayEl.classList.contains('wf-modal-viewport-fill'));
      if (!isViewportFill) {
        body.classList.add('wf-modal-body--autoheight');
        body.classList.remove('wf-modal-body--scroll');
      } else {
        try { body.classList.remove('wf-modal-body--autoheight','wf-modal-body--scroll'); } catch(_) {}
      }
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

// Centrally wire overlays regardless of opener path
function wireOverlay(overlayEl) {
  try {
    const el = (typeof overlayEl === 'string') ? document.getElementById(overlayEl) : overlayEl;
    if (!el || el.__wfWired) return;
    // Ensure rollout via CSS classes: autowide max-width clamp + single-scroll policy
    try { el.classList.add('wf-modal-autowide'); } catch(_) {}
    const isViewportFill = !!(el && el.classList && el.classList.contains('wf-modal-viewport-fill'));
    if (!isViewportFill) {
      try { el.classList.add('wf-modal-single-scroll'); } catch(_) {}
    } else {
      try { el.classList.remove('wf-modal-single-scroll'); } catch(_) {}
    }
    markOverlayResponsive(el);
    const frames = el.querySelectorAll('iframe, .wf-admin-embed-frame');
    frames.forEach((f) => {
      try { if (!f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
      try { if (!f.hasAttribute('data-measure-selector')) f.setAttribute('data-measure-selector', '#admin-section-content,.tool-wrap,.sc-redesign-grid,.attributes-grid,.admin-page,.admin-card'); } catch(_) {}
      try { f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}
      try { f.classList.remove('wf-embed--fill','wf-embed-h-s','wf-embed-h-m','wf-embed-h-l','wf-embed-h-xl','wf-embed-h-xxl','wf-admin-embed-frame--tall'); } catch(_) {}
      try { attachSameOriginFallback(f, el); } catch(_) {}
      try { f.addEventListener('load', () => { try { if (window.__wfEmbedAutosize && typeof window.__wfEmbedAutosize.resize === 'function') window.__wfEmbedAutosize.resize(f); } catch(_) {} }, { once: false }); } catch(_) {}
  });
    el.__wfWired = true;
  } catch(_) {}
}

function initOverlayAutoWire() {
  try {
    const updatePageScrollLock = () => {
      try {
        const anyOpen = !!document.querySelector('.admin-modal-overlay.show');
        const html = document.documentElement;
        const body = document.body;
        if (anyOpen) {
          try { html.classList.add('wf-admin-modal-open'); } catch(_) {}
          try { body.classList.add('wf-admin-modal-open'); } catch(_) {}
        } else {
          try { html.classList.remove('wf-admin-modal-open'); } catch(_) {}
          try { body.classList.remove('wf-admin-modal-open'); } catch(_) {}
        }
      } catch(_) {}
    };
    updatePageScrollLock();
    // Wrap showModal helpers to wire overlays just-in-time
    try {
      const wrap = (obj, key) => {
        try {
          const fn = obj && obj[key];
          if (typeof fn !== 'function') return;
          if (fn.__wfWrapped) return;
          obj[key] = function(id){
            try { const el = (typeof id === 'string') ? document.getElementById(id) : id; if (el) wireOverlay(el); } catch(_) {}
            return fn.apply(this, arguments);
          };
          obj[key].__wfWrapped = true;
        } catch(_) {}
      };
      wrap(window, 'showModal');
      wrap(window, '__wfShowModal');
      if (window.WFModalUtils) wrap(window.WFModalUtils, 'showModalById');
    } catch(_) {}

    // Observe body direct children only; wire overlays as they are appended
    const mo = new MutationObserver((muts) => {
      try {
        for (const m of muts) {
          if (m.type !== 'childList') continue;
          m.addedNodes.forEach((n) => {
            try {
              if (!n || n.nodeType !== 1) return;
              if (n.classList && n.classList.contains('admin-modal-overlay')) {
                wireOverlay(n);
                // Track show/hide to maintain scroll lock
                try {
                  if (!n.__wfAttrMO) {
                    const amo = new MutationObserver(() => updatePageScrollLock());
                    amo.observe(n, { attributes: true, attributeFilter: ['class','aria-hidden'] });
                    n.__wfAttrMO = amo;
                  }
                } catch(_) {}
              }
            } catch(_) {}
          });
        }
        updatePageScrollLock();
      } catch(_) {}
    });
    try { mo.observe(document.body, { childList: true }); } catch(_) {}

    // Recompute on viewport changes for visible overlays
    const recomputeVisible = () => {
      try {
        document.querySelectorAll('.admin-modal-overlay.show').forEach((ov) => {
          try { markOverlayResponsive(ov); } catch(_) {}
        });
        updatePageScrollLock();
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

export { initEmbedAutosizeParent, attachSameOriginFallback, markOverlayResponsive, wireOverlay, initOverlayAutoWire };
