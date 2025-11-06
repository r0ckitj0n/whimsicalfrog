// Child autosize emitter for embedded admin pages
// Activates only when running inside an iframe and body[data-embed="1"] is present
(function(){
  try {
    if (window.parent === window) return; // not embedded
  } catch(_) { return; }

  function isModalEmbed() {
    try {
      const b = document.body;
      return !!(b && b.getAttribute('data-embed') === '1');
    } catch (_) { return false; }
  }

  if (!isModalEmbed()) return;

  function getMeasureNode() {
    try {
      // Size/Color Redesign tool: prefer wrapper so height includes header + grid
      const scWrap = document.querySelector('.tool-wrap');
      if (scWrap) return scWrap;
      // Fallback: main grid (width calc still handled separately below)
      const scGrid = document.querySelector('.sc-redesign-grid');
      if (scGrid) return scGrid;
      const catRoot = document.getElementById('categoryManagementRoot');
      if (catRoot) return catRoot;
      // Prefer the primary content container in admin embeds
      const section = document.getElementById('admin-section-content');
      if (section) return section;
      const adminPage = document.querySelector('.admin-page, .admin-categories-page, .admin-tools-page');
      if (adminPage) return adminPage;
      const marketingRoot = document.querySelector('.admin-marketing-page');
      if (marketingRoot) return marketingRoot;
      // Generic: fallback to a stable admin card inside page container
      const firstCard = document.querySelector('.admin-card');
      if (firstCard) return firstCard;
      // Final fallback to body/page
      const containers = document.querySelectorAll('#admin-section-content');
      if (containers && containers.length) return containers[0];
    } catch (_) {}
    return document.body || document.documentElement;
  }

  

  let rafId = 0;
  let lastSentH = 0, lastSentW = 0, lastSentOv = '', lastSentTs = 0;
  function schedulePost(height, width, overlay) {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      try {
        const h = Math.round(Number(height) || 0);
        const w = Math.round(Number(width) || 0);
        const ov = overlay ? String(overlay) : '';
        const EPS = 2, BIG = 12, MIN_MS = 220;
        const now = Date.now();
        const smallDelta = Math.abs(h - lastSentH) <= EPS && Math.abs(w - lastSentW) <= EPS;
        const timeOk = (now - lastSentTs) >= MIN_MS;
        const bigDelta = Math.abs(h - lastSentH) >= BIG || Math.abs(w - lastSentW) >= BIG;
        // Always ignore tiny no-op deltas when overlay state hasn't changed
        if (smallDelta && String(ov) === String(lastSentOv)) return;
        // Throttle normal updates unless the delta is clearly significant
        if (!timeOk && !bigDelta && String(ov) === String(lastSentOv)) return;
        lastSentH = h; lastSentW = w; lastSentOv = ov; lastSentTs = now;
        window.parent.postMessage({ source: 'wf-embed-size', height: h, width: w, overlay: ov || undefined }, '*');
      } catch (_) {}
    });
  }

  function install() {
    const node = getMeasureNode();
    if (!node) return;

    const measure = () => {
      try {
        const r = node.getBoundingClientRect();
        let rectH = Math.round(r.height);
        let rectW = Math.round(r.width);
        let overflowY = 'visible';
        try { overflowY = getComputedStyle(node).overflowY || 'visible'; } catch(_) {}
        const sH = Number(node.scrollHeight || 0);
        // Document-level candidates to avoid under-measuring (esp. Attributes modal)
        let docH = 0;
        try {
          const de = document.documentElement;
          const db = document.body;
          docH = Math.max(
            rectH,
            sH,
            de ? Math.max(de.scrollHeight || 0, de.offsetHeight || 0, de.clientHeight || 0) : 0,
            db ? Math.max(db.scrollHeight || 0, db.offsetHeight || 0, db.clientHeight || 0) : 0
          );
        } catch(_) {}
        let sW = Number(node.scrollWidth || 0);
        // Size/Color Redesign: compute natural width for 2-column grid
        try {
          const sc = document.querySelector('.sc-redesign-grid');
          if (sc) {
            const cs = getComputedStyle(sc);
            const gapX = (parseFloat(cs.columnGap) || parseFloat(cs.gap) || 12);
            const padX = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.paddingRight) || 0);
            // Use a wider baseline per track so parent allocates generous space
            const minTrack = 420;
            const desiredCols = 2;
            const natural = Math.ceil(desiredCols * minTrack + (desiredCols - 1) * gapX + padX + 24);
            sW = Math.max(sW, natural);
            rectW = Math.max(rectW, natural);
          }
        } catch(_) {}
        // Attributes: compute natural width from grid to avoid single-column lock,
        // and prefer grid/container height as the primary height signal
        try {
          const grid = node.querySelector('.attributes-grid');
          if (grid) {
            // Also strengthen height from the grid region
            try {
              const gr = grid.getBoundingClientRect();
              const gridH = Math.round(gr.height);
              rectH = Math.max(rectH, gridH);
            } catch(_) {}
            const cs = getComputedStyle(grid);
            const gapX = (parseFloat(cs.columnGap) || parseFloat(cs.gap) || 16);
            const gridPad = (()=>{ try{ return (parseFloat(cs.paddingLeft)||0)+(parseFloat(cs.paddingRight)||0); }catch(_){return 0;}})();
            const cards = Array.from(grid.querySelectorAll(':scope > .card'));
            // Measure per-card natural widths: label column + actions width, per column
            const trackWidths = cards.slice(0,3).map((card, idx) => {
              let L = 0; let A = 0;
              try { card.querySelectorAll('ul.simple li > span:first-child').forEach(el=>{ L = Math.max(L, el.scrollWidth||0); }); } catch(_) {}
              try {
                card.querySelectorAll('ul.simple li .row-actions').forEach(ra=>{
                  const w = Math.max(ra.scrollWidth||0, ra.offsetWidth||0);
                  A = Math.max(A, w);
                });
              } catch(_) {}
              // Baselines: Genders content can be skinny; Sizes/Colors need wider min
              const minTrack = idx === 0 ? 140 : 260;
              // Aim to fit full label + actions when space allows
              const desired = Math.ceil((L||0) + (A||0) + 24);
              return Math.max(minTrack, desired);
            });
            const have = trackWidths.length;
            const sumTracks = trackWidths.reduce((a,b)=>a+b,0);
            const natural = Math.max(
              grid.scrollWidth || 0,
              Math.ceil(sumTracks + Math.max(0, have-1) * gapX + gridPad)
            );
            sW = Math.max(sW, natural);
            rectW = Math.max(rectW, natural);
          }
        } catch(_) {}

        // Area Item Mapper: compute natural two-column width so parent can widen to prevent stacking
        try {
          const aimList = document.getElementById('aimMappingsList');
          if (aimList) {
            const grid = document.querySelector('#admin-section-content > .grid');
            if (grid && grid.children && grid.children.length >= 2) {
              const cs = getComputedStyle(grid);
              const gapX = (parseFloat(cs.columnGap) || parseFloat(cs.gap) || 16);
              const padX = (()=>{ try{ return (parseFloat(cs.paddingLeft)||0)+(parseFloat(cs.paddingRight)||0);}catch(_){return 0;}})();
              const left = grid.children[0];
              const right = grid.children[1];
              // Include inner list/table width for right side if present
              const rightInner = right.querySelector('#aimMappingsList') || right;
              let rw = 0;
              try {
                const tbl = rightInner.querySelector('table');
                if (tbl) {
                  rw = Math.max(tbl.scrollWidth || 0, tbl.offsetWidth || 0, rightInner.scrollWidth || 0);
                } else {
                  rw = Math.max(rightInner.scrollWidth || 0, rightInner.offsetWidth || 0);
                }
              } catch(_) { rw = Math.max(rightInner ? (rightInner.scrollWidth || rightInner.offsetWidth || 0) : 0, 0); }
              const lw = Math.max(left ? (left.scrollWidth || left.offsetWidth || 0) : 0, 360);
              // Safety margin to account for borders/padding inside column cards
              const natural = Math.ceil(lw + gapX + rw + padX + 24);
              sW = Math.max(sW, natural);
              rectW = Math.max(rectW, natural);
            }
          }
        } catch(_) {}

        // If any admin overlay is open inside the iframe, include its box in measurements
        let overlayOpen = false;
        try {
          const ov = document.querySelector('.admin-modal-overlay.show, .attr-modal-overlay.show');
          if (ov) {
            const or = ov.getBoundingClientRect();
            rectH = Math.max(rectH, Math.round(or.height));
            rectW = Math.max(rectW, Math.round(or.width));
            overlayOpen = true;
          }
        } catch (_) {}
        const preferRect = (overflowY === 'visible' || overflowY === 'unset' || overflowY === 'initial' || overflowY === '');
        // Build content-height candidates prioritizing primary containers
        const candidates = [preferRect ? rectH : Math.max(rectH, sH)];
        try {
          const section = document.getElementById('admin-section-content');
          if (section) {
            const sr = section.getBoundingClientRect();
            candidates.push(Math.round(sr.height));
            candidates.push(Number(section.scrollHeight||0));
          }
          const grid = document.querySelector('.attributes-grid');
          if (grid) {
            const gr = grid.getBoundingClientRect();
            candidates.push(Math.round(gr.height));
            candidates.push(Number(grid.scrollHeight||0));
          }
        } catch(_) {}
        const baseH = Math.max.apply(null, candidates.filter(n=>isFinite(n)));
        // Prefer document scrollHeight when an inner overlay is open so parent expands to cap
        let h = baseH;
        if (overlayOpen && docH) {
          h = Math.max(baseH, docH);
        } else if (docH && docH > baseH) {
          const excess = docH - baseH;
          const THRESH = 48; // small tolerance to account for minor gutters
          if (excess <= THRESH) h = docH;
        }
        const w = Math.max(rectW, sW);
        return { h, w, overlay: overlayOpen ? 'open' : '' };
      } catch(_) { return { h: 0, w: 0 }; }
    };

    const send = () => { const m = measure(); schedulePost(m.h, m.w, m.overlay); };
    send();

    try {
      const ro = new ResizeObserver(() => send());
      ro.observe(node);
      if (document.body) ro.observe(document.body);
    } catch (_) {
      // Fallback: poll a few times on slow pages
      let c = 0; const t = setInterval(() => { send(); if (++c > 20) clearInterval(t); }, 200);
    }

    // Also observe overlay show/hide to trigger updates promptly
    try {
      const mo = new MutationObserver(() => send());
      document.querySelectorAll('.admin-modal-overlay, .attr-modal-overlay').forEach((el) => {
        try { mo.observe(el, { attributes: true, attributeFilter: ['class','style'] }); } catch(_) {}
      });
    } catch(_) {}

    // Also update on images loaded and DOM ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', send, { once: true });
    } else {
      setTimeout(send, 0);
    }
    window.addEventListener('load', () => {
      setTimeout(send, 0);
      let i = 0; const t = setInterval(() => { send(); if (++i > 10) clearInterval(t); }, 100);
    }, { once: true });
  }

  install();
})();
