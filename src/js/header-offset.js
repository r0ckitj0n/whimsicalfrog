// Compute and expose the header height as a CSS variable so pages can offset content precisely
(function initHeaderOffset(){
  function ensureHeaderStyleEl() {
    let el = document.getElementById('wf-header-offset-css');
    if (!el) {
      el = document.createElement('style');
      el.id = 'wf-header-offset-css';
      document.head.appendChild(el);
    }
    return el;
  }

  function setHeaderHeightVar(){
    const styleEl = ensureHeaderStyleEl();
    try {
      const header = document.querySelector('.universal-page-header');
      const h = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
      const pad = 0; // extra pixels if desired
      const px = (h > 0 ? (h + pad) : 120);
      styleEl.textContent = `:root { --wf-header-height: ${px}px; }`;
    } catch (e) {
      // fallback default
      styleEl.textContent = `:root { --wf-header-height: 120px; }`;
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setHeaderHeightVar, { once: true });
  } else {
    setHeaderHeightVar();
  }
  window.addEventListener('resize', setHeaderHeightVar);
  // Recalculate when fonts load (header height can change)
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(setHeaderHeightVar).catch(()=>{});
  }
})();
