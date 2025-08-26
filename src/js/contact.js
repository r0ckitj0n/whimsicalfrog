// Contact page JS: AJAX submit via Vite-managed module

(function initContactForm() {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  ready(() => {
    const form = document.getElementById('wf-contact-form');
    if (!form) return; // Only run on contact page

    const statusEl = document.getElementById('wf-contact-status');
    const submitBtn = document.getElementById('wf-contact-submit');

    // Simple math CAPTCHA modal reused from reveal-company styles
    let overlay = null;
    let modal = null;
    function ensureCaptchaElements() {
      if (overlay && modal) return;
      overlay = document.createElement('div');
      overlay.className = 'wf-revealco-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');

      modal = document.createElement('div');
      modal.className = 'wf-revealco-modal';
      modal.innerHTML = `
        <div class="wf-revealco-card">
          <button type="button" class="wf-revealco-close" aria-label="Close">×</button>
          <h3 class="wf-revealco-title">Verify you're human</h3>
          <div class="wf-revealco-body" id="wfContactCaptchaBody"></div>
        </div>
      `;
      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      overlay.addEventListener('click', (e) => { if (e.target === overlay) closeCaptcha(); });
      modal.querySelector('.wf-revealco-close').addEventListener('click', closeCaptcha);
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && overlay.classList.contains('show')) closeCaptcha(); });
    }

    function openCaptcha() {
      ensureCaptchaElements();
      const body = modal.querySelector('#wfContactCaptchaBody');
      if (!body) return Promise.reject(new Error('captcha-missing'));

      const a = Math.floor(Math.random() * 8) + 1;
      const b = Math.floor(Math.random() * 8) + 1;
      const expected = String(a + b);

      body.innerHTML = `
        <div class="wf-revealco-challenge">
          <p class="wf-revealco-hint">Quick check to prevent spam. Please solve:</p>
          <label class="wf-revealco-q">
            <span class="wf-revealco-qtext">${a} + ${b} =</span>
            <input type="text" inputmode="numeric" class="wf-revealco-input" aria-label="Answer" />
          </label>
          <div class="wf-revealco-actions">
            <button type="button" class="wf-btn wf-btn-primary" data-action="ok">Submit</button>
            <button type="button" class="wf-btn" data-action="cancel">Cancel</button>
          </div>
          <p class="wf-revealco-status" aria-live="polite"></p>
        </div>
      `;

      const input = body.querySelector('.wf-revealco-input');
      const status = body.querySelector('.wf-revealco-status');

      overlay.classList.add('show');
      // Lock page scroll using shared modal manager if present
      try { window.WFModals?.lockScroll?.(); } catch (_) {}
      if (input) input.focus();

      return new Promise((resolve, reject) => {
        function cleanup() {
          body.removeEventListener('click', onClick);
        }
        function onClick(e) {
          const btn = e.target.closest('button');
          if (!btn) return;
          const action = btn.getAttribute('data-action');
          if (action === 'cancel') {
            cleanup();
            closeCaptcha();
            reject(new Error('captcha-cancelled'));
            return;
          }
          if (action === 'ok') {
            const val = (input && input.value || '').trim();
            if (val === expected) {
              cleanup();
              closeCaptcha();
              resolve(true);
            } else {
              if (status) status.textContent = 'Incorrect, please try again.';
              if (input) { input.value = ''; input.focus(); }
            }
          }
        }
        body.addEventListener('click', onClick);
      });
    }

    function closeCaptcha() {
      if (!overlay) return;
      overlay.classList.remove('show');
      try { window.WFModals?.unlockScrollIfNoneOpen?.(); } catch (_) {}
    }

    function setStatus(text, type = 'info') {
      if (!statusEl) return;
      statusEl.textContent = text;
      statusEl.className = 'text-sm ' + (type === 'error' ? 'text-red-600' : type === 'success' ? 'text-green-700' : 'text-gray-700');
    }

    function disable(disabled) {
      if (submitBtn) submitBtn.disabled = disabled;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const data = Object.fromEntries(new FormData(form).entries());

      // Client-side validation
      const name = (data.name || '').trim();
      const email = (data.email || '').trim();
      const subject = (data.subject || '').trim();
      const message = (data.message || '').trim();

      if (!name || name.length > 100) {
        setStatus('Please enter your name (max 100 characters).', 'error');
        return;
      }
      if (!email || email.length > 255 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setStatus('Please enter a valid email address.', 'error');
        return;
      }
      if (subject && subject.length > 150) {
        setStatus('Subject is too long (max 150 characters).', 'error');
        return;
      }
      if (!message || message.length > 5000) {
        setStatus('Please enter a message (max 5000 characters).', 'error');
        return;
      }

      // Human check before submit
      try {
        await openCaptcha();
      } catch (_) {
        setStatus('Submission cancelled.', 'info');
        return;
      }

      setStatus('Sending...', 'info');
      disable(true);

      try {
        const res = await fetch('/api/contact_submit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            csrf: data.csrf,
            website: data.website || '', // Honeypot passthrough
            name,
            email,
            subject,
            message,
          }),
        });

        const raw = await res.text();
        let json;
        try {
          json = JSON.parse(raw);
        } catch (_) {
          console.error('[Contact] Non-JSON response from server:', raw);
          json = { success: false, error: 'Invalid server response' };
        }

        if (res.ok && json.success) {
          setStatus(json.message || 'Thanks! Your message has been sent.', 'success');
          form.reset();
        } else {
          setStatus(json.error || 'Failed to send your message. Please try again later.', 'error');
        }
      } catch (err) {
        setStatus('Network error. Please check your connection and try again.', 'error');
      } finally {
        disable(false);
      }
    });
  });
})();
