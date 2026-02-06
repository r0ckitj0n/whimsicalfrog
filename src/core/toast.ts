// src/core/Toast.ts
/**
 * WhimsicalFrog Core – Toast (TypeScript)
 */

interface Toaster {
    success: (msg: string) => void;
    error: (msg: string) => void;
}

function getHostToaster(): Toaster {
    return {
        success: (msg: string) => {
            if (typeof window.WhimsicalFrog?.notifySuccess === 'function') return window.WhimsicalFrog.notifySuccess(msg);
            if (typeof window.showSuccess === 'function') return window.showSuccess(msg);
            try {
                if (window.WhimsicalFrog?.Core?.debug) window.WhimsicalFrog.log({ msg: '[Toast:success]', text: msg });
            } catch { /* Debug logging failed - non-critical */ }
        },
        error: (msg: string) => {
            if (typeof window.WhimsicalFrog?.notifyError === 'function') return window.WhimsicalFrog.notifyError(msg);
            if (typeof window.showError === 'function') return window.showError(msg);
            console.error('[Toast:error]', msg);
        }
    };
}

export function toastSuccess(message: string | null | undefined): void {
    getHostToaster().success(String(message || ''));
}

export function toastError(message: string | null | undefined): void {
    getHostToaster().error(String(message || ''));
}

export function toastFromData(data: { success: boolean; message?: string; error?: string } | null, fallbackSuccess = 'Updated successfully'): void {
    const t = getHostToaster();
    if (!data) return t.success(fallbackSuccess);

    if (data.success === true) {
        return t.success(data.message || fallbackSuccess);
    }

    if (data.success === false) {
        return t.error(data.error || data.message || 'Request failed');
    }

    t.success(fallbackSuccess);
}

// Global exposure for legacy bridge
if (typeof window !== 'undefined') {
    window.WFToast = {
        success: (msg: string) => toastSuccess(msg),
        error: (msg: string) => toastError(msg),
        info: (msg: string) => toastSuccess(msg), // Fallback
        warning: (msg: string) => toastError(msg), // Fallback
        toastSuccess,
        toastError,
        toastFromData
    } as NonNullable<typeof window.WFToast>;
}
