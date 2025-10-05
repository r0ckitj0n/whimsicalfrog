// Admin health checks and minimal toasts
// - Notifies admins of missing backgrounds or item images without masking issues

function isAdminRoute() {
  try {
    const body = document.body;
    const ds = body ? body.dataset : {};
    if (ds && ds.isAdmin === 'true') return true;
    const path = window.location && window.location.pathname ? window.location.pathname : '';
    return /^\/?admin(\/|$)/i.test(path);
  } catch (_) {
    return false;
  }
}

function _getPageSlug() {
  const ds = document.body ? document.body.dataset : {};
  return (ds && ds.page) ? ds.page : '';
}

function showToast(type, title, message) {
  try {
    if (window.showNotification) {
      window.showNotification(message, type, { title, duration: 5000 });
      return;
    }
    if (window.showToast) { window.showToast(type, message); return; }
    console[(type === 'error' ? 'error' : 'log')](title ? `${title}: ${message}` : message);
  } catch (_) {}
}

async function checkBackgrounds() {
  try {
    const res = await fetch('/api/health_backgrounds.php', { credentials: 'include', headers: { 'X-Requested-With':'XMLHttpRequest' } });
    if (!res.ok) return;
    const data = await res.json().catch(() => null);
    if (!data || data.success !== true || !data.data) return;
    const missingActive = Array.isArray(data.data.missingActive) ? data.data.missingActive : [];
    const missingFiles  = Array.isArray(data.data.missingFiles)  ? data.data.missingFiles  : [];
    if (missingActive.length) {
      showToast('warning', 'Backgrounds Missing', `No active background configured for room(s): ${missingActive.join(', ')}`);
    }
    if (missingFiles.length) {
      showToast('error', 'Background Files Missing', `Missing image files for room(s): ${missingFiles.join(', ')}`);
    }
  } catch (_) { /* silent */ }
}

async function checkItems() {
  try {
    const res = await fetch('/api/health_items.php', { credentials: 'include', headers: { 'X-Requested-With':'XMLHttpRequest' } });
    if (!res.ok) return;
    const data = await res.json().catch(() => null);
    if (!data || data.success !== true || !data.data) return;
    const counts = data.data.counts || {};
    const noPrimary = counts.noPrimary || 0;
    const missingFiles = counts.missingFiles || 0;
    if (noPrimary > 0) {
      showToast('warning', 'Items Missing Primary Image', `${noPrimary} item(s) have no primary image configured.`);
    }
    if (missingFiles > 0) {
      showToast('error', 'Item Image Files Missing', `${missingFiles} item(s) have a missing image file on disk.`);
    }
  } catch (_) {}
}

function checkPageBackground() {
  try {
    const ds = document.body ? document.body.dataset : {};
    const page = (ds && ds.page) || '';
    const bg = (document.body && document.body.getAttribute('data-bg-url')) || '';
    const strict = !!(window.WF_STRICT_FAILFAST !== false); // default true
    if (!strict) return; // only toast in strict mode to aid rollout
    const needsBg = page === 'landing' || page === 'room_main' || page === 'shop' || page === 'about' || page === 'contact' || /^room\d+$/.test(page);
    if (needsBg && !bg) {
      showToast('warning', 'No Background Applied', `No background configured for ${page}.`);
    }
  } catch (_) {}
}

function checkMissingItemImages() {
  try {
    if (!document.querySelector('.wf-missing-item-image')) return;
    showToast('info', 'Missing Item Images', 'Some items on this page have no images configured.');
  } catch (_) {}
}

function init() {
  if (!isAdminRoute()) return;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      checkPageBackground();
      checkMissingItemImages();
      checkBackgrounds();
      checkItems();
    }, { once: true });
  } else {
    checkPageBackground();
    checkMissingItemImages();
    checkBackgrounds();
    checkItems();
  }
}

init();
