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
  function schedulePost(height, width) {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
      try {
        window.parent.postMessage({ source: 'wf-embed-size', height: Number(height) || 0, width: Number(width) || 0 }, '*');
      } catch (_) {}
    });
  }

  function install() {
    const node = getMeasureNode();
    if (!node) return;

    const measure = () => {
      try {
        const r = node.getBoundingClientRect();
        const rectH = Math.round(r.height);
        let rectW = Math.round(r.width);
        let overflowY = 'visible';
        try { overflowY = getComputedStyle(node).overflowY || 'visible'; } catch(_) {}
        const sH = Number(node.scrollHeight || 0);
        let sW = Number(node.scrollWidth || 0);
        // Attributes: compute natural width from grid and labels to avoid premature ellipsis
        try {
          const grid = node.querySelector('.attributes-grid');
          if (grid) {
            let maxLabelW = 0;
            grid.querySelectorAll('ul.simple li > span:first-child').forEach((el)=>{
              try { maxLabelW = Math.max(maxLabelW, el.scrollWidth || 0); } catch(_) {}
            });
            const gridPad = (()=>{ try{ const cs=getComputedStyle(grid); return (parseFloat(cs.paddingLeft)||0)+(parseFloat(cs.paddingRight)||0); }catch(_){return 0;}})();
            const natural = Math.max(grid.scrollWidth || 0, Math.ceil(maxLabelW + 220 + gridPad));
            sW = Math.max(sW, natural);
            rectW = Math.max(rectW, Math.min(natural, (node.scrollWidth||rectW)));
          }
        } catch(_) {}
        const preferRect = (overflowY === 'visible' || overflowY === 'unset' || overflowY === 'initial' || overflowY === '');
        const h = preferRect ? rectH : Math.max(rectH, sH);
        const w = Math.max(rectW, sW);
        return { h, w };
      } catch(_) { return { h: 0, w: 0 }; }
    };

    const send = () => { const m = measure(); schedulePost(m.h, m.w); };
    send();

    try {
      const ro = new ResizeObserver(() => send());
      ro.observe(node);
      if (document.body) ro.observe(document.body);
    } catch (_) {
      // Fallback: poll a few times on slow pages
      let c = 0; const t = setInterval(() => { send(); if (++c > 20) clearInterval(t); }, 200);
    }

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
