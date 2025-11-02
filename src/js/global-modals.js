// Global Modal and Confirmation Dialog System

const __WF_CONFIRM_STYLE_ID = 'global-confirmation-modal-styles';
function __wfInjectConfirmationStyles(){
    try {
        if (document.getElementById(__WF_CONFIRM_STYLE_ID)) return;
        const css = `
        .confirmation-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity .2s ease, visibility .2s ease; z-index: var(--z-overlay-topmost, 1600); }
        .confirmation-modal-overlay.show { opacity: 1; visibility: visible; }
        .confirmation-modal { background: #fff; color: #111827; width: min(520px, 92vw); border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,.25); padding: 16px; font-family: inherit; }
        .confirmation-modal-header { display: flex; align-items: center; gap: 12px; }
        .confirmation-modal-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9999px; font-size: 18px; }
        .confirmation-modal-icon.warning { background: #FEF3C7; color: #92400E; }
        .confirmation-modal-icon.danger { background: #FEE2E2; color: #991B1B; }
        .confirmation-modal-icon.info { background: #DBEAFE; color: #1E40AF; }
        .confirmation-modal-icon.success { background: #DCFCE7; color: #166534; }
        .confirmation-modal-title { font-size: 1.125rem; font-weight: 600; margin: 0; }
        .confirmation-modal-subtitle { margin: 0; font-size: .875rem; color: #6B7280; }
        .confirmation-modal-body { margin-top: 8px; }
        .confirmation-modal-message { font-size: .95rem; color: #374151; }
        .confirmation-modal-details { color: #4B5563; font-size: .9rem; }
        .confirmation-modal-footer { margin-top: 16px; display: flex; gap: 8px; justify-content: flex-end; }
        .confirmation-modal-button { padding: 8px 12px; border-radius: 6px; border: 1px solid transparent; cursor: pointer; font-weight: 500; }
        .confirmation-modal-button.confirm { background: var(--brand-primary, #87ac3a); color: #fff; }
        .confirmation-modal-button.confirm:hover { filter: brightness(0.96); }
        .confirmation-modal-button.danger { background: #B91C1C; color: #fff; }
        .confirmation-modal-button.danger:hover { filter: brightness(0.96); }
        .confirmation-modal-button.cancel { background: #fff; color: #111827; border-color: #D1D5DB; }
        .animate-slide-in-up { animation: cm-slide-in-up .2s ease-out both; }
        @keyframes cm-slide-in-up { from { transform: translateY(12px); opacity: 0; } to { transform: none; opacity: 1; } }
        `;
        const style = document.createElement('style');
        style.id = __WF_CONFIRM_STYLE_ID;
        style.textContent = css;
        (document.head || document.documentElement || document.body).appendChild(style);
    } catch(_) {}
}

class ConfirmationModal {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.currentResolve = null;
        this.cancelResult = false;
        this.init();
    }

    init() {
        __wfInjectConfirmationStyles();
        // Create modal HTML structure
        this.createModalHTML();
        
        // Add event listeners
        this.addEventListeners();
    }

    createModalHTML() {
        // Remove existing modal if it exists
        const existingOverlay = document.getElementById('global-confirmation-modal');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        // Create modal overlay
        this.overlay = document.createElement('div');
        this.overlay.id = 'global-confirmation-modal';
        this.overlay.className = 'confirmation-modal-overlay';

        // Create modal container
        this.modal = document.createElement('div');
        this.modal.className = 'confirmation-modal animate-slide-in-up';

        this.overlay.appendChild(this.modal);
        document.body.appendChild(this.overlay);
    }

    addEventListeners() {
        // Close on overlay click
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close(this.cancelResult);
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.classList.contains('show')) {
                this.close(this.cancelResult);
            }
        });
    }

    show(options = {}) {
        return new Promise((resolve) => {
            this.currentResolve = resolve;

            const {
                title = 'Confirm Action',
                message = 'Are you sure you want to proceed?',
                details = null,
                icon = '⚠️',
                iconType = 'warning',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                confirmStyle = 'confirm',
                subtitle = null,
                // New options
                mode = 'confirm', // 'confirm' | 'alert' | 'prompt'
                input = null,     // { placeholder, defaultValue, type }
                iconKey = null,   // if provided, renders a btn-icon using centralized icon map
                showCancel = (typeof options.showCancel === 'boolean') ? options.showCancel : (mode !== 'alert')
            } = options;

            // Set how cancel resolves for this dialog
            this.cancelResult = (mode === 'prompt') ? null : false;

            const inputMarkup = (mode === 'prompt' || input) ? `
                <div class="confirmation-modal-details" style="margin-top:12px;">
                    <input id="modal-input" class="form-input w-full" type="${(input&&input.type)||'text'}" placeholder="${(input&&input.placeholder)||''}" value="${(input&&input.defaultValue)||''}">
                </div>
            ` : '';

            // Resolve icon content (prefer centralized icon map via iconKey, fallback to emoji)
            const iconMarkup = iconKey && typeof iconKey === 'string'
              ? `<span class="btn-icon btn-icon--${iconKey}" aria-hidden="true" style="width:18px;height:18px;min-width:18px;min-height:18px;padding:0"></span>`
              : icon;

            // Build modal content
            this.modal.innerHTML = `
                <div class="confirmation-modal-header">
                    <div class="confirmation-modal-icon ${iconType}">
                        ${iconMarkup}
                    </div>
                    <h3 class="confirmation-modal-title">${title}</h3>
                    ${subtitle ? `<p class="confirmation-modal-subtitle">${subtitle}</p>` : ''}
                </div>
                <div class="confirmation-modal-body">
                    <div class="confirmation-modal-message">${message}</div>
                    ${details ? `<div class="confirmation-modal-details">${details}</div>` : ''}
                    ${inputMarkup}
                </div>
                <div class="confirmation-modal-footer">
                    ${showCancel ? `<button class="confirmation-modal-button cancel" id="modal-cancel">${cancelText}</button>` : ''}
                    <button class="confirmation-modal-button ${confirmStyle}" id="modal-confirm">${confirmText}</button>
                </div>
            `;

            // Add button event listeners
            const cancelBtn = document.getElementById('modal-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this.close(this.cancelResult);
                });
            }

            const confirmBtn = document.getElementById('modal-confirm');
            confirmBtn.addEventListener('click', () => {
                if (mode === 'prompt' || input) {
                    const valEl = document.getElementById('modal-input');
                    const val = valEl ? valEl.value : '';
                    this.close(val);
                } else if (mode === 'alert') {
                    this.close(true);
                } else {
                    this.close(true);
                }
            });

            // Show modal
            this.overlay.classList.add('show');
            
            // Focus the confirm button
            setTimeout(() => {
                if (mode === 'prompt') {
                    const inp = document.getElementById('modal-input');
                    if (inp) { try { inp.focus(); inp.select && inp.select(); } catch(_) {} }
                    else { confirmBtn && confirmBtn.focus(); }
                } else {
                    confirmBtn && confirmBtn.focus();
                }
            }, 100);
        });
    }

    close(result) {
        this.overlay.classList.remove('show');
        
        setTimeout(() => {
            if (this.currentResolve) {
                this.currentResolve(result);
                this.currentResolve = null;
            }
        }, 300);
    }
}

// Global confirmation modal instance
let globalConfirmationModal = null;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    globalConfirmationModal = new ConfirmationModal();
});

// Global confirmation function
window.showConfirmationModal = async (options) => {
    if (!globalConfirmationModal) {
        globalConfirmationModal = new ConfirmationModal();
    }
    return await globalConfirmationModal.show(options);
};

// Convenience functions for different types of confirmations
window.confirmAction = async (title, message, confirmText = 'Confirm') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        icon: '⚠️',
        iconType: 'warning'
    });
};

window.confirmDanger = async (title, message, confirmText = 'Delete') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        confirmStyle: 'danger',
        icon: '⚠️',
        iconType: 'danger'
    });
};

window.confirmInfo = async (title, message, confirmText = 'Continue') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        icon: 'ℹ️',
        iconType: 'info'
    });
};

window.confirmSuccess = async (title, message, confirmText = 'Proceed') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        icon: '✅',
        iconType: 'success'
    });
};

// Alert modal (single OK button)
window.showAlertModal = async (options = {}) => {
    const {
        title = 'Notice',
        message = '',
        icon = 'ℹ️',
        iconType = 'info',
        confirmText = 'OK',
        subtitle = null,
        details = null,
        iconKey = null
    } = options;
    return await showConfirmationModal({
        title,
        message,
        details,
        icon,
        iconType,
        confirmText,
        subtitle,
        mode: 'alert',
        showCancel: false,
        confirmStyle: 'confirm',
        iconKey
    });
};

// Prompt modal (text input)
window.showPromptModal = async (options = {}) => {
    const {
        title = 'Input Required',
        message = '',
        placeholder = '',
        defaultValue = '',
        inputType = 'text',
        icon = '✏️',
        iconType = 'info',
        confirmText = 'OK',
        cancelText = 'Cancel',
        subtitle = null,
        details = null,
        iconKey = null
    } = options;
    return await showConfirmationModal({
        title,
        message,
        details,
        icon,
        iconType,
        confirmText,
        cancelText,
        subtitle,
        mode: 'prompt',
        input: { placeholder, defaultValue, type: inputType },
        confirmStyle: 'confirm',
        iconKey
    });
};

// Enhanced confirmation with details
window.confirmWithDetails = async (title, message, details, options = {}) => {
    return await showConfirmationModal({
        title,
        message,
        details,
        ...options
    });
}; 