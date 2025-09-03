// Login page handler for /login
// Wires the standalone login form to the session-based endpoint and handles redirect


(function initLoginPage() {
  try {
    const root = document.getElementById('loginPage');
    const form = document.getElementById('loginForm');
    const errorEl = document.getElementById('errorMessage');

    if (!root || !form) return; // Not on login page

    const loginUrl = new URL('/functions/process_login.php', window.__WF_BACKEND_ORIGIN || window.location.origin).toString();
    const whoUrl = new URL('/api/whoami.php', window.__WF_BACKEND_ORIGIN || window.location.origin).toString();

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

    async function resolveRedirect(fallback) {
      // Prefer server-provided redirectAfterLogin via whoami (not provided),
      // but process_login.php returns redirectUrl; caller passes it.
      // As a backup, try current location or provided fallback.
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
        const res = await fetch(loginUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ username, password })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok) {
          showError(data?.error || 'Invalid username or password.');
          return;
        }

        // Update body attrs immediately
        const userId = Number(data?.userId);
        if (document && document.body) {
          document.body.setAttribute('data-is-logged-in', 'true');
          if (Number.isFinite(userId) && userId > 0) {
            document.body.setAttribute('data-user-id', String(userId));
          }
        }

        // Dispatch login event for any listeners
        try {
          window.dispatchEvent(new CustomEvent('wf:login-success', { detail: { userId } }));
        } catch {}

        // Attempt whoami to ensure session is set
        try {
          await fetch(whoUrl, { credentials: 'include' }).then(r => r.ok ? r.json() : null);
        } catch {}

        // Redirect back to prior page if provided, otherwise /payment if coming from checkout, else home
        const redirectUrl = data?.redirectUrl || (document.referrer?.includes('/payment') ? '/payment' : null) || await resolveRedirect('/');
        window.location.assign(redirectUrl);
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
