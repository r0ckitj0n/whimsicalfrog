// Reveal Company Information Modal (Vite-managed)
// Replaces per-field reveal buttons with a single, CAPTCHA-protected modal.

(function initRevealCompanyModal() {
  let overlay = null;
  let modal = null;

  function b64decode(str) {
    try {
      return decodeURIComponent(escape(atob(str)));
    } catch (e) {
      try { return atob(str); } catch (e2) { return ''; }
    }
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[ch]);
  }

  function ensureElements() {
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
        <h3 class="wf-revealco-title">Company Information</h3>
        <div class="wf-revealco-body" id="wfRevealCoBody"></div>
      </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Close interactions
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeModal();
    });
    modal.querySelector('.wf-revealco-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('show')) closeModal();
    });
  }

  function openModal(triggerBtn) {
    ensureElements();

    const body = modal.querySelector('#wfRevealCoBody');
    if (!body) return;

    // Build a fresh challenge view
    const a = Math.floor(Math.random() * 8) + 1;
    const b = Math.floor(Math.random() * 8) + 1;

    body.innerHTML = `
      <div class="wf-revealco-challenge">
        <p class="wf-revealco-hint">Quick check to prevent spam. Please solve:</p>
        <label class="wf-revealco-q">
          <span class="wf-revealco-qtext">${a} + ${b} =</span>
          <input type="text" inputmode="numeric" class="wf-revealco-input" aria-label="Answer" />
        </label>
        <div class="wf-revealco-actions">
          <button type="button" class="btn btn-primary" data-action="reveal">Reveal</button>
          <button type="button" class="btn btn-secondary" data-action="cancel">Cancel</button>
        </div>
        <p class="wf-revealco-status" aria-live="polite"></p>
      </div>
    `;

    const expected = String(a + b);
    const input = body.querySelector('.wf-revealco-input');
    const status = body.querySelector('.wf-revealco-status');

    function showDetails() {
      const encEmail = triggerBtn.getAttribute('data-enc-email') || '';
      const encPhone = triggerBtn.getAttribute('data-enc-phone') || '';
      const encAddress = triggerBtn.getAttribute('data-enc-address') || '';
      const encName = triggerBtn.getAttribute('data-enc-name') || '';
      const encOwner = triggerBtn.getAttribute('data-enc-owner') || '';
      const encSite = triggerBtn.getAttribute('data-enc-site') || '';
      const encHours = triggerBtn.getAttribute('data-enc-hours') || '';

      const email = (b64decode(encEmail) || '').trim();
      const phoneRaw = (b64decode(encPhone) || '').trim();
      const phoneTel = phoneRaw.replace(/[^\d+]/g, '');
      const address = (b64decode(encAddress) || '').trim();
      const company = (b64decode(encName) || '').trim();
      const owner = (b64decode(encOwner) || '').trim();
      const siteUrl = (b64decode(encSite) || '').trim();
      const hoursRaw = (b64decode(encHours) || '').trim();

      const rows = [];
      if (company) {
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Company</span><span>${escapeHtml(company)}</span></div>`);
      }
      if (owner) {
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Owner</span><span>${escapeHtml(owner)}</span></div>`);
      }
      if (siteUrl) {
        const safeUrl = siteUrl.startsWith('http') ? siteUrl : ('https://' + siteUrl);
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Website</span><a class="wf-revealco-link" target="_blank" rel="noopener" href="${safeUrl}">${siteUrl}</a></div>`);
      }
      if (email) {
        const emailEsc = escapeHtml(email);
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Email</span><a class="wf-revealco-link" href="mailto:${emailEsc}">${emailEsc}</a></div>`);
      }
      if (phoneRaw) {
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Phone</span><a class="wf-revealco-link" href="tel:${phoneTel}">${escapeHtml(phoneRaw)}</a></div>`);
      }
      if (address) {
        const maps = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(address);
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Address</span><a class="wf-revealco-link" target="_blank" rel="noopener" href="${maps}">${escapeHtml(address)}</a></div>`);
      }
      if (hoursRaw) {
        const hoursHtml = escapeHtml(hoursRaw).replace(/\n/g, '<br>');
        rows.push(`<div class="wf-revealco-row"><span class="wf-revealco-label">Hours</span><span>${hoursHtml}</span></div>`);
      }

      body.innerHTML = `
        <div class="wf-revealco-details">
          ${rows.join('') || '<p>No company details available.</p>'}
        </div>
      `;
    }

    body.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      const action = btn.getAttribute('data-action');
      if (action === 'cancel') {
        closeModal();
        return;
      }
      if (action === 'reveal') {
        const val = (input && input.value || '').trim();
        if (val === expected) {
          showDetails();
        } else {
          if (status) status.textContent = 'Incorrect, please try again.';
          if (input) {
            input.value = '';
            input.focus();
          }
        }
      }
    });

    overlay.classList.add('show');
    // Apply global scroll lock via centralized helper
    WFModals?.lockScroll?.();
    if (input) input.focus();
  }

  function closeModal() {
    if (!overlay) return;
    overlay.classList.remove('show');
    // Remove scroll lock only if no other modals are open
    WFModals?.unlockScrollIfNoneOpen?.();
  }

  function onClickTrigger(e) {
    const btn = e.target.closest('#wf-reveal-company-btn');
    if (!btn) return;
    e.preventDefault();
    openModal(btn);
  }

  // Attach on contact page only
  function setup() {
    const body = document.body;
    if (!body || body.dataset.page !== 'contact') return;
    document.addEventListener('click', onClickTrigger);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup, { once: true });
  } else {
    setup();
  }
})();
