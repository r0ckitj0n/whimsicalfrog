/**
 * WhimsicalFrog Modal Management System
 * Centralized functions to eliminate duplication and improve maintainability
 * Generated: 2025-07-01 23:17:27
 */

console.log('Loading modal-manager.js...');

(function initWFModals() {
    const SELECTOR_ANY_OPEN = [
        // Room modal overlays
        '.room-modal-overlay.show',
        // Reveal company modal
        '.wf-revealco-overlay.show',
        // Global confirmation modal
        '#global-confirmation-modal.show',
        // Image viewer state
        '.image-viewer-modal-open',
        // Confirmation overlay variant
        '.confirmation-modal-overlay.show',
        // Search and login
        '#searchModal.show',
        '.wf-login-overlay.show',
        // Quantity modal and detailed item modal (use hidden toggling)
        '#quantityModal:not(.hidden)',
        '#detailedItemModal:not(.hidden)',
        // Generic modal overlay patterns (legacy/admin)
        '.modal-overlay.show',
        '.modal-overlay:not(.hidden)',
        '.admin-modal-overlay.show',
        '.admin-modal-overlay:not(.hidden)'
    ].join(', ');

    function isAnyModalOpen() {
        return !!document.querySelector(SELECTOR_ANY_OPEN);
    }

    function lockScroll() {
        document.body.classList.add('modal-open');
        document.documentElement.classList.add('modal-open');
    }

    function unlockScrollIfNoneOpen() {
        if (!isAnyModalOpen()) {
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
        }
    }

    // Expose globally for use across modules without import churn
    window.WFModals = window.WFModals || {};
    window.WFModals.SELECTOR_ANY_OPEN = SELECTOR_ANY_OPEN;
    window.WFModals.isAnyModalOpen = isAnyModalOpen;
    window.WFModals.lockScroll = lockScroll;
    window.WFModals.unlockScrollIfNoneOpen = unlockScrollIfNoneOpen;

    console.log('[WFModals] initialized');
})();

function closeModal() {
    document.getElementById('deleteConfirmModal').classList.remove('show');
}

console.log('modal-manager.js loaded successfully');
