// src/js/console-gate.js
// Gate console logging behind WF_DEBUG flag or ?debug=1 in production.
// Always allow logs in development.

(function(){
  if (typeof window === 'undefined') return;
  const isDev = (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.DEV) || false;
  if (isDev) return; // do not gate logs in dev

  const hasQueryDebug = /(?:^|[?&])debug=1(?:&|$)/.test((window.location && window.location.search) || '');
  const enabled = window.WF_DEBUG === true || hasQueryDebug;

  if (!enabled) {
    const noop = () => {};
    try {
      if (window.console) {
        // Preserve warn/error; silence info/log/debug in production unless explicitly enabled
        window.console.debug = noop;
        window.console.log = noop;
        if (!window.console.info) window.console.info = noop; // ensure defined
        else window.console.info = noop;
      }
    } catch (_) {}
  }
})();
