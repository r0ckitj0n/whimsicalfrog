// Admin Settings: Attributes inline loader
// Exposes window.loadAttributesInline() used by delegated-handlers on the Settings page
import { ApiClient } from '../core/api-client.js';

(function(){
  const MOUNT_ID = 'attributesInlineContainer';

  async function fetchHtml(url){
    try {
      const txt = await ApiClient.request(url, { method: 'GET', headers: { 'Accept': 'text/html' } });
      return (typeof txt === 'string') ? txt : '';
    } catch(_) { return ''; }
  }

  async function loadAttributesInline(){
    const mount = document.getElementById(MOUNT_ID);
    if (!mount) return;
    if (mount.__wfLoading) return;
    mount.__wfLoading = true;
    try {
      // Simple loading state (non-blocking)
      try { mount.innerHTML = '<div class="p-3 text-sm text-gray-600">Loading…</div>'; } catch(_){ }
      const url = '/components/embeds/attributes_manager.php?modal=1&_=' + Date.now();
      const html = await fetchHtml(url);
      if (!html) {
        try { mount.innerHTML = '<div class="p-2 text-sm text-red-700">Failed to load Attributes Manager</div>'; } catch(_){ }
        return;
      }
      // Parse and mount content
      let inner = html;
      try {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        inner = (doc && doc.body && doc.body.innerHTML) ? doc.body.innerHTML : html;
      } catch(_) { /* keep raw */ }
      mount.innerHTML = inner;

      // Execute inline scripts to bootstrap handlers embedded in the fragment
      try {
        const scripts = Array.from(mount.querySelectorAll('script'));
        scripts.forEach((old) => {
          const s = document.createElement('script');
          if (old.type) s.type = old.type;
          if (old.src) s.src = old.src;
          else s.text = old.textContent || '';
          s.setAttribute('data-wf-attr-inline','1');
          old.replaceWith(s);
        });
      } catch(_) {}

      // Signal ready
      try { document.dispatchEvent(new CustomEvent('wf:attributes-mounted')); } catch(_) {}
    } finally {
      try { mount.__wfLoading = false; } catch(_) {}
    }
  }

  try { window.loadAttributesInline = loadAttributesInline; } catch(_) {}

  // Optional: If modal becomes shown, trigger load once
  try {
    const modal = document.getElementById('attributesModal');
    if (modal && !modal.__wfAttrObs) {
      let prevShown = modal.classList.contains('show');
      const obs = new MutationObserver(() => {
        try {
          const shown = modal.classList.contains('show');
          if (shown && !prevShown) {
            if (typeof window.loadAttributesInline === 'function') window.loadAttributesInline();
          }
          prevShown = shown;
        } catch(_) {}
      });
      obs.observe(modal, { attributes: true, attributeFilter: ['class','aria-hidden'] });
      modal.__wfAttrObs = true;
    }
  } catch(_) {}
})();
