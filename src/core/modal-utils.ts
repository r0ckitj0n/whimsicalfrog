/**
 * Shared Modal Utilities
 * Provides consistent show/hide behavior and cooperates with the React modal system.
 */

export function ensureOnBody(el: HTMLElement | null): HTMLElement | null {
    try {
        if (!el) return el;
        if (el.parentNode !== document.body) {
            document.body.appendChild(el);
        }
    } catch { /* DOM manipulation failed */ }
    return el;
}

export function showModalById(id: string): boolean {
    try {
        const el = document.getElementById(id);
        if (!el) return false;
        ensureOnBody(el);
        el.classList.add('show');

        // Basic a11y state
        el.setAttribute('aria-hidden', 'false');

        // Cooperate with global scroll lock if available
        if (window.WFModals?.lockScroll) {
            window.WFModals.lockScroll();
        } else {
            document.body.classList.add('modal-open');
            document.documentElement.classList.add('modal-open');
        }

        return true;
    } catch { // Modal show failed - likely SSR
        return false;
    }
}

export function hideModalById(id: string): boolean {
    try {
        const el = document.getElementById(id);
        if (!el) return false;
        el.classList.remove('show');
        el.setAttribute('aria-hidden', 'true');

        // Cooperate with global scroll unlock
        if (window.WFModals?.unlockScrollIfNoneOpen) {
            window.WFModals.unlockScrollIfNoneOpen();
        } else {
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
        }

        return true;
    } catch { // Modal hide failed - likely SSR
        return false;
    }
}

export function forceVisibleStyles(el: HTMLElement): void {
    try {
        el.classList.add('wf-modal-force-visible');
    } catch { /* Style class manipulation failed */ }
}

// Attach to window for legacy support
if (typeof window !== 'undefined') {
    window.WFModalUtils = {
        ensureOnBody,
        showModalById,
        hideModalById,
        forceVisibleStyles
    };
}
