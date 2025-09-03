// Header auth UI sync: makes header reflect login state without full page reload
// - Listens for wf:login-success
// - Updates body data attributes (defensive)
// - Swaps Login link -> Logout + username
// - Nudges cart header counters to refresh

(function initHeaderAuthSync() {
  if (window.__wfHeaderAuthSync) return;
  window.__wfHeaderAuthSync = true;

  function updateHeaderUI(detail) {
    try {
      // Ensure body attributes are set
      if (document && document.body) {
        document.body.setAttribute('data-is-logged-in', 'true');
        if (detail && detail.userId != null) {
          document.body.setAttribute('data-user-id', String(detail.userId));
        }
      }
    } catch (_) {}

    try {
      // Replace Login link with Logout link and optional username
      const header = document.querySelector('.site-header .header-right');
      const loginLink = header && header.querySelector('a.nav-link[data-action="open-login-modal"]');
      if (loginLink) {
        const username = (detail && detail.username) ? String(detail.username) : null;
        // Insert username link before logout if available
        if (username) {
          const userA = document.createElement('a');
          userA.href = '/account_settings';
          userA.className = 'nav-link';
          userA.textContent = username;
          loginLink.parentNode.insertBefore(userA, loginLink);
        }
        loginLink.textContent = 'Logout';
        loginLink.href = '/logout.php';
        loginLink.removeAttribute('data-action');
      }
    } catch (_) {}

    // Nudge cart header counters
    try { window.WF_Cart?.refreshFromStorage?.(); } catch (_) {}
    try { window.WF_Cart?.updateCartDisplay?.(); } catch (_) {}
  }

  // If body already indicates logged-in on load, ensure header is in sync (e.g., after server-side login)
  function syncOnLoadIfNeeded() {
    try {
      const isLoggedIn = document?.body?.dataset?.isLoggedIn === 'true';
      if (!isLoggedIn) return;
      updateHeaderUI({ userId: document.body.dataset.userId });
    } catch (_) {}
  }

  window.addEventListener('wf:login-success', (e) => {
    updateHeaderUI(e?.detail || {});
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncOnLoadIfNeeded, { once: true });
  } else {
    syncOnLoadIfNeeded();
  }
})();
