// Initialize CSS variable-based positioning for room product icons
// Applies to both legacy .item-icon and new .room-product-icon elements
(function roomIconsInit(){
  function setVarsFromDataset(el){
    if (!el || !el.dataset) return;
    const d = el.dataset;
    const top = d.originalTop;
    const left = d.originalLeft;
    const width = d.originalWidth;
    const height = d.originalHeight;
    // Only set if provided; values are numbers as strings
    if (top != null && top !== '') el.style.setProperty('--icon-top', `${parseFloat(top)||0}px`);
    if (left != null && left !== '') el.style.setProperty('--icon-left', `${parseFloat(left)||0}px`);
    if (width != null && width !== '') el.style.setProperty('--icon-width', `${parseFloat(width)||0}px`);
    if (height != null && height !== '') el.style.setProperty('--icon-height', `${parseFloat(height)||0}px`);
  }

  function initAll(root=document){
    try {
      const icons = root.querySelectorAll?.('.item-icon, .room-product-icon');
      if (!icons || !icons.length) return;
      icons.forEach(setVarsFromDataset);
    } catch(e){
      console.warn('[room-icons-init] initAll failed', e);
    }
  }

  function onReady(){
    initAll(document);
    // Observe for dynamically inserted icons (e.g., room content loaded async)
    try {
      const mo = new MutationObserver((muts) => {
        for (const m of muts){
          m.addedNodes && m.addedNodes.forEach(node => {
            if (!(node instanceof Element)) return;
            if (node.matches?.('.item-icon, .room-product-icon')) setVarsFromDataset(node);
            const nested = node.querySelectorAll?.('.item-icon, .room-product-icon');
            nested && nested.forEach(setVarsFromDataset);
          });
        }
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
    } catch(e){
      console.warn('[room-icons-init] MutationObserver not available', e);
    }
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', onReady, { once: true });
  } else {
    onReady();
  }
})();
