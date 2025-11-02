/* Receipt Modal: loads receipt.php content into a modal and provides print-friendly styling */
(function() {
  const state = {
    overlay: null,
    container: null,
    content: null,
    header: null,
    orderId: null,
    previouslyFocusedElement: null,
  };

  function ensureOverlay() {
    if (state.overlay && state.container) return;

    const existing = document.getElementById('receiptModalOverlay');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'receiptModalOverlay';
    // High z-index via checkout-overlay; vertical offset handled by CSS on inner modal
    overlay.className = 'confirmation-modal-overlay checkout-overlay receipt-overlay';
    try { overlay.setAttribute('role', 'dialog'); overlay.setAttribute('aria-modal', 'true'); } catch(_) {}

    const modal = document.createElement('div');
    modal.className = 'confirmation-modal receipt-modal animate-slide-in-up';

    const header = document.createElement('div');
    header.className = 'confirmation-modal-header receipt-modal-header';
    header.innerHTML = `
      <div class="left">
        <h3 class="title" id="receiptModalTitle">Order Receipt</h3>
      </div>
      <div class="right actions">
        <button type="button" class="btn-secondary btn-print">Print</button>
        <button type="button" class="btn-secondary btn-close" aria-label="Close receipt">Close</button>
      </div>
    `;

    const content = document.createElement('div');
    content.className = 'confirmation-modal-content receipt-modal-content';

    modal.appendChild(header);
    modal.appendChild(content);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    header.querySelector('.btn-close')?.addEventListener('click', close);
    header.querySelector('.btn-print')?.addEventListener('click', () => {
      try { window.print(); } catch(_) {}
    });

    state.overlay = overlay;
    state.container = modal;
    state.content = content;
    state.header = header;
    try { overlay.setAttribute('aria-labelledby', 'receiptModalTitle'); } catch(_) {}

    try {
      if (!overlay._wfFocusTrap) {
        overlay._wfFocusTrap = (e) => {
          if (e.key !== 'Tab') return;
          if (!state.overlay || !state.overlay.classList.contains('show')) return;
          const scope = state.container || state.overlay;
          const nodes = scope.querySelectorAll('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])');
          const focusables = Array.from(nodes).filter(el => !el.hasAttribute('disabled') && el.tabIndex !== -1 && el.offsetParent !== null);
          if (!focusables.length) return;
          const first = focusables[0];
          const last = focusables[focusables.length - 1];
          const active = document.activeElement;
          if (e.shiftKey) {
            if (active === first || !scope.contains(active)) { last.focus(); e.preventDefault(); }
          } else {
            if (active === last || !scope.contains(active)) { first.focus(); e.preventDefault(); }
          }
        };
        overlay.addEventListener('keydown', overlay._wfFocusTrap, true);
      }
    } catch(_) {}
  }

  async function loadReceipt(orderId) {
    // Use canonical router path with bare=1 so the server suppresses header/footer
    const url = `/receipt?orderId=${encodeURIComponent(orderId)}&bare=1`;
    if (!window.ApiClient || typeof window.ApiClient.request !== 'function') {
      throw new Error('ApiClient is not available');
    }
    const html = await window.ApiClient.request(url, { method: 'GET' });
    // Parse and extract only the receipt container to avoid any stray layout wrappers
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const container = doc.querySelector('.receipt-container');
      if (container) return container.outerHTML;
    } catch(_) {}
    return html;
  }

  function wireInnerPrintButton(rootEl) {
    try {
      const printBtn = rootEl.querySelector('.js-print-button');
      if (printBtn) {
        printBtn.addEventListener('click', (e) => {
          e.preventDefault();
          try { window.print(); } catch(_) {}
        }, { once: true });
      }
    } catch(_) {}
  }

  async function open(orderId) {
    ensureOverlay();
    state.orderId = orderId;
    // Mark receipt as open to allow other modules to suppress reactive updates
    try { window.__wfReceiptOpen = true; } catch(_) {}

    state.content.innerHTML = '<div class="receipt-loading">Loading receipt…</div>';
    try { window.WFModalUtils && window.WFModalUtils.ensureOnBody && window.WFModalUtils.ensureOnBody(state.overlay); } catch(_) {}
    try { state.previouslyFocusedElement = document.activeElement; } catch(_) {}
    if (typeof window.showModal === 'function') {
      window.showModal('receiptModalOverlay');
    } else {
      state.overlay.classList.add('show');
      try { state.overlay.setAttribute('aria-hidden', 'false'); } catch(_) {}
      try { window.WFModals && window.WFModals.lockScroll && window.WFModals.lockScroll(); } catch(_) {}
    }
    try {
      const scope = state.container || state.overlay;
      const target = scope.querySelector('.btn-close, .btn-print, button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (target && typeof target.focus === 'function') target.focus();
    } catch(_) {}

    try {
      const html = await loadReceipt(orderId);
      // Ensure we only inject the receipt markup and keep styling scoped for print
      state.content.innerHTML = `<div class="receipt-print-root">${html}</div>`;
      wireInnerPrintButton(state.content);
    } catch (err) {
      console.error('[ReceiptModal] Error loading receipt:', err);
      state.content.innerHTML = '<div class="error">Sorry, we could not load your receipt. Please try again.</div>';
    }
  }

  function close() {
    if (!state.overlay) return;
    if (typeof window.hideModal === 'function') {
      window.hideModal('receiptModalOverlay');
    } else {
      state.overlay.classList.remove('show');
      try { state.overlay.setAttribute('aria-hidden', 'true'); } catch(_) {}
      try { window.WFModals && window.WFModals.unlockScrollIfNoneOpen && window.WFModals.unlockScrollIfNoneOpen(); } catch(_) {}
    }
    try { if (state.previouslyFocusedElement) state.previouslyFocusedElement.focus(); } catch(_) {}
    try { state.previouslyFocusedElement = null; } catch(_) {}
    // Unset receipt open flag and notify listeners that the modal closed
    try { window.__wfReceiptOpen = false; } catch(_) {}
    try { window.dispatchEvent(new CustomEvent('receiptModalClosed')); } catch(_) {}
  }

  // expose
  window.WF_ReceiptModal = { open, close };
})();
