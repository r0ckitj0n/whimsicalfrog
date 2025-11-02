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
      if (!window.ApiClient || typeof window.ApiClient.request !== 'function') return null;
      const data = await window.ApiClient.request(`/api/get_background.php?room=${encodeURIComponent(roomNum)}`, { method: 'GET' }).catch(() => null);
      const bg = data && data.background ? (data.background.webp_filename || data.background.image_filename) : null;
      if (!bg) return null;
      let fname = String(bg);
      if (fname.startsWith('background_')) fname = fname.replace(/^background_/, 'background-');
      if (!fname.startsWith('background-')) fname = `background-${fname}`;
      const url = `${window.location.origin}/images/backgrounds/${fname}?v=${Date.now()}`;
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
      // Create CSS rules that target the body via attribute flags instead of element.style
      styleEl.textContent = `
        body[data-bg-url][data-bg-applied="1"] {
          background-image: url(${JSON.stringify(url)});
          background-size: cover;
          background-position: center;
          background-repeat: no-repeat;
        }
        body[data-bg-url][data-bg-applied="1"]:not([data-is-admin="true"]) {
          min-height: 100vh;
        }
      `;
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
