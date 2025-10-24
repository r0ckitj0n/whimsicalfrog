// Admin Secrets page enhancements
(function initAdminSecrets() {
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  onReady(() => {
    const body = document.body;
    const ds = body ? body.dataset : {};
    const isAdmin = (ds && ds.isAdmin === 'true') || false;
    const page = (ds && ds.page) || '';
    if (!isAdmin || typeof page !== 'string' || !page.startsWith('admin')) return;

    // Only run on /admin/secrets
    const path = (ds && ds.path) || window.location.pathname || '';
    if (!/\/admin(\/|%2F)?secrets(\b|\/|$)/i.test(path)) return;

    // Confirm delete actions (branded)
    document.querySelectorAll('form.admin-secret-delete-form').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        const key = form.getAttribute('data-key') || '';
        if (typeof window.showConfirmationModal !== 'function') {
          try { window.showNotification && window.showNotification('Confirmation UI unavailable. Action canceled.', 'error'); } catch(_) {}
          e.preventDefault();
          return;
        }
        const ok = await window.showConfirmationModal({ title: 'Delete Secret', message: `Delete secret "${key}"? This cannot be undone.`, confirmText: 'Delete', confirmStyle: 'danger', icon: '⚠️', iconType: 'danger' });
        if (!ok) e.preventDefault();
      });
    });
  });
})();
