// Login Modal and Intercepted Login Flow
// - Opens compact modal when clicking elements with data-action="open-login-modal"
// - Submits credentials to /functions/process_login.php
// - Shows success notification and redirects to the page user started from
// - Also intercepts existing /login page form for consistent UX

(function initLoginModal() {
  let overlay = null;
  let modal = null;
  let returnTo = null;
  let lastOpenOptions = {};

  function ensureElements() {
    if (overlay && modal) return;

    overlay = document.createElement('div');
    overlay.id = 'wf-login-overlay';
    overlay.className = 'wf-login-overlay';

    modal = document.createElement('div');
    modal.id = 'wf-login-modal';
    modal.className = 'wf-login-modal';
    modal.innerHTML = `
      <div class="wf-login-card">
        <button type="button" class="wf-login-close" aria-label="Close">×</button>
        <h3 class="wf-login-title">Sign in</h3>
        <form id="wfLoginForm" class="wf-login-form">
          <label class="wf-field">
            <span class="wf-label">Username</span>
            <input type="text" name="username" autocomplete="username" required class="wf-input" />
          </label>
          <label class="wf-field">
            <span class="wf-label">Password</span>
            <input type="password" name="password" autocomplete="current-password" required class="wf-input" />
          </label>
          <button type="submit" class="wf-btn wf-btn-primary">Login</button>
        </form>
      </div>`;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Close interactions
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeModal();
    });
    modal.querySelector('.wf-login-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('show')) closeModal();
    });

    // Submit handler
    const form = modal.querySelector('#wfLoginForm');
    form.addEventListener('submit', onSubmitModalForm);
  }

  function openModal(desiredReturn, opts) {
    ensureElements();
    lastOpenOptions = opts || {};
    returnTo = desiredReturn || window.location.pathname + window.location.search + window.location.hash;
    try { sessionStorage.setItem('wf_login_return_to', returnTo); } catch (_) {}
    overlay.classList.add('show');
    // Apply standardized scroll lock via centralized helper
    WFModals?.lockScroll?.();
    const firstInput = modal.querySelector('input[name="username"]');
    if (firstInput) firstInput.focus();
  }

  function closeModal() {
    if (!overlay) return;
    overlay.classList.remove('show');
    // Remove scroll lock only if no other modals are open
    WFModals?.unlockScrollIfNoneOpen?.();
  }

  async function onSubmitModalForm(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const username = form.username.value.trim();
    const password = form.password.value;
    if (!username || !password) {
      if (window.showValidation) window.showValidation('Please enter username and password.');
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Logging in…';

    try {
      const res = await fetch('/functions/process_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
      });

      if (!res.ok) {
        const err = await safeJson(res);
        throw new Error(err?.error || 'Login failed.');
      }

      const data = await res.json();
      // Choose redirect target: server-provided > sessionStorage > current
      const serverRedirect = data?.redirectUrl;
      let target = serverRedirect;
      if (!target) {
        try { target = sessionStorage.getItem('wf_login_return_to'); } catch (_) {}
      }
      if (!target) target = window.location.pathname || '/';

      // Mark client-side state as logged-in for in-page flows
      try { if (document && document.body) document.body.setAttribute('data-is-logged-in', 'true'); } catch (_) {}

      // Notify listeners about successful login
      try { window.dispatchEvent(new CustomEvent('wf:login-success', { detail: { serverRedirect, target } })); } catch (_) {}

      // Invoke optional callback if provided
      try {
        if (lastOpenOptions && typeof lastOpenOptions.onSuccess === 'function') {
          lastOpenOptions.onSuccess(target, serverRedirect);
        }
      } catch (_) {}

      // Optionally suppress redirect to keep user on current page (e.g., continue checkout flow in-place)
      const suppress = !!(lastOpenOptions && lastOpenOptions.suppressRedirect === true);
      if (suppress) {
        if (window.showSuccess) window.showSuccess('Login successful.');
        closeModal();
      } else {
        if (window.showSuccess) window.showSuccess('Login successful. Redirecting…');
        closeModal();
        setTimeout(() => { window.location.assign(target); }, 700);
      }
    } catch (err) {
      if (window.showError) window.showError(err.message || 'Login failed.');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Login';
    }
  }

  async function safeJson(res) {
    try { return await res.json(); } catch (_) { return null; }
  }

  // Delegated listener for header login links
  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-action="open-login-modal"]');
    if (!link) return;
    // Only intercept left click without modifier keys
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
    openModal(desiredReturn);
  });

  // Intercept native /login page form if present for consistent UX
  function hookInlineLoginPage() {
    const pageForm = document.getElementById('loginForm');
    if (!pageForm) return;

    pageForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = (pageForm.querySelector('#username') || {}).value?.trim();
      const password = (pageForm.querySelector('#password') || {}).value;
      if (!username || !password) {
        if (window.showValidation) window.showValidation('Please enter username and password.');
        return;
      }

      // Prefer redirect_to from query string if provided
      const params = new URLSearchParams(window.location.search);
      const qsRedirect = params.get('redirect_to');
      try { sessionStorage.setItem('wf_login_return_to', qsRedirect || (window.location.pathname || '/')); } catch (_) {}

      try {
        const res = await fetch('/functions/process_login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });

        if (!res.ok) {
          const err = await safeJson(res);
          throw new Error(err?.error || 'Login failed.');
        }

        const data = await res.json();
        const serverRedirect = data?.redirectUrl;
        let target = serverRedirect;
        if (!target) {
          try { target = sessionStorage.getItem('wf_login_return_to'); } catch (_) {}
        }
        if (!target) target = '/';

        if (window.showSuccess) window.showSuccess('Login successful. Redirecting…');
        setTimeout(() => { window.location.assign(target); }, 700);
      } catch (err) {
        if (window.showError) window.showError(err.message || 'Login failed.');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hookInlineLoginPage);
  } else {
    hookInlineLoginPage();
  }

  // Expose for manual triggers
  window.openLoginModal = openModal;
})();
