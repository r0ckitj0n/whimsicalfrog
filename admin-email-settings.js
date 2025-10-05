// Admin Email Settings Vite module
// Reads the current alert message from the DOM (if present) and shows a toast via available globals.
(function(){
  const alertEl = document.querySelector('.admin-alert');
  if (!alertEl) return;
  try {
    const msg = alertEl.textContent.trim();
    const typeClass = Array.from(alertEl.classList).find(c => c.startsWith('alert-')) || '';
    let toastType = 'info';
    if (typeClass.includes('success')) toastType = 'success';
    else if (typeClass.includes('error')) toastType = 'error';
    else if (typeClass.includes('warning')) toastType = 'warning';

    let show;
    if (window.wfNotifications && typeof window.wfNotifications.show === 'function') {
      show = (m, t, o) => window.wfNotifications.show(m, t, o || {});
    } else if (typeof window.showNotification === 'function') {
      show = (m, t, o) => window.showNotification(m, t, o || {});
    } else if (toastType === 'success' && typeof window.showSuccess === 'function') {
      show = (m) => window.showSuccess(m);
    } else if (toastType === 'error' && typeof window.showError === 'function') {
      show = (m) => window.showError(m);
    }

    if (typeof show === 'function' && msg) {
      requestAnimationFrame(() => {
        try { show(msg, toastType, { title: toastType === 'success' ? 'Email Test Sent' : 'Email Test' }); } catch(e) {}
      });
    }
  } catch(e) {
    // no-op
  }
})();
