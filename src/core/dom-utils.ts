/**
 * WhimsicalFrog Core – DOM Utilities (TypeScript)
 */

export class DOMUtils {
    static setContent(element: HTMLElement | null, content: string, showLoading: boolean = false): void {
        if (!element) return;
        if (showLoading) {
            element.innerHTML = '<div class="text-center text-gray-500 py-4">Loading...</div>';
            setTimeout(() => {
                element.innerHTML = content;
            }, 100);
        } else {
            element.innerHTML = content;
        }
    }

    static createLoadingSpinner(message: string = 'Loading...'): string {
        return `
            <div class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-[var(--brand-primary)] mr-3"></div>
                <span class="text-gray-600">${message}</span>
            </div>
        `;
    }

    static createErrorMessage(message: string): string {
        return `
            <div class="bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] rounded-lg p-4 text-[var(--brand-error)]">
                <div class="flex items-center">
                    <span class="btn-icon--warning mr-2" style="font-size: 1.25rem;" aria-hidden="true"></span>
                    ${message}
                </div>
            </div>
        `;
    }

    static createSuccessMessage(message: string): string {
        return `
            <div class="bg-[var(--brand-accent-bg)] border border-[var(--brand-accent-border)] rounded-lg p-4 text-[var(--brand-accent)]">
                <div class="flex items-center">
                    <span class="btn-icon--check mr-2" style="font-size: 1.25rem;" aria-hidden="true"></span>
                    ${message}
                </div>
            </div>
        `;
    }

    static showToast(message: string, type: 'success' | 'error' | 'info' = 'info', duration: number = 3000): void {
        // Use the global React-based toast if available
        if (typeof window !== 'undefined' && window.showNotification) {
            window.showNotification(message, type as GlobalNotificationType);
            return;
        }

        const toastId = 'toast-' + Date.now();
        const colors = {
            success: 'bg-[var(--brand-accent)] text-white',
            error: 'bg-[var(--brand-error)] text-white',
            info: 'bg-[var(--brand-primary)] text-white'
        };

        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${colors[type]} px-6 py-3 rounded-lg shadow-lg transform transition-transform duration-300 translate-x-full`;
        toast.style.zIndex = 'var(--wf-z-toast)';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);

        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                document.getElementById(toastId)?.remove();
            }, 300);
        }, duration);
    }

    static debounce<T extends (...args: unknown[]) => unknown>(func: T, wait: number): (...args: Parameters<T>) => void {
        let timeout: ReturnType<typeof setTimeout> | null;
        return function executedFunction(...args: Parameters<T>) {
            if (timeout) clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    }

    static formatCurrency(value: number): string {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
    }

    static escapeHtml(text: string): string {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    static async confirm(message: string, title: string = 'Confirm'): Promise<boolean> {
        try {
            // Priority 1: New standardized global themed alias
            if (typeof window.WF_Confirm === 'function') {
                return await window.WF_Confirm({ title, message });
            }

            // Priority 2: Original themed modal bridge
            if (typeof window.showConfirmationModal === 'function') {
                const ok = await window.showConfirmationModal({
                    title,
                    message,
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    icon: '⚠️',
                    iconType: 'warning',
                    confirmStyle: 'confirm'
                });
                return !!ok;
            }
        } catch { /* Themed confirm modal failed - fall back to DOM modal */ }


        return new Promise(resolve => {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center';
            modal.style.zIndex = 'var(--wf-z-modal)';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4">${DOMUtils.escapeHtml(title)}</h3>
                    <p class="text-gray-600 mb-6">${DOMUtils.escapeHtml(message)}</p>
                    <div class="flex justify-end space-x-3">
                        <button class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded" data-action="cancel">Cancel</button>
                        <button class="px-4 py-2 bg-[var(--brand-error)] text-white hover:bg-[var(--brand-error)]/90 rounded" data-action="confirm">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal.addEventListener('click', (e: MouseEvent) => {
                const target = e.target as HTMLElement;
                if (target.dataset.action === 'confirm') {
                    modal.remove();
                    resolve(true);
                } else if (target.dataset.action === 'cancel' || target === modal) {
                    modal.remove();
                    resolve(false);
                }
            });
        });
    }
}

// Global exposure for legacy code
if (typeof window !== 'undefined') {
    window.DOMUtils = DOMUtils;
    window.debounce = DOMUtils.debounce;
    window.formatCurrency = DOMUtils.formatCurrency;
    window.escapeHtml = DOMUtils.escapeHtml;
    // Don't overwrite the React-based showToast if it exists
    if (!window.showToast) {
        window.showToast = DOMUtils.showToast as unknown as (typeOrMessage: string, messageOrType?: string | null, options?: GlobalNotificationOptions) => number;
    }
    window.confirmDialog = DOMUtils.confirm;
}

export default DOMUtils;
