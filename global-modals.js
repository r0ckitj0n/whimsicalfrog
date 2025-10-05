// Global Modal and Confirmation Dialog System

class ConfirmationModal {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.currentResolve = null;
        this.init();
    }

    init() {
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
                this.close(false);
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.classList.contains('show')) {
                this.close(false);
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
                subtitle = null
            } = options;

            // Build modal content
            this.modal.innerHTML = `
                <div class="confirmation-modal-header">
                    <div class="confirmation-modal-icon ${iconType}">
                        ${icon}
                    </div>
                    <h3 class="confirmation-modal-title">${title}</h3>
                    ${subtitle ? `<p class="confirmation-modal-subtitle">${subtitle}</p>` : ''}
                </div>
                <div class="confirmation-modal-body">
                    <div class="confirmation-modal-message">${message}</div>
                    ${details ? `<div class="confirmation-modal-details">${details}</div>` : ''}
                </div>
                <div class="confirmation-modal-footer">
                    <button class="confirmation-modal-button cancel" id="modal-cancel">
                        ${cancelText}
                    </button>
                    <button class="confirmation-modal-button ${confirmStyle}" id="modal-confirm">
                        ${confirmText}
                    </button>
                </div>
            `;

            // Add button event listeners
            document.getElementById('modal-cancel').addEventListener('click', () => {
                this.close(false);
            });

            document.getElementById('modal-confirm').addEventListener('click', () => {
                this.close(true);
            });

            // Show modal
            this.overlay.classList.add('show');
            
            // Focus the confirm button
            setTimeout(() => {
                document.getElementById('modal-confirm').focus();
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

// Enhanced confirmation with details
window.confirmWithDetails = async (title, message, details, options = {}) => {
    return await showConfirmationModal({
        title,
        message,
        details,
        ...options
    });
}; 