// Small centralized toast utility for consistent UX across modules
// Usage:
//   import { toastSuccess, toastError, toastFromData } from '../core/toast.js';
//   toastFromData(apiResponse, 'Updated successfully');
//
// The helpers gracefully no-op if a host module provides its own showSuccess/showError.

function getHostToaster() {
  const host = {};
  try {
    // Prefer module-scoped showSuccess/showError if bound via "this"
    // Fallback to global notification systems if present
    host.success = (msg) => {
      if (typeof window?.WhimsicalFrog?.notifySuccess === 'function') return window.WhimsicalFrog.notifySuccess(msg);
      if (typeof window?.wf?.notifySuccess === 'function') return window.wf.notifySuccess(msg);
      if (typeof window?.showSuccess === 'function') return window.showSuccess(msg);
      // Last resort: console
      console.info('[Toast:success]', msg);
    };
    host.error = (msg) => {
      if (typeof window?.WhimsicalFrog?.notifyError === 'function') return window.WhimsicalFrog.notifyError(msg);
      if (typeof window?.wf?.notifyError === 'function') return window.wf.notifyError(msg);
      if (typeof window?.showError === 'function') return window.showError(msg);
      console.error('[Toast:error]', msg);
    };
  } catch (_) {}
  return host;
}

export function toastSuccess(message) {
  const t = getHostToaster();
  t.success(String(message || ''));
}

export function toastError(message) {
  const t = getHostToaster();
  t.error(String(message || ''));
}

// Prefer backend-provided { success, message } schema.
// Fallback message is used when success is true but no message given.
export function toastFromData(data, fallbackSuccess = 'Updated successfully', _fallbackNoChange = 'No changes detected') {
  try {
    const t = getHostToaster();
    if (!data) return t.success(fallbackSuccess);
    if (data.success === true) {
      const msg = data.message || fallbackSuccess;
      return t.success(msg);
    }
    if (data.success === false) {
      const msg = data.error || data.message || 'Request failed';
      return t.error(msg);
    }
    // Unknown shape; assume success
    return t.success(fallbackSuccess);
  } catch (e) {
    console.warn('[Toast] toastFromData warning', e);
  }
}

// Expose on a global for legacy modules that don’t import ESM
try {
  window.WFToast = { toastSuccess, toastError, toastFromData };
} catch (_) {}
