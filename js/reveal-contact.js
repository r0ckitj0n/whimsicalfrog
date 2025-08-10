// Simple human verification reveal for email/phone/address
// Usage: markup adds a .wf-reveal element with a button having data-wf-reveal and data-enc (base64)

(function initReveal() {
  function b64decode(str) {
    try {
      return decodeURIComponent(escape(atob(str)));
    } catch (e) {
      try { return atob(str); } catch (e2) { return ''; }
    }
  }

  function createChallenge(container) {
    const a = Math.floor(Math.random() * 8) + 1; // 1-9
    const b = Math.floor(Math.random() * 8) + 1; // 1-9
    container.dataset.ans = String(a + b);
    return `${a} + ${b}`;
  }

  function handleRevealClick(ev) {
    const btn = ev.currentTarget;
    const container = btn.closest('.wf-reveal');
    if (!container) return;

    // Already solved
    if (container.classList.contains('is-revealed')) return;

    // Toggle challenge UI
    const chall = container.querySelector('.wf-reveal-challenge');
    if (!chall) return;

    const q = createChallenge(container);
    const qLabel = chall.querySelector('[data-q]');
    const input = chall.querySelector('.wf-reveal-input');
    const submit = chall.querySelector('.wf-reveal-submit');

    if (qLabel) qLabel.textContent = q + ' = ?';
    container.classList.add('is-challenging');
    input && input.focus();

    function onSubmit() {
      const expected = container.dataset.ans || '';
      const got = (input && input.value || '').trim();
      if (got === expected) {
        // Decode value and display
        const enc = btn.getAttribute('data-enc') || '';
        const type = btn.getAttribute('data-wf-reveal') || 'text';
        const val = b64decode(enc);
        const out = container.querySelector('.wf-reveal-value');
        if (out) {
          // Render as actionable link when possible
          // Create elements programmatically to avoid HTML injection
          let linkEl = null;
          const safeText = String(val || '').trim();
          if (type === 'email' && safeText) {
            linkEl = document.createElement('a');
            linkEl.href = 'mailto:' + safeText;
            linkEl.textContent = safeText;
            linkEl.className = 'wf-reveal-link';
          } else if (type === 'phone' && safeText) {
            const tel = safeText.replace(/[^\d+]/g, '');
            linkEl = document.createElement('a');
            linkEl.href = 'tel:' + tel;
            linkEl.textContent = safeText;
            linkEl.className = 'wf-reveal-link';
          } else if (type === 'address' && safeText) {
            linkEl = document.createElement('a');
            linkEl.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(safeText);
            linkEl.textContent = safeText;
            linkEl.target = '_blank';
            linkEl.rel = 'noopener';
            linkEl.className = 'wf-reveal-link';
          }

          out.textContent = '';
          if (linkEl) {
            out.appendChild(linkEl);
          } else {
            // Fallback to plain text
            out.textContent = safeText;
          }
          out.hidden = false;
        }
        btn.remove();
        chall.remove();
        container.classList.remove('is-challenging');
        container.classList.add('is-revealed');
      } else {
        // new challenge
        const q2 = createChallenge(container);
        if (qLabel) qLabel.textContent = q2 + ' = ?';
        if (input) {
          input.value = '';
          input.focus();
        }
      }
    }

    submit && submit.addEventListener('click', onSubmit, { once: true });
    input && input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        onSubmit();
      }
    }, { once: true });
  }

  function setup() {
    document.querySelectorAll('.wf-reveal .wf-reveal-btn[data-wf-reveal]').forEach(btn => {
      btn.addEventListener('click', handleRevealClick);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup, { once: true });
  } else {
    setup();
  }
})();
