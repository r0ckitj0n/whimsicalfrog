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
// Lazy modal factory: Room Map Editor
const __wfEnsureRoomMapEditorModal = () => {
  let el = document.getElementById('roomMapEditorModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'roomMapEditorModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'roomMapEditorTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="roomMapEditorTitle" class="admin-card-title">🗺️ Room Map Editor</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="roomMapEditorFrame" title="Room Map Editor" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/room_map_editor.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};
// Lazy modal factory: Area-Item Mapper
const __wfEnsureAreaItemMapperModal = () => {
  let el = document.getElementById('areaItemMapperModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'areaItemMapperModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'areaItemMapperTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="areaItemMapperTitle" class="admin-card-title">🧭 Area-Item Mapper</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="areaItemMapperFrame" title="Area-Item Mapper" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/area_item_mapper.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Email Settings (iframe to standalone settings page)
const __wfEnsureEmailSettingsModal = () => {
  let el = document.getElementById('emailSettingsModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'emailSettingsModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'emailSettingsTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="emailSettingsTitle" class="admin-card-title">✉️ Email Settings</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="emailSettingsFrame" title="Email Settings" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/email_settings.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Receipt Messages (iframe to receipt page)
const __wfEnsureReceiptMessagesModal = () => {
  let el = document.getElementById('receiptMessagesModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'receiptMessagesModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'receiptMessagesTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
      <div class="modal-header">
        <h2 id="receiptMessagesTitle" class="admin-card-title">🧾 Receipt Messages</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="receiptMessagesFrame" title="Receipt Messages" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/receipt.php?modal=1" referrerpolicy="no-referrer"></iframe>
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

      // Open Email Settings modal (iframe)
      if (closest('[data-action="open-email-settings"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureEmailSettingsModal();
        __wfShowModal('emailSettingsModal');
        const iframe = document.getElementById('emailSettingsFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Receipt Messages modal (iframe)
      if (closest('[data-action="open-receipt-messages"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureReceiptMessagesModal();
        __wfShowModal('receiptMessagesModal');
        const iframe = document.getElementById('receiptMessagesFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Template Manager modal
      if (closest('[data-action="open-template-manager"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = __wfEnsureTemplateManagerModal();
        // Ensure overlay is at top-level and above header
        try {
          if (el && el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
          }
          // Add helper classes; CSS should define these behaviors
          el.classList.add('over-header');
          el.classList.add('pointer-events-auto');
          // z-index and pointer-events are controlled via CSS classes rather than inline styles
        } catch (_) {}
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

      // Open Shipping Settings modal (static form)
      if (closest('[data-action="open-shipping-settings"], #shippingSettingsBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('shippingSettingsModal');
        return;
      }

      // Open Address Diagnostics modal (iframe)
      if (closest('[data-action="open-address-diagnostics"], #addressDiagBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('addressDiagnosticsModal');
        const iframe = document.getElementById('addressDiagnosticsFrame');
        if (iframe) {
          const needsSrc = (!iframe.src || iframe.src === 'about:blank');
          const ds = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : '/sections/tools/address_diagnostics.php?modal=1';
          if (needsSrc) { iframe.src = ds; }
        }
        return;
      }

      // Open Health & Diagnostics modal
      if (closest('[data-action="open-health-diagnostics"], #healthDiagnosticsBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('healthModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('healthModal');
        }
        return;
      }

      // Open Secrets Manager modal
      if (closest('[data-action="open-secrets-modal"], #secretsManagerBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('secretsModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('secretsModal');
        }
        return;
      }

      // Open Categories Management modal (iframe)
      if (closest('[data-action="open-categories"], #categoriesBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('categoriesModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('categoriesModal');
          const f = el.querySelector('iframe');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/sections/admin_categories.php?modal=1';
            f.setAttribute('src', ds);
          }
        }
        return;
      }

      // Open Attributes Management modal (iframe)
      if (closest('[data-action="open-attributes"], #attributesBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('attributesModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('attributesModal');
          const f = document.getElementById('attributesFrame');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/components/embeds/attributes_manager.php?modal=1';
            f.setAttribute('src', ds);
          }
        }
        return;
      }

      // Customer Messages modal (native)
      if (closest('[data-action="open-customer-messages"], #customerMessagesBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('customerMessagesModal');
        return;
      }

      // Shopping Cart Settings modal
      if (closest('[data-action="open-shopping-cart"], #shoppingCartBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('shoppingCartModal');
        return;
      }

      // Size/Color Redesign Tool modal (iframe)
      if (closest('[data-action="open-size-color-redesign"], #sizeColorRedesignBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('sizeColorRedesignModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('sizeColorRedesignModal');
          const f = document.getElementById('sizeColorRedesignFrame');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/sections/tools/size_color_redesign.php?modal=1';
            f.setAttribute('src', ds);
          }
        }
        return;
      }

      // Deploy Manager modal (iframe)
      if (closest('[data-action="open-deploy-manager"], #deployManagerBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('deployManagerModal');
        return;
      }

      // DB Schema Audit modal (iframe)
      if (closest('[data-action="open-db-schema-audit"], #dbSchemaAuditBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('dbSchemaAuditModal');
        const f = document.getElementById('dbSchemaAuditFrame');
        if (f && !f.getAttribute('src')) {
          const ds = f.getAttribute('data-src') || '/sections/tools/db_schema_audit.php?modal=1';
          f.setAttribute('src', ds);
        }
        return;
      }

      // Repository Cleanup modal (iframe)
      if (closest('[data-action="open-repo-cleanup"], #repoCleanupBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('repoCleanupModal');
        return;
      }

      // Dashboard Configuration modal (native)
      if (closest('[data-action="open-dashboard-config"], #dashboardConfigBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('dashboardConfigModal');
        return;
      }

      // AI Tools modal (iframe)
      if (closest('[data-action="open-ai-tools"], #aiToolsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('aiToolsModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('aiToolsModal');
          const f = document.getElementById('aiToolsFrame');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/sections/admin_router.php?section=marketing&modal=1';
            f.setAttribute('src', ds);
          }
        }
        return;
      }

      // AI Settings modal (iframe)
      if (closest('[data-action="open-ai-settings"], #aiSettingsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('aiSettingsModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfShowModal('aiSettingsModal');
          // Note: aiSettingsModal currently embeds Categories frame per template; leave src priming to data-src
        }
        return;
      }

      // Square Settings modal (native form)
      if (closest('[data-action="open-square-settings"], #squareSettingsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('squareSettingsModal');
        return;
      }

      // Let global Account Settings modal handler manage [data-action="open-account-settings"] clicks

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

      // Open Room Map Editor modal (ensure + prime)
      if (closest('[data-action="open-room-map-editor"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureRoomMapEditorModal();
        __wfShowModal('roomMapEditorModal');
        const iframe = document.getElementById('roomMapEditorFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Area-Item Mapper modal (ensure + prime)
      if (closest('[data-action="open-area-item-mapper"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureAreaItemMapperModal();
        __wfShowModal('areaItemMapperModal');
        const iframe = document.getElementById('areaItemMapperFrame');
        if (iframe && iframe.dataset) {
          if (iframe.dataset.loaded === '1' || iframe.dataset.loading === '1') {
            return;
          }
          const needsSrc = (!iframe.src || iframe.src === 'about:blank');
          const ds = iframe.dataset.src;
          if (needsSrc && ds) {
            iframe.dataset.loading = '1';
            iframe.addEventListener('load', () => { try { iframe.dataset.loaded = '1'; iframe.dataset.loading = '0'; } catch(_) {} }, { once: true });
            iframe.src = ds;
          }
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
