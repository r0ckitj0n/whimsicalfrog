// Lazy modal factory: Background Manager (embeds dashboard #background)
const __wfEnsureBackgroundManagerModal = () => {
  let el = document.getElementById('backgroundManagerModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'backgroundManagerModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'backgroundManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="backgroundManagerTitle" class="admin-card-title">🖼️ Background Manager</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="backgroundManagerFrame" title="Background Manager" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/admin_dashboard.php?modal=1#background" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Template Manager (reports/doc browser)
const __wfEnsureTemplateManagerModal = () => {
  let el = document.getElementById('templateManagerModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'templateManagerModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'templateManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="templateManagerTitle" class="admin-card-title">📁 Template Manager</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="templateManagerFrame" title="Template Manager" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/template_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Email History (uses Reports/Docs browser as a proxy)
const __wfEnsureEmailHistoryModal = () => {
  let el = document.getElementById('emailHistoryModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'emailHistoryModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'emailHistoryTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="emailHistoryTitle" class="admin-card-title">📬 Email History</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="emailHistoryFrame" title="Email History" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/email_history.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};
// Ensure a given overlay element exists; used to lazily create modals on demand
const __wfEnsureCssCatalogModal = () => {
  let el = document.getElementById('cssCatalogModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'cssCatalogModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'cssCatalogTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="cssCatalogTitle" class="admin-card-title">🎨 CSS Catalog</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="cssCatalogFrame" title="CSS Catalog" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/css_catalog.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};
// Admin Settings Entry Point - Lightweight
// This replaces the large admin-settings.js with a lightweight coordinator
// that loads modules on-demand for better performance

// Import main styles
import '../styles/main.css';
import '../styles/components/components-base.css';
import '../styles/components/admin-nav.css';
// Ensure help chip overrides load after nav styles in this entry
import '../styles/admin-help-bubble.css';
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

      // Open Template Manager modal
      if (closest('[data-action="open-template-manager"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureTemplateManagerModal();
        __wfShowModal('templateManagerModal');
        const iframe = document.getElementById('templateManagerFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Email History modal
      if (closest('[data-action="open-email-history"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureEmailHistoryModal();
        __wfShowModal('emailHistoryModal');
        const iframe = document.getElementById('emailHistoryFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Account Settings: navigate to page (keeps canonical single template)
      if (closest('[data-action="open-account-settings"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        try { window.location.assign('/account_settings.php'); } catch(_) { window.location.href = '/account_settings.php'; }
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

      // Open CSS Catalog modal
      if (closest('[data-action="open-css-catalog"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureCssCatalogModal();
        __wfShowModal('cssCatalogModal');
        const iframe = document.getElementById('cssCatalogFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Room Map Editor modal
      if (closest('[data-action="open-room-map-editor"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('roomMapEditorModal');
        const iframe = document.getElementById('roomMapEditorFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Area-Item Mapper modal
      if (closest('[data-action="open-area-item-mapper"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('areaItemMapperModal');
        const iframe = document.getElementById('areaItemMapperFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Background Manager modal (lazy create)
      if (closest('[data-action="open-background-manager"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureBackgroundManagerModal();
        __wfShowModal('backgroundManagerModal');
        const iframe = document.getElementById('backgroundManagerFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
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
