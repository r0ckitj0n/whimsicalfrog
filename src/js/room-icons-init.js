// Initialize class-based positioning for room product icons (no inline styles)
// Applies to both legacy .item-icon and new .room-product-icon elements
(function roomIconsInit(){
  const STYLE_ID = 'wf-iconpos-runtime';
  function getStyleEl(){
    let el = document.getElementById(STYLE_ID);
    if (!el){ el = document.createElement('style'); el.id = STYLE_ID; document.head.appendChild(el); }
    return el;
  }
  const posCache = new Map(); // key t_l_w_h -> class
  function ensurePosClass(t,l,w,h){
    const top = Math.max(0, Math.round(Number(t)||0));
    const left = Math.max(0, Math.round(Number(l)||0));
    const width = Math.max(1, Math.round(Number(w)||0));
    const height = Math.max(1, Math.round(Number(h)||0));
    const key = `${top}_${left}_${width}_${height}`;
    if (posCache.has(key)) return posCache.get(key);
    const cls = `iconpos-t${top}-l${left}-w${width}-h${height}`;
    getStyleEl().appendChild(document.createTextNode(`.item-icon.${cls}, .room-product-icon.${cls}{position:absolute;top:${top}px;left:${left}px;width:${width}px;height:${height}px;--icon-top:${top}px;--icon-left:${left}px;--icon-width:${width}px;--icon-height:${height}px;}`));
    posCache.set(key, cls);
    return cls;
  }

  function applyPosFromDataset(el){
    if (!el || !el.dataset) return;
    const d = el.dataset;
    const top = d.originalTop;
    const left = d.originalLeft;
    const width = d.originalWidth;
    const height = d.originalHeight;
    if (top == null || left == null || width == null || height == null) return;
    const cls = ensurePosClass(top, left, width, height);
    if (el.dataset.iconPosClass && el.dataset.iconPosClass !== cls){
      el.classList.remove(el.dataset.iconPosClass);
    }
    el.classList.add(cls);
    el.dataset.iconPosClass = cls;
  }

  function initAll(root=document){
    try {
      const icons = root.querySelectorAll?.('.item-icon, .room-product-icon');
      if (!icons || !icons.length) return;
      icons.forEach(applyPosFromDataset);
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
            if (node.matches?.('.item-icon, .room-product-icon')) applyPosFromDataset(node);
            const nested = node.querySelectorAll?.('.item-icon, .room-product-icon');
            nested && nested.forEach(applyPosFromDataset);
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
