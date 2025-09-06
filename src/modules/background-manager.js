// Background Manager (stub)
// Provides a minimal init(el) so Vite can resolve and the Settings page can open the modal.
// Extend with real functionality later.

export function init(rootEl) {
  try {
    const el = rootEl || document.getElementById('backgroundManagerModal');
    if (!el) {
      console.warn('[BackgroundManager] modal element not found');
      return;
    }
    // Example: wire a refresh button if present
    const refreshBtn = el.querySelector('[data-action="bg-refresh"]');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', (e) => {
        e.preventDefault();
        console.log('[BackgroundManager] refresh requested');
      });
    }
    console.log('[BackgroundManager] initialized');
  } catch (e) {
    console.error('[BackgroundManager] init error', e);
  }
}

export default { init };
