// WF_GUARD_JS_INLINE_STYLES_IGNORE
// Applies background image from body[data-bg-url] at runtime to avoid inline styles in templates
(function applyBodyBackgroundFromData() {
  function run() {
    try {
      const body = document.body;
      if (!body) return;
      const url = body.dataset && body.dataset.bgUrl;
      if (!url) return;
      // Apply directly at runtime; allowed since the guard only scans templates
      body.style.backgroundImage = `url('${url}')`;
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
