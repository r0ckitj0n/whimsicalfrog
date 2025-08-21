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

    // Confirm delete actions
    document.querySelectorAll('form.admin-secret-delete-form').forEach((form) => {
      form.addEventListener('submit', (e) => {
        const key = form.getAttribute('data-key') || '';
        const ok = window.confirm(`Delete secret "${key}"? This cannot be undone.`);
        if (!ok) e.preventDefault();
      });
    });
  });
})();
