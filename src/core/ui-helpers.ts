/**
 * WhimsicalFrog Core – UI Helpers (TypeScript)
 * Provides consistent loading/disabled behavior for async actions.
 */

interface LoadingOptions {
    resultEl?: HTMLElement | null;
    startText?: string | null;
    successText?: string | null;
    errorText?: string | null;
    infoClass?: string;
    okClass?: string;
    errorClass?: string;
    onStart?: (() => void) | null;
    onSuccess?: ((value: unknown) => void) | null;
    onError?: ((error: Error) => void) | null;
}

export async function withLoading<T>(
    btn: HTMLButtonElement | HTMLAnchorElement | HTMLElement | null,
    action: (() => Promise<T> | T) | Promise<T>,
    options: LoadingOptions = {}
): Promise<T | undefined> {
    const {
        resultEl = null,
        startText = null,
        successText = null,
        errorText = null,
        infoClass = 'status status--info',
        okClass = 'status status--ok',
        errorClass = 'status status--error',
        onStart = null,
        onSuccess = null,
        onError = null,
    } = options;

    try {
        if (btn) {
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            if (btn instanceof HTMLButtonElement) btn.disabled = true;
        }
        if (resultEl) {
            if (startText) resultEl.textContent = startText;
            if (infoClass) resultEl.className = infoClass;
        }
        if (typeof onStart === 'function') {
            try { onStart(); } catch { /* User callback failed - continue */ }
        }

        const ret = typeof action === 'function' ? (action as () => Promise<T> | T)() : action;
        const value = (ret && typeof (ret as { then?: unknown }).then === 'function')
            ? await (ret as Promise<T>)
            : await new Promise<T>((r) => setTimeout(() => r(ret as T), 600));

        if (btn) {
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
            if (btn instanceof HTMLButtonElement) btn.disabled = false;
        }
        if (resultEl) {
            if (successText) resultEl.textContent = successText;
            if (okClass) resultEl.className = okClass;
        }
        if (typeof onSuccess === 'function') {
            try { onSuccess(value); } catch { /* User callback failed - continue */ }
        }
        return value;
    } catch (err: unknown) {
        const error = err instanceof Error ? err : new Error(String(err));
        if (btn) {
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
            if (btn instanceof HTMLButtonElement) btn.disabled = false;
        }
        if (resultEl) {
            if (errorText) resultEl.textContent = errorText;
            if (errorClass) resultEl.className = errorClass;
        }
        if (typeof onError === 'function') {
            try { onError(error); } catch { /* User callback failed - continue */ }
        }
        throw error;
    }
}
