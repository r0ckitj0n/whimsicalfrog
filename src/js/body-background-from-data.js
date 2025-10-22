import { ApiClient } from '../core/api-client.js';
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

  async function fetchRoomBgIfNeeded() {
    try {
      const body = document.body;
      if (!body) return null;
      const ds = body.dataset || {};
      if (ds.bgUrl) return null; // nothing to do
      // Detect a room N page from path like /room/1 or query ?room=1
      const path = window.location.pathname || '';
      let roomNum = null;
      const segs = path.split('/').filter(Boolean);
      if (segs.length >= 2 && segs[0].toLowerCase() === 'room' && /^\d+$/.test(segs[1])) {
        roomNum = segs[1];
      } else {
        const params = new URLSearchParams(window.location.search || '');
        const roomParam = params.get('room') || params.get('room_number');
        if (roomParam && /^\d+$/.test(roomParam)) roomNum = roomParam;
      }
      if (!roomNum) return null;
      // Fetch background info and return an absolute URL with cache-busting
      const data = await ApiClient.get('/api/get_background.php', { room: roomNum }).catch(() => null);
      const bg = data && data.background ? (data.background.webp_filename || data.background.png_filename || data.background.image_filename) : null;
      if (!bg) return null;
      const val = String(bg).trim();
      const buildUrl = (v) => {
        if (!v) return '';
        if (/^https?:\/\//i.test(v)) return v;
        if (v.startsWith('/images/')) return window.location.origin + v;
        if (v.startsWith('images/')) return window.location.origin + '/' + v;
        if (v.startsWith('backgrounds/')) return window.location.origin + '/images/' + v;
        return window.location.origin + '/images/backgrounds/' + v;
      };
      const url = buildUrl(val) + `&v=${Date.now()}`.replace('&v','?v');
      body.setAttribute('data-bg-url', url);
      return url;
    } catch (_) { return null; }
  }

  async function run() {
    try {
      const body = document.body;
      if (!body) return;
      let url = body.dataset && body.dataset.bgUrl;
      if (!url) {
        // Try to populate it for room pages
        url = await fetchRoomBgIfNeeded();
        if (!url) return; // still nothing to apply
      }
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
    document.addEventListener('DOMContentLoaded', () => { run(); }, { once: true });
  } else {
    run();
  }
})();
