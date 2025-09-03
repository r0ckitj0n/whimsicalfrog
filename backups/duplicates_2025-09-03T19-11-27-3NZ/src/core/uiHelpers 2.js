// Shared UI helpers for admin modules
// Provides consistent loading/disabled/aria-busy behavior on async buttons

export async function withLoading(btn, action, options = {}) {
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
      btn.disabled = true;
    }
    if (resultEl) {
      if (startText) resultEl.textContent = startText;
      if (infoClass) resultEl.className = infoClass;
    }
    if (typeof onStart === 'function') {
      try { onStart(); } catch (_) {}
    }

    const ret = typeof action === 'function' ? action() : action;
    const value = (ret && typeof ret.then === 'function') ? await ret : await new Promise((r) => setTimeout(() => r(undefined), 600));

    if (btn) {
      btn.classList.remove('is-loading');
      btn.removeAttribute('aria-busy');
      btn.disabled = false;
    }
    if (resultEl) {
      if (successText) resultEl.textContent = successText;
      if (okClass) resultEl.className = okClass;
    }
    if (typeof onSuccess === 'function') {
      try { onSuccess(value); } catch (_) {}
    }
    return value;
  } catch (err) {
    if (btn) {
      btn.classList.remove('is-loading');
      btn.removeAttribute('aria-busy');
      btn.disabled = false;
    }
    if (resultEl) {
      if (errorText) resultEl.textContent = errorText;
      if (errorClass) resultEl.className = errorClass;
    }
    if (typeof onError === 'function') {
      try { onError(err); } catch (_) {}
    }
    // Surface error to caller if they need it
    throw err;
  }
}
