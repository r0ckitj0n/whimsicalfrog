// Applies background image from body[data-bg-url] at runtime without inline style writes
(function applyBodyBackgroundFromData() {
  function ensureStyleElement() {
    let styleEl = document.getElementById('wf-body-bg-style');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'wf-body-bg-style';
      document.head.appendChild(styleEl);
    }
    return styleEl;
  }

  function run() {
    try {
      const body = document.body;
      if (!body) return;
      const url = body.dataset && body.dataset.bgUrl;
      if (!url) return;
      const styleEl = ensureStyleElement();
      // Create a CSS rule that targets the body via attribute flag instead of element.style
      styleEl.textContent = `body[data-bg-url][data-bg-applied="1"] { background-image: url(${JSON.stringify(url)}); }`;
      body.setAttribute('data-bg-applied', '1');
      body.classList.add('wf-bg-applied');
    } catch (e) {
      console.warn('[WF] Failed to apply body background from data-bg-url', e);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();
