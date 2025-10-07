// Footer Newsletter Module (Vite-managed)
// Attaches AJAX submit handlers to
import { ApiClient } from '../core/api-client.js';

(function initFooterNewsletter() {
  if (window.__WF_FOOTER_NEWSLETTER_INIT__) return;
  window.__WF_FOOTER_NEWSLETTER_INIT__ = true;

  function attach(form) {
    if (!form || form.dataset.wfNewsletterInit === 'true') return;
    form.dataset.wfNewsletterInit = 'true';

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const input = this.querySelector('input[name="email"]');
      const button = this.querySelector('.footer-newsletter-button');
      if (!input || !button) return;

      const email = input.value.trim();
      const originalText = button.textContent;

      // Loading state
      button.textContent = 'Subscribing...';
      button.disabled = true;

      ApiClient.post('/api/newsletter_signup.php', { email })
        .then(data => {
          if (data && data.success) {
            button.textContent = 'Subscribed!';
            button.classList.add('footer-newsletter-success');
            input.value = '';
            setTimeout(() => {
              button.textContent = originalText;
              button.disabled = false;
              button.classList.remove('footer-newsletter-success');
            }, 3000);
          } else {
            throw new Error((data && (data.message || data.error)) || 'Subscription failed');
          }
        })
        .catch(err => {
          console.error('[newsletter] signup error:', err);
          button.textContent = 'Try Again';
          button.disabled = false;
          button.classList.add('footer-newsletter-error');
          setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('footer-newsletter-error');
          }, 3000);
        });
    }, { once: false });
  }

  function init() {
    document.querySelectorAll('.footer-newsletter-form').forEach(attach);
    console.log('[footer-newsletter] Initialized');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
