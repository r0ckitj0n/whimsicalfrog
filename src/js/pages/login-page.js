// Login page handler for /login
// Wires the standalone login form to the session-based endpoint and handles redirect

import { ApiClient } from '../../core/api-client.js';

(function initLoginPage() {
  try {
    const root = document.getElementById('loginPage');
    const form = document.getElementById('loginForm');
    const errorEl = document.getElementById('errorMessage');

    if (!root || !form) return; // Not on login page

    const loginUrl = new URL('/functions/process_login.php', window.__WF_BACKEND_ORIGIN || window.location.origin).toString();
    const whoUrl = new URL('/api/whoami.php', window.__WF_BACKEND_ORIGIN || window.location.origin).toString();
    const urlParams = new URLSearchParams(window.location.search || '');
    const qsRedirect = urlParams.get('redirect_to');

    function showError(msg) {
      if (!errorEl) return;
      errorEl.textContent = msg || 'Login failed. Please try again.';
      errorEl.classList.remove('hidden');
    }

    function hideError() {
      if (!errorEl) return;
      errorEl.textContent = '';
      errorEl.classList.add('hidden');
    }

    function sanitizeRedirect(target) {
      try {
        if (!target || typeof target !== 'string') return null;
        // If absolute URL, only allow same-origin; convert to relative path
        if (/^https?:\/\//i.test(target) || target.startsWith('//')) {
          const u = new URL(target, window.location.origin);
          if (u.host !== window.location.host) return null;
          target = u.pathname + u.search + u.hash;
        }
        // Only allow relative paths; default to '/'
        if (!target.startsWith('/')) return '/';
        // Avoid redirecting back to /login
        if (/^\/login(\/?|\?|#|$)/i.test(target)) return '/';
        return target;
      } catch (_) {
        return null;
      }
    }

    async function resolveRedirect(fallback) {
      // Priority: sessionStorage (set below) > sanitized referrer > explicit fallback
      try {
        const ss = sessionStorage.getItem('wf_login_return_to');
        const ssz = sanitizeRedirect(ss);
        if (ssz) return ssz;
      } catch (_) {}
      const ref = sanitizeRedirect(document.referrer || '');
      if (ref) return ref;
      return fallback || '/';
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideError();

      const username = (document.getElementById('username') || {}).value?.trim();
      const password = (document.getElementById('password') || {}).value || '';

      if (!username || !password) {
        showError('Please enter your username and password.');
        return;
      }

      const btn = document.getElementById('loginButton');
      const prevDisabled = btn ? btn.disabled : undefined;
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Signing in…';
      }

      try {
        // Persist intended return target for after-login redirect
        try {
          const candidate = qsRedirect || document.referrer || '/';
          const clean = sanitizeRedirect(candidate) || '/';
          sessionStorage.setItem('wf_login_return_to', clean);
        } catch (_) {}

        const data = await ApiClient.request(loginUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password }),
        });

        // Update body attrs immediately
        const userId = Number(data?.userId);
        if (document && document.body) {
          document.body.setAttribute('data-is-logged-in', 'true');
          if (Number.isFinite(userId) && userId > 0) {
            document.body.setAttribute('data-user-id', String(userId));
          }
        }

        // Attempt whoami to ensure session is set
        try {
          await ApiClient.request(whoUrl, { method: 'GET' }).catch(() => null);
        } catch {}

        // Choose redirect: server-provided > sessionStorage > sanitized referrer > fallback '/'
        let redirectUrl = data?.redirectUrl;
        if (!redirectUrl) {
          try { redirectUrl = sessionStorage.getItem('wf_login_return_to'); } catch (_) {}
        }
        if (!redirectUrl) {
          const ref = sanitizeRedirect(document.referrer || '');
          if (ref) redirectUrl = ref;
        }
        if (!redirectUrl) {
          redirectUrl = await resolveRedirect('/');
        }
        redirectUrl = sanitizeRedirect(redirectUrl) || '/';

        // Publish the intended redirect so global header listener can use it
        try { window.__wf_desired_return_url = redirectUrl; } catch(_) {}

        // Now dispatch login-success for any listeners (header may trigger sealing)
        try {
          window.dispatchEvent(new CustomEvent('wf:login-success', { detail: { userId, target: redirectUrl } }));
        } catch {}

        // Seal cookies via server-side endpoint for reliable persistence (redundant to header safety, but harmless)
        try {
          const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : window.location.origin;
          const seal = new URL('/api/seal_login.php', backendOrigin);
          seal.searchParams.set('to', redirectUrl || '/');
          window.location.assign(seal.toString());
        } catch (_) {
          window.location.assign(redirectUrl);
        }
      } catch (err) {
        console.error('[LoginPage] Login error', err);
        showError('An unexpected error occurred. Please try again.');
      } finally {
        if (btn) {
          btn.disabled = prevDisabled || false;
          btn.textContent = 'Login';
        }
      }
    }, { passive: false });
  } catch (e) {
    console.error('[LoginPage] init error', e);
  }
})();
