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

      setStatus('Sending...', 'info');
      disable(true);

      try {
        const res = await fetch('/api/contact_submit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            csrf: data.csrf,
            website: data.website || '', // Honeypot passthrough
            name,
            email,
            subject,
            message,
          }),
        });

        const json = await res.json().catch(() => ({ success: false, error: 'Invalid server response' }));

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
