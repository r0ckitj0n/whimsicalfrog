// Login Modal and Intercepted Login Flow
// - Opens compact modal when clicking elements with data-action="open-login-modal"
// - Submits credentials to /functions/process_login.php
// - Shows success notification and redirects to the page user started from
// - Also intercepts existing /login page form for consistent UX

(function initLoginModal() {
  try { console.log('[LoginModal] init'); } catch(_) {}
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
    try { window.WFModalUtils && window.WFModalUtils.ensureOnBody && window.WFModalUtils.ensureOnBody(overlay); } catch(_) {}
    overlay.classList.add('show');
    try { overlay.setAttribute('aria-hidden', 'false'); } catch(_) {}
    // Apply standardized scroll lock via centralized helper
    WFModals?.lockScroll?.();
    const firstInput = modal.querySelector('input[name="username"]');
    if (firstInput) firstInput.focus();
  }

  function closeModal() {
    if (!overlay) return;
    overlay.classList.remove('show');
    try { overlay.setAttribute('aria-hidden', 'true'); } catch(_) {}
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
      const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : window.location.origin;
      const loginUrl = new URL('/functions/process_login.php', backendOrigin).toString();
      const res = await fetch(loginUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
        credentials: 'include'
      });

      if (!res.ok) {
        const err = await safeJson(res);
        throw new Error(err?.error || 'Login failed.');
      }

      const data = await safeJsonOk(res);
      let resolvedUserId = (data && data.userId != null) ? data.userId : undefined;
      // Fallback: if login response had no userId (e.g., proxy/content-type issues), query session
      if (resolvedUserId == null) {
        try {
          const whoUrl = new URL('/api/whoami.php', backendOrigin).toString();
          const who = await fetch(whoUrl, { credentials: 'include' }).then(r => r.ok ? r.json() : null);
          const sid = who?.userId;
          if (sid != null) resolvedUserId = sid;
        } catch (_) {}
      }
      // Choose redirect target: server-provided > sessionStorage > current
      const serverRedirect = data?.redirectUrl;
      let target = serverRedirect;
      if (!target) {
        try { target = sessionStorage.getItem('wf_login_return_to'); } catch (_) {}
      }
      if (!target) target = window.location.pathname || '/';

      // Normalize and mark client-side state as logged-in for in-page flows
      try {
        if (document && document.body) {
          document.body.setAttribute('data-is-logged-in', 'true');
          // Also expose user id so payment-modal can operate without full page reload
          const n = Number(resolvedUserId);
          if (Number.isFinite(n) && n > 0) {
            document.body.setAttribute('data-user-id', String(n));
            resolvedUserId = n;
          } else {
            resolvedUserId = undefined;
          }
        }
      } catch (_) {}

      // Notify listeners about successful login
      try {
        window.dispatchEvent(new CustomEvent('wf:login-success', {
          detail: {
            serverRedirect,
            target,
            userId: (resolvedUserId != null) ? resolvedUserId : undefined,
            username: data?.username,
            role: data?.role,
          }
        }));
        // Also refresh cart state and notify globally so any open cart UI re-renders
        try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
        try {
          window.dispatchEvent(new CustomEvent('cartUpdated', {
            detail: { action: 'auth', state: window.WF_Cart?.getState?.() }
          }));
        } catch(_) {}
      } catch (_) {}

      // Invoke optional callback if provided
      try {
        if (lastOpenOptions && typeof lastOpenOptions.onSuccess === 'function') {
          lastOpenOptions.onSuccess(target, serverRedirect);
        }
      } catch (_) {}

      // Optionally suppress redirect to keep user on current page (e.g., continue checkout flow in-place)
      // Also allow URL param ?wf_debug_login=1 to force in-place flow for debugging
      const params = new URLSearchParams(window.location.search || '');
      const suppress = !!(lastOpenOptions && lastOpenOptions.suppressRedirect === true) || params.get('wf_debug_login') === '1';
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

  // Parse JSON safely on success responses. Tolerates empty body or non-JSON content.
  async function safeJsonOk(res) {
    try {
      const ct = (res.headers && res.headers.get && res.headers.get('content-type')) || '';
      const text = await res.text();
      const trimmed = (text || '').trim();
      if (!ct.includes('application/json')) return {};
      if (!trimmed) return {};
      try { return JSON.parse(trimmed); } catch (_) { return {}; }
    } catch (_) {
      return {};
    }
  }

  // Delegated listener for header login links (capture phase)
  document.addEventListener('click', (e) => {
    const link = e.target && e.target.closest ? e.target.closest('[data-action="open-login-modal"]') : null;
    if (!link) return;
    // Only intercept left click without modifier keys
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    try { console.log('[LoginModal] intercept click on login link'); } catch(_) {}
    e.preventDefault();
    e.stopPropagation();
    try { if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); } catch(_) {}
    const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
    openModal(desiredReturn);
  }, true);

  // Intercept native /login page form if present for consistent UX
  function hookInlineLoginPage() {
    const pageForm = document.getElementById('loginForm');
    if (!pageForm) return;

    // Prevent duplicate listener attachments from other modules
    if (pageForm.dataset.wfLoginHandler === 'true') return;
    pageForm.dataset.wfLoginHandler = 'true';

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
        const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : window.location.origin;
        const loginUrl = new URL('/functions/process_login.php', backendOrigin).toString();
        const res = await fetch(loginUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password }),
          credentials: 'include'
        });

        if (!res.ok) {
          const err = await safeJson(res);
          throw new Error(err?.error || 'Login failed.');
        }

        const data = await safeJsonOk(res);
        const serverRedirect = data?.redirectUrl;
        let target = serverRedirect;
        if (!target) {
          try { target = sessionStorage.getItem('wf_login_return_to'); } catch (_) {}
        }
        if (!target) target = '/';

        // Standardize events and cart refresh for inline login flow as well
        try {
          window.dispatchEvent(new CustomEvent('wf:login-success', {
            detail: {
              serverRedirect,
              target,
              userId: (data && data.userId != null) ? data.userId : undefined,
              username: data?.username,
              role: data?.role,
            }
          }));
        } catch(_) {}
        try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
        try {
          window.dispatchEvent(new CustomEvent('cartUpdated', {
            detail: { action: 'auth', state: window.WF_Cart?.getState?.() }
          }));
        } catch(_) {}

        // Fallback: if inline login didn't return a userId, query backend session explicitly
        try {
          if (data?.userId == null) {
            const whoUrl = new URL('/api/whoami.php', backendOrigin).toString();
            const who = await fetch(whoUrl, { credentials: 'include' }).then(r => r.ok ? r.json() : null);
            const sid = who?.userId;
            const n = Number(sid);
            if (Number.isFinite(n) && n > 0) {
              try { if (document && document.body) document.body.setAttribute('data-user-id', String(n)); } catch(_) {}
              try {
                window.dispatchEvent(new CustomEvent('wf:login-success', {
                  detail: { serverRedirect, target, userId: n, username: data?.username, role: data?.role }
                }));
              } catch(_) {}
            }
          }
        } catch(_) {}

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

  // Auto-open modal when ?wf_debug_login=1 is present (to simplify debugging/testing)
  try {
    const __params = new URLSearchParams(window.location.search || '');
    if (__params.get('wf_debug_login') === '1') {
      const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => openModal(desiredReturn), { once: true });
      } else {
        openModal(desiredReturn);
      }
    }
  } catch (_) {}

  // Expose for manual triggers
  window.openLoginModal = openModal;
})();
