/* Receipt Modal: loads receipt.php content into a modal and provides print-friendly styling */
(function() {
  const state = {
    overlay: null,
    container: null,
    content: null,
    header: null,
    orderId: null,
  };

  function ensureOverlay() {
    if (state.overlay && state.container) return;

    const existing = document.getElementById('receiptModalOverlay');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'receiptModalOverlay';
    overlay.className = 'confirmation-modal-overlay under-header checkout-overlay';

    const modal = document.createElement('div');
    modal.className = 'confirmation-modal receipt-modal animate-slide-in-up';

    const header = document.createElement('div');
    header.className = 'confirmation-modal-header receipt-modal-header';
    header.innerHTML = `
      <div class="left">
        <h3 class="title">Order Receipt</h3>
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
  }

  async function loadReceipt(orderId) {
    const url = `/receipt.php?orderId=${encodeURIComponent(orderId)}`;
    const res = await fetch(url, { credentials: 'include' });
    if (!res.ok) throw new Error(`Failed to load receipt (${res.status})`);
    return await res.text();
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
    state.overlay.classList.add('show');
    try { state.overlay.setAttribute('aria-hidden', 'false'); } catch(_) {}
    try { window.WFModals && window.WFModals.lockScroll && window.WFModals.lockScroll(); } catch(_) {}

    try {
      const html = await loadReceipt(orderId);
      // Wrap inside a print root to control print visibility
      state.content.innerHTML = `<div class="receipt-print-root">${html}</div>`;
      wireInnerPrintButton(state.content);
    } catch (err) {
      console.error('[ReceiptModal] Error loading receipt:', err);
      state.content.innerHTML = '<div class="error">Sorry, we could not load your receipt. Please try again.</div>';
    }
  }

  function close() {
    if (!state.overlay) return;
    state.overlay.classList.remove('show');
    try { state.overlay.setAttribute('aria-hidden', 'true'); } catch(_) {}
    try { window.WFModals && window.WFModals.unlockScrollIfNoneOpen && window.WFModals.unlockScrollIfNoneOpen(); } catch(_) {}
    // Unset receipt open flag and notify listeners that the modal closed
    try { window.__wfReceiptOpen = false; } catch(_) {}
    try { window.dispatchEvent(new CustomEvent('receiptModalClosed')); } catch(_) {}
  }

  // expose
  window.WF_ReceiptModal = { open, close };
})();
