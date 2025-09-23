// Admin Settings Entry Point - Lightweight
// This replaces the large admin-settings.js with a lightweight coordinator
// that loads modules on-demand for better performance

// Import main styles
import '../styles/main.css';
import '../styles/components/components-base.css';
import '../styles/components/admin-nav.css';
import '../styles/admin-modals.css';
import '../styles/components/modal.css';
import '../styles/admin-settings.css';
import '../styles/admin-settings-extras.css';

// Import utilities
import '../modules/utilities.js';

// Import the coordinator module
import '../modules/admin-settings-coordinator.js';

// Immediately install lightweight modal helpers
const __wfShowModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  try { el.removeAttribute('hidden'); } catch(_) {}
  el.classList.remove('hidden');
  el.classList.add('show');
  el.setAttribute('aria-hidden', 'false');
  return true;
};

const __wfHideModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  try { el.setAttribute('hidden', ''); } catch(_) {}
  el.classList.add('hidden');
  el.classList.remove('show');
  el.setAttribute('aria-hidden', 'true');
  return true;
};

// Install immediate click handlers for critical functionality
(function installImmediateSettingsClicks(){
  const handler = (e) => {
    try {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);

      // Only act on Settings routes
      const body = document.body;
      const pathLower = ((body && body.dataset && body.dataset.path) || window.location.pathname + window.location.search || '').toLowerCase();
      const isSettings = (
        document.getElementById('adminSettingsRoot') !== null
        || (body && body.dataset && body.dataset.page === 'admin/settings')
        || (body && body.dataset && body.dataset.isAdmin === 'true' && (
            pathLower.includes('/admin/settings')
            || pathLower.includes('section=settings')
            || pathLower.includes('admin_settings')
        ))
      );
      if (!isSettings) return;

      // Handle overlay close
      if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
        const id = t.id; if (!id) return;
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfHideModal(id);
        return;
      }

      // Handle close buttons
      if (closest('[data-action="close-admin-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const overlay = closest('.admin-modal-overlay');
        if (overlay && overlay.id) { __wfHideModal(overlay.id); }
        return;
      }

      // Handle core modal openers
      if (closest('[data-action="open-admin-help-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('adminHelpDocsModal');
        return;
      }

      // Load specific functionality on-demand
      if (closest('[data-action="open-business-info"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        import('./delegated-handlers.js').catch(err => {
          console.error('Failed to load business info handlers:', err);
        });
        return;
      }

    } catch (_) {}
  };

  // Register both capture and bubble to be resilient
  document.addEventListener('click', handler, true);
  document.addEventListener('click', handler);
})();

// Export for potential use by other modules
export { __wfShowModal, __wfHideModal };
