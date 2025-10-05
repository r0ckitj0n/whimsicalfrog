<?php
// Admin Settings (JS-powered). Renders the wrapper the module expects and seeds minimal context.
// Guard auth if helper exists - TEMPORARILY DISABLED FOR DEVELOPMENT
// if (function_exists('isLoggedIn') && !isLoggedIn()) {
//     echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login to access settings</h1></div>';
//     return;
// }
// CSRF token for Secrets actions
try {
    require_once dirname(__DIR__) . '/includes/csrf.php';
    $__secrets_csrf = csrf_token('admin_secrets');
} catch (Throwable $____) {
    $__secrets_csrf = '';
}

// Current user for account prefill
$userData = function_exists('getCurrentUser') ? (getCurrentUser() ?? []) : [];
$uid = $userData['id'] ?? ($userData['userId'] ?? '');
$firstNamePrefill = $userData['firstName'] ?? ($userData['first_name'] ?? '');
$lastNamePrefill = $userData['lastName'] ?? ($userData['last_name'] ?? '');
$emailPrefill = $userData['email'] ?? '';

// Basic page title to match admin design

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_settings_footer_shutdown')) {
        function __wf_admin_settings_footer_shutdown()
        {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_settings_footer_shutdown');
}

// Always include admin navbar on settings page, even when accessed directly
$section = 'settings';
include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';

// Reusable settings card renderer
require_once dirname(__DIR__) . '/components/settings_card.php';

// Ensure admin-specific CSS variables are set
echo '<style id="admin-settings-navbar-fix">:root{--wf-header-height:64px;--admin-tabs-height:56px;}</style>';

// Reduce spacing between header and admin navbar
echo '<style id="admin-settings-navbar-spacing">
.admin-tab-navigation {
    top: var(--wf-header-height, 64px) !important;
    margin-top: 0 !important;
}
body[data-page="admin/settings"] .admin-tab-navigation {
    margin-top: 0 !important;
    padding-top: 0 !important;
}
/* Ensure navbar sits flush against header */
.admin-tab-navigation {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
</style>';

// Reduce spacing between header and admin navbar
echo '<style id="admin-settings-navbar-spacing">
.admin-tab-navigation {
    top: var(--wf-header-height, 64px) !important;
    margin-top: 0 !important;
}
body[data-page="admin/settings"] .admin-tab-navigation {
    margin-top: 0 !important;
    padding-top: 0 !important;
}
/* Ensure navbar sits flush against header */
.admin-tab-navigation {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
</style>';

// Calculate proper content spacing to avoid overlap with fixed navbar
echo '<style id="admin-settings-content-positioning">
body[data-page="admin/settings"] .settings-page {
    padding-top: calc(var(--wf-header-height, 64px) + var(--admin-tabs-height, 56px) + 8px) !important;
    margin-top: 0 !important;
    min-height: calc(100vh - var(--wf-header-height, 64px) - var(--admin-tabs-height, 56px)) !important;
    overflow-y: visible !important;
}
body[data-page="admin/settings"] #adminSettingsRoot {
    min-height: calc(100vh - var(--wf-header-height, 64px) - var(--admin-tabs-height, 56px) - 2rem) !important;
    overflow-y: auto !important;
}
</style>';

// Ensure admin content area has proper height
echo '<style id="admin-settings-content-height">
body[data-page="admin/settings"] #admin-section-content {
    overflow-y: auto !important;
    padding-top: 0 !important;
}
body[data-page="admin/settings"] .admin-dashboard {
    overflow-y: auto !important;
}
body[data-page="admin/settings"] {
    overflow-y: auto !important;
}
</style>';

// Reduce spacing between admin navbar and settings cards
echo '<style id="admin-settings-content-spacing">
/* Remove padding-top from admin content area on settings page */
body[data-page="admin/settings"] #admin-section-content {
    padding-top: 0 !important;
}
/* Remove padding-top from settings page container */
body[data-page="admin/settings"] > .settings-page {
    padding-top: 0 !important;
}
/* Reduce margin/padding on settings grid */
body[data-page="admin/settings"] .settings-grid {
    margin-top: 0 !important;
    padding-top: 0 !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
/* Ensure admin dashboard and section content have no spacing */
body[data-page="admin/settings"] .admin-dashboard,
body[data-page="admin/settings"] .page-content,
body[data-page="admin/settings"] #admin-section-content {
    margin-top: 0 !important;
    padding-top: 0 !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
/* Remove any inherited spacing from parent containers */
body[data-page="admin/settings"] .admin-dashboard.page-content {
    padding-top: 0 !important;
    margin-top: 0 !important;
}
/* Ensure no spacing on nested containers */
body[data-page="admin/settings"] .admin-dashboard,
body[data-page="admin/settings"] .page-content,
body[data-page="admin/settings"] #admin-section-content,
body[data-page="admin/settings"] .settings-page,
body[data-page="admin/settings"] #adminSettingsRoot {
    padding-top: 0 !important;
    margin-top: 0 !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}
/* Remove any footer spacing */
body[data-page="admin/settings"] footer,
body[data-page="admin/settings"] .site-footer,
body[data-page="admin/settings"] .footer,
body[data-page="admin/settings"] .footer-container {
    margin: 0 !important;
    padding: 0 !important;
    height: 0 !important;
    display: none !important;
}
/* Remove any residual bottom spacing from last child in content wrapper */
body[data-page="admin/settings"] #admin-section-content > *:last-child {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
/* Hide generic spacer utility elements if present */
body[data-page="admin/settings"] .spacer,
body[data-page="admin/settings"] .content-spacer,
body[data-page="admin/settings"] .push {
    display: none !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}
/* Remove any global body margins/padding that might create dead space */
body[data-page="admin/settings"] {
    margin: 0 !important;
    padding: 0 !important;
}
/* Ensure no spacing on container classes */
body[data-page="admin/settings"] .container {
    margin: 0 !important;
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}
/* Remove any admin-specific spacing rules that might create dead space */
body[data-page="admin/settings"] .settings-grid {
    margin-top: 0 !important;
}
</style>';
?>
<div class="admin-dashboard page-content">
    <div id="admin-section-content">
<!-- WF: SETTINGS WRAPPER START -->
<div class="settings-page container mx-auto px-4" data-page="admin-settings" data-user-id="<?= htmlspecialchars((string)$uid) ?>" style="padding-top: 128px !important; margin-top: 0 !important;">
  <noscript>
    <div class="admin-alert alert-warning">
      JavaScript is required to use the Settings page.
    </div>

    <script>
    (function(){
      try {
        const openBtn = document.getElementById('shippingSettingsBtn');
        const modal = document.getElementById('shippingSettingsModal');
        const statusEl = document.getElementById('shippingSettingsStatus');
        const saveBtn = document.getElementById('shippingSettingsSaveBtn');
        const closeEls = Array.from(document.querySelectorAll('[data-action="close-shipping-settings"]'));
        const getVal = (id) => (document.getElementById(id)?.value || '').trim();
        const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };

        function showModal(){ if (modal){ modal.classList.remove('hidden'); modal.setAttribute('aria-hidden','false'); } }
        function hideModal(){ if (modal){ modal.classList.add('hidden'); modal.setAttribute('aria-hidden','true'); } }
        function setStatus(msg, ok){ if (!statusEl) return; statusEl.textContent = msg || ''; statusEl.className = 'modal-status-chip ' + (ok ? 'text-green-700' : 'text-gray-600'); }

        async function loadSettings(){
          try {
            setStatus('Loading…', false);
            const url = '/api/business_settings.php?action=get_by_category&category=shipping';
            const r = await fetch(url, { credentials: 'include' });
            const j = r.ok ? await r.json() : null;
            const list = (j && j.success && Array.isArray(j.data?.settings)) ? j.data.settings : (j?.data?.settings || []);
            const map = {}; (list||[]).forEach(row => { if (row && row.setting_key) map[row.setting_key] = row.setting_value; });
            setVal('uspsUserId', map['usps_webtools_userid'] || '');
            setVal('upsAccessKey', map['ups_access_key'] || '');
            setVal('upsSecret', map['ups_secret'] || '');
            setVal('fedexKey', map['fedex_key'] || '');
            setVal('fedexSecret', map['fedex_secret'] || '');
            setVal('orsKey', map['ors_api_key'] || '');
            setStatus('Loaded', true);
          } catch (e) { setStatus('Load failed', false); }
        }

        async function saveSettings(){
          try {
            setStatus('Saving…', false);
            const payload = {
              action: 'upsert_settings',
              category: 'shipping',
              settings: {
                usps_webtools_userid: getVal('uspsUserId'),
                ups_access_key: getVal('upsAccessKey'),
                ups_secret: getVal('upsSecret'),
                fedex_key: getVal('fedexKey'),
                fedex_secret: getVal('fedexSecret'),
                ors_api_key: getVal('orsKey')
              }
            };
            const r = await fetch('/api/business_settings.php?action=upsert_settings', {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });
            const j = r.ok ? await r.json() : null;
            if (j && j.success) {
              setStatus('Saved', true);
              // Optional: close after short delay
              setTimeout(hideModal, 600);
            } else {
              setStatus('Save failed', false);
            }
          } catch (e) { setStatus('Save failed', false); }
        }

        openBtn && openBtn.addEventListener('click', async () => { showModal(); await loadSettings(); });
        closeEls.forEach(el => el.addEventListener('click', hideModal));
        saveBtn && saveBtn.addEventListener('click', saveSettings);
      } catch (_) {}
    })();
    </script>

    <!-- Shipping & Distance Settings Modal -->
    <div id="shippingSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="shippingSettingsTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="shippingSettingsTitle" class="admin-card-title">🚚 Shipping &amp; Distance Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-shipping-settings" aria-label="Close">×</button>
          <span class="modal-status-chip" id="shippingSettingsStatus" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="text-sm text-gray-600 mb-3">Configure carrier API keys and distance service. These enable live USPS/UPS/FedEx rates and local delivery eligibility by driving miles. Optional keys fall back safely if unset.</div>
          <form id="shippingSettingsForm" data-action="prevent-submit" class="space-y-4">
            <fieldset class="border rounded p-3">
              <legend class="text-sm font-semibold">USPS</legend>
              <label class="block text-sm font-medium mb-1" for="uspsUserId">USPS Web Tools USERID</label>
              <input id="uspsUserId" type="text" class="form-input w-full" placeholder="(required for USPS live rates)" />
            </fieldset>
            <fieldset class="border rounded p-3">
              <legend class="text-sm font-semibold">UPS</legend>
              <div class="grid gap-3 md:grid-cols-2">
                <div>
                  <label class="block text-sm font-medium mb-1" for="upsAccessKey">UPS Access Key</label>
                  <input id="upsAccessKey" type="text" class="form-input w-full" placeholder="optional" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1" for="upsSecret">UPS Secret</label>
                  <input id="upsSecret" type="password" class="form-input w-full" placeholder="optional" />
                </div>
              </div>
            </fieldset>
            <fieldset class="border rounded p-3">
              <legend class="text-sm font-semibold">FedEx</legend>
              <div class="grid gap-3 md:grid-cols-2">
                <div>
                  <label class="block text-sm font-medium mb-1" for="fedexKey">FedEx Key</label>
                  <input id="fedexKey" type="text" class="form-input w-full" placeholder="optional" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1" for="fedexSecret">FedEx Secret</label>
                  <input id="fedexSecret" type="password" class="form-input w-full" placeholder="optional" />
                </div>
              </div>
            </fieldset>
            <fieldset class="border rounded p-3">
              <legend class="text-sm font-semibold">Driving Distance</legend>
              <label class="block text-sm font-medium mb-1" for="orsKey">OpenRouteService API Key</label>
              <input id="orsKey" type="text" class="form-input w-full" placeholder="optional (used for driving miles)" />
            </fieldset>
            <div class="flex items-center justify-between pt-2">
              <div class="text-sm text-gray-600">Changes apply immediately. Cache TTL is 24h; rates/distance are auto-cached.</div>
              <div class="flex gap-2">
                <button type="button" class="btn-secondary" data-action="close-shipping-settings">Close</button>
                <button type="button" class="btn-brand" id="shippingSettingsSaveBtn">Save Settings</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Dev Status Dashboard Modal (iframe embed) -->
    <div id="devStatusModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="devStatusTitle">
      <div class="admin-modal admin-modal-content w-[90vw] h-[85vh]">
        <div class="modal-header">
          <h2 id="devStatusTitle" class="admin-card-title">🧪 Dev Status Dashboard</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <iframe id="devStatusFrame" title="Dev Status" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/dev/status.php" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>
  </noscript>

  <!-- Root containers the JS module can enhance -->
  <div id="adminSettingsRoot" class="admin-settings-root">
    <!-- Settings cards grid using legacy classes -->
    <div class="settings-grid">
      <?php // Content Management ?>
      <?php ob_start(); ?>
        <button type="button" id="dashboardConfigBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-dashboard-config">Dashboard Configuration</button>
        <button type="button" id="categoriesBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-categories">Category Management</button>
        <button type="button" id="attributesBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-attributes">Genders, Sizes, &amp; Colors</button>
        <button type="button" id="templateManagerBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-template-manager">Template Manager</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-blue', 'Content Management', 'Organize products, categories, and room content', $__content); ?>

      <?php // Visual & Design ?>
      <?php ob_start(); ?>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-css-catalog">CSS Catalog</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-background-manager">Background Manager</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-map-editor">Room Map Editor</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-area-item-mapper">Area-Item Mapper</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-purple', 'Visual & Design', 'Customize appearance and interactive elements', $__content); ?>

      <?php // Business & Analytics ?>
      <?php ob_start(); ?>
        <button type="button" id="businessInfoBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-business-info">Business Information</button>
        <button type="button" id="squareSettingsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-square-settings">Configure Square</button>
        <button type="button" id="aiSettingsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-ai-settings">AI Provider</button>
        <button type="button" id="aiToolsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-ai-tools">AI &amp; Automation Tools</button>
        <button type="button" id="shippingSettingsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-shipping-settings">Shipping &amp; Distance Settings</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-emerald', 'Business & Analytics', 'Manage sales, promotions, and business insights', $__content); ?>

      <?php // Communication ?>
      <?php ob_start(); ?>
        <button type="button" id="emailConfigBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-settings">Email Configuration</button>
        <button type="button" id="emailHistoryBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-history">Email History</button>
        <button type="button" id="emailTestBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-test">Send Sample Email</button>
        <button type="button" id="loggingStatusBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-logging-status">Logging Status</button>
        <a class="admin-settings-button btn-primary btn-full-width" href="/receipt.php">Receipt Messages</a>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-orange', 'Communication', 'Email configuration and customer messaging', $__content); ?>

      <?php // Technical & System ?>
      <?php ob_start(); ?>
        <button type="button" id="accountSettingsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-account-settings">Account Settings</button>
        <button type="button" id="secretsManagerBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-secrets-modal">Secrets Manager</button>
        <button type="button" id="healthDiagnosticsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-health-diagnostics">Health & Diagnostics</button>
        <a class="admin-settings-button btn-primary btn-full-width" href="/sections/admin_router.php?section=cost-breakdown-manager">Cost Breakdown Manager</a>
        <a class="admin-settings-button btn-primary btn-full-width" href="/sections/admin_router.php?section=customers">User Manager</a>
        <a class="admin-settings-button btn-primary btn-full-width" href="/sections/admin_router.php?section=reports-browser">Reports &amp; Documentation Browser</a>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-red', 'Technical & System', 'System tools and advanced configuration', $__content); ?>
    </div>

    <!-- Health & Diagnostics Modal (hidden by default) -->
    <div id="healthModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="healthTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="healthTitle" class="admin-card-title">🩺 Health &amp; Diagnostics</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="flex items-center justify-between mb-3">
            <div id="healthStatus" class="text-sm text-gray-600">Loading…</div>
            <div class="flex items-center gap-2">
              <button type="button" class="btn-secondary" data-action="health-refresh">Refresh</button>
              <a class="btn" href="/sections/admin_router.php?section=dashboard#background">Background Manager</a>
              <a class="btn" href="/sections/admin_router.php?section=inventory">Inventory</a>
            </div>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="border rounded p-3">
              <div class="font-semibold mb-2">Backgrounds</div>
              <div class="text-sm text-gray-600 mb-2">Active configuration per room (0 = landing)</div>
              <div class="text-sm mb-1">Missing Active: <span id="bgMissingActiveCount">0</span></div>
              <ul id="bgMissingActiveList" class="list-disc ml-4 text-sm"></ul>
              <div class="text-sm mt-3 mb-1">Missing Files: <span id="bgMissingFilesCount">0</span></div>
              <ul id="bgMissingFilesList" class="list-disc ml-4 text-sm"></ul>
            </div>
            <div class="border rounded p-3">
              <div class="font-semibold mb-2">Items</div>
              <div class="text-sm mb-1">No Primary Image: <span id="itemsNoPrimaryCount">0</span></div>
              <ul id="itemsNoPrimaryList" class="list-disc ml-4 text-sm max-h-56 overflow-auto"></ul>
              <div class="text-sm mt-3 mb-1">Missing Image Files: <span id="itemsMissingFilesCount">0</span></div>
              <ul id="itemsMissingFilesList" class="list-disc ml-4 text-sm max-h-56 overflow-auto"></ul>
            </div>
          </div>

          <div class="border rounded p-3 mt-4">
            <div class="font-semibold mb-2">Advanced Diagnostics</div>
            <div class="text-sm text-gray-600 mb-2">Extra checks and dashboards for development and troubleshooting.</div>
            <div class="flex flex-wrap gap-2 mb-3">
              <button type="button" class="btn" data-action="open-dev-status">Open Dev Status Dashboard</button>
              <button type="button" class="btn-secondary" data-action="run-health-check">Run /health.php</button>
              <button type="button" class="btn-secondary" data-action="scan-item-images">Scan Item Images</button>
            </div>
            <pre id="advancedHealthOutput" class="text-xs bg-gray-50 p-2 rounded border max-h-64 overflow-auto" aria-live="polite"></pre>
          </div>
        </div>
      </div>
    </div>

    <!-- Area-Item Mapper Modal (hidden by default) -->
    <div id="areaItemMapperModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="areaItemMapperTitle">
      <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
        <div class="modal-header">
          <h2 id="areaItemMapperTitle" class="admin-card-title">🧭 Area-Item Mapper</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <iframe id="areaItemMapperFrame" title="Area-Item Mapper" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/area_item_mapper.php?modal=1" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>



    <!-- Room Map Editor Modal (iframe embed) -->
    <div id="roomMapEditorModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="roomMapEditorTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="roomMapEditorTitle" class="admin-card-title">��️ Room Map Editor</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="roomMapEditorFrame" title="Room Map Editor" src="about:blank" data-src="/sections/tools/room_map_editor.php?modal=1" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>
    <!-- Template Manager Modal (iframe embed) -->
    <div id="templateManagerModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="templateManagerTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="templateManagerTitle" class="admin-card-title">📁 Template Manager</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="templateManagerFrame" title="Template Manager" src="about:blank" data-src="/sections/admin_router.php?section=reports&modal=1" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- Business Info Modal (native form, best practice) -->
    <div id="businessInfoModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="businessInfoTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="businessInfoTitle" class="admin-card-title">🏢 Business Information</h2>
          <button type="button" class="admin-modal-close" data-action="close-business-info" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <form id="businessInfoForm" data-action="prevent-submit" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizName" class="block text-sm font-medium mb-1">Business Name</label>
                <input id="bizName" type="text" class="form-input w-full" placeholder="(ie: Whimsical Frog)" />
              </div>
              <div>
                <label for="bizEmail" class="block text-sm font-medium mb-1">Business Email</label>
                <input id="bizEmail" type="email" class="form-input w-full" placeholder="(ie: info@yourdomain.com)" />
              </div>
              <div class="mt-4 flex items-center justify-between">
                <div id="brandPreviewCard" class="rounded border p-3">
                  <div id="brandPreviewTitle" class="font-bold mb-1">Brand Preview Title</div>
                  <div id="brandPreviewText" class="text-sm">This is sample content using your brand fonts and colors.</div>
                  <div id="brandPreviewSwatches" class="flex gap-2 mt-2">
                    <div title="Primary"></div>
                    <div title="Secondary"></div>
                    <div title="Accent"></div>
                  </div>
                </div>
                <div class="flex flex-col gap-2">
                    <button type="button" class="btn-brand" data-action="business-save-branding">Save Branding</button>
                    <button type="button" class="btn-secondary" data-action="business-reset-branding">Reset Branding</button>
                  </div>
              </div>
            </div>

            <div class="border-t pt-4 mt-4">
              <h3 class="text-sm font-semibold mb-2">Brand Fonts</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="brandFontPrimary" class="block text-sm font-medium mb-1">Primary Font</label>
                  <input id="brandFontPrimary" type="text" class="form-input w-full" placeholder="(ie: Inter, system-ui)" />
                </div>
                <div>
                  <label for="brandFontSecondary" class="block text-sm font-medium mb-1">Secondary Font</label>
                  <input id="brandFontSecondary" type="text" class="form-input w-full" placeholder="(ie: Merriweather)" />
                </div>
              </div>
            </div>

                        <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizWebsite" class="block text-sm font-medium mb-1">Website</label>
                <input id="bizWebsite" type="url" class="form-input w-full" placeholder="(ie: https://yourdomain.com)" />
              </div>
              <div>
                <label for="bizLogoUrl" class="block text-sm font-medium mb-1">Logo URL</label>
                <input id="bizLogoUrl" type="url" class="form-input w-full" placeholder="(ie: https://yourdomain.com/logo.png)" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizTagline" class="block text-sm font-medium mb-1">Tagline</label>
                <input id="bizTagline" type="text" class="form-input w-full" placeholder="(ie: Great deals, great vibes)" />
              </div>
              <div>
                <label for="bizDescription" class="block text-sm font-medium mb-1">Short Description</label>
                <input id="bizDescription" type="text" class="form-input w-full" placeholder="(ie: What customers should know)" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizPhone" class="block text-sm font-medium mb-1">Phone</label>
                <input id="bizPhone" type="text" class="form-input w-full" placeholder="(ie: (555) 555-5555)" />
              </div>
              <div>
                <label for="bizHours" class="block text-sm font-medium mb-1">Hours</label>
                <input id="bizHours" type="text" class="form-input w-full" placeholder="(ie: Mon-Fri 9am-5pm)" />
              </div>
            </div>
            <div>
              <label for="bizAddress" class="block text-sm font-medium mb-1">Address</label>
              <textarea id="bizAddress" class="form-textarea w-full" rows="3" placeholder="(ie: 123 Main St, City, ST 00000)"></textarea>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="bizAddress2" class="block text-sm font-medium mb-1">Address 2</label>
                <input id="bizAddress2" type="text" class="form-input w-full" placeholder="Suite 100" />
              </div>
              <div>
                <label for="bizCity" class="block text-sm font-medium mb-1">City</label>
                <input id="bizCity" type="text" class="form-input w-full" />
              </div>
              <div>
                <label for="bizState" class="block text-sm font-medium mb-1">State/Region</label>
                <input id="bizState" type="text" class="form-input w-full" />
              </div>
              <div>
                <label for="bizPostal" class="block text-sm font-medium mb-1">Postal Code</label>
                <input id="bizPostal" type="text" class="form-input w-full" />
              </div>
              <div>
                <label for="bizCountry" class="block text-sm font-medium mb-1">Country</label>
                <input id="bizCountry" type="text" class="form-input w-full" placeholder="US" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizSupportEmail" class="block text-sm font-medium mb-1">Support Email</label>
                <input id="bizSupportEmail" type="email" class="form-input w-full" placeholder="support@yourdomain.com" />
              </div>
              <div>
                <label for="bizSupportPhone" class="block text-sm font-medium mb-1">Support Phone</label>
                <input id="bizSupportPhone" type="text" class="form-input w-full" placeholder="(555) 555-5556" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="bizFacebook" class="block text-sm font-medium mb-1">Facebook URL</label>
                <input id="bizFacebook" type="url" class="form-input w-full" placeholder="https://facebook.com/yourpage" />
              </div>
              <div>
                <label for="bizInstagram" class="block text-sm font-medium mb-1">Instagram URL</label>
                <input id="bizInstagram" type="url" class="form-input w-full" placeholder="https://instagram.com/yourhandle" />
              </div>
              <div>
                <label for="bizTwitter" class="block text-sm font-medium mb-1">Twitter/X URL</label>
                <input id="bizTwitter" type="url" class="form-input w-full" placeholder="https://x.com/yourhandle" />
              </div>
              <div>
                <label for="bizTikTok" class="block text-sm font-medium mb-1">TikTok URL</label>
                <input id="bizTikTok" type="url" class="form-input w-full" placeholder="https://tiktok.com/@yourhandle" />
              </div>
              <div>
                <label for="bizYouTube" class="block text-sm font-medium mb-1">YouTube URL</label>
                <input id="bizYouTube" type="url" class="form-input w-full" placeholder="https://youtube.com/@yourchannel" />
              </div>
              <div>
                <label for="bizLinkedIn" class="block text-sm font-medium mb-1">LinkedIn URL</label>
                <input id="bizLinkedIn" type="url" class="form-input w-full" placeholder="https://linkedin.com/company/yourco" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="bizTermsUrl" class="block text-sm font-medium mb-1">Terms URL</label>
                <input id="bizTermsUrl" type="url" class="form-input w-full" placeholder="https://.../terms" />
              </div>
              <div>
                <label for="bizPrivacyUrl" class="block text-sm font-medium mb-1">Privacy URL</label>
                <input id="bizPrivacyUrl" type="url" class="form-input w-full" placeholder="https://.../privacy" />
              </div>
              <div>
                <label for="bizTaxId" class="block text-sm font-medium mb-1">Tax ID</label>
                <input id="bizTaxId" type="text" class="form-input w-full" placeholder="EIN/Tax ID" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="bizTimezone" class="block text-sm font-medium mb-1">Timezone</label>
                <input id="bizTimezone" type="text" class="form-input w-full" placeholder="America/New_York" />
              </div>
              <div>
                <label for="bizCurrency" class="block text-sm font-medium mb-1">Currency</label>
                <input id="bizCurrency" type="text" class="form-input w-full" placeholder="USD" />
              </div>
              <div>
                <label for="bizLocale" class="block text-sm font-medium mb-1">Locale</label>
                <input id="bizLocale" type="text" class="form-input w-full" placeholder="en-US" />
              </div>
            </div>

            <div class="border-t pt-4 mt-4">
              <h3 class="text-sm font-semibold mb-2">Footer</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="footerNote" class="block text-sm font-medium mb-1">Footer Note</label>
                  <textarea id="footerNote" class="form-textarea w-full" rows="2" placeholder="Short note shown in footer"></textarea>
                </div>
                <div>
                  <label for="footerHtml" class="block text-sm font-medium mb-1">Footer HTML</label>
                  <textarea id="footerHtml" class="form-textarea w-full" rows="2" placeholder="Custom HTML (safe subset)"></textarea>
                </div>
              </div>
            </div>

            <div class="border-t pt-4 mt-4">
              <h3 class="text-sm font-semibold mb-2">Store Policies</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="returnPolicy" class="block text-sm font-medium mb-1">Return Policy</label>
                  <textarea id="returnPolicy" class="form-textarea w-full" rows="3"></textarea>
                </div>
                <div>
                  <label for="shippingPolicy" class="block text-sm font-medium mb-1">Shipping Policy</label>
                  <textarea id="shippingPolicy" class="form-textarea w-full" rows="3"></textarea>
                </div>
              </div>
              <div class="grid gap-4 md:grid-cols-2 mt-4">
                <div>
                  <label for="warrantyPolicy" class="block text-sm font-medium mb-1">Warranty Policy</label>
                  <textarea id="warrantyPolicy" class="form-textarea w-full" rows="3"></textarea>
                </div>
                <div>
                  <label for="policyUrl" class="block text-sm font-medium mb-1">Store Policy URL</label>
                  <input id="policyUrl" type="url" class="form-input w-full" placeholder="https://.../policies" />
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div id="businessInfoStatus" class="text-sm text-gray-600"></div>
              <button type="button" class="btn-brand" data-action="business-save">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Email Settings Modal (lightweight shell; bridge populates) -->
    <div id="emailSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="emailSettingsTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="emailSettingsTitle" class="admin-card-title">✉️ Email Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-email-settings" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="text-sm text-gray-600 mb-2">Configure sender and SMTP options. Fields will load automatically.</div>
          <form id="emailConfigForm" data-action="prevent-submit" class="space-y-3">
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label for="fromEmail" class="block text-sm font-medium mb-1">From Email</label>
                <input id="fromEmail" type="email" class="form-input w-full" placeholder="you@domain.com" />
              </div>
              <div>
                <label for="fromName" class="block text-sm font-medium mb-1">From Name</label>
                <input id="fromName" type="text" class="form-input w-full" placeholder="Your Business" />
              </div>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label for="adminEmail" class="block text-sm font-medium mb-1">Admin Email</label>
                <input id="adminEmail" type="email" class="form-input w-full" />
              </div>
              <div>
                <label for="bccEmail" class="block text-sm font-medium mb-1">BCC Email</label>
                <input id="bccEmail" type="email" class="form-input w-full" />
              </div>
            </div>
            <div>
              <label for="replyToEmail" class="block text-sm font-medium mb-1">Reply-To</label>
              <input id="replyToEmail" type="email" class="form-input w-full" />
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label for="testRecipient" class="block text-sm font-medium mb-1">Test Recipient</label>
                <input id="testRecipient" type="email" class="form-input w-full" placeholder="test@domain.com" />
              </div>
              <div class="flex items-end">
                <button type="button" class="btn-secondary" data-action="email-send-test">Send Test</button>
              </div>
            </div>
            <div>
              <label class="inline-flex items-center gap-2"><input id="smtpEnabled" type="checkbox" /><span>Enable SMTP</span></label>
            </div>
            <div id="smtpSettings" class="grid gap-3 md:grid-cols-2 hidden">
              <div>
                <label for="smtpHost" class="block text-sm font-medium mb-1">SMTP Host</label>
                <input id="smtpHost" type="text" class="form-input w-full" placeholder="smtp.gmail.com" />
              </div>
              <div>
                <label for="smtpPort" class="block text-sm font-medium mb-1">SMTP Port</label>
                <input id="smtpPort" type="number" class="form-input w-full" placeholder="465" />
              </div>
              <div>
                <label for="smtpUsername" class="block text-sm font-medium mb-1">SMTP Username</label>
                <input id="smtpUsername" type="text" class="form-input w-full" />
              </div>
              <div>
                <label for="smtpPassword" class="block text-sm font-medium mb-1">SMTP Password</label>
                <input id="smtpPassword" type="password" class="form-input w-full" />
              </div>
              <div>
                <label for="smtpEncryption" class="block text-sm font-medium mb-1">Encryption</label>
                <select id="smtpEncryption" class="form-select w-full">
                  <option value="">None</option>
                  <option value="ssl">SSL</option>
                  <option value="tls">TLS</option>
                </select>
              </div>
              <div>
                <label for="smtpTimeout" class="block text-sm font-medium mb-1">Timeout (sec)</label>
                <input id="smtpTimeout" type="number" class="form-input w-full" />
              </div>
              <div class="col-span-2">
                <label class="inline-flex items-center gap-2"><input id="smtpAuth" type="checkbox" /><span>Use SMTP Auth</span></label>
              </div>
              <div class="col-span-2">
                <label class="inline-flex items-center gap-2"><input id="smtpDebug" type="checkbox" /><span>Enable SMTP Debug</span></label>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Email History Modal (dedicated UI) -->
    <div id="emailHistoryModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="emailHistoryTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="emailHistoryTitle" class="admin-card-title">📬 Email History</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <input id="emailHistorySearch" type="text" class="form-input" placeholder="Search subject, to, type..." />
            <button type="button" class="btn-secondary" data-action="email-history-search">Search</button>
            <span class="mx-2 text-sm text-gray-500">|</span>
            <input id="emailHistoryFrom" type="date" class="form-input" />
            <input id="emailHistoryTo" type="date" class="form-input" />
            <input id="emailHistoryType" type="text" class="form-input" placeholder="Type (e.g., order_confirmation)" list="emailTypeOptions" />
            <select id="emailHistorySort" class="form-select">
              <option value="sent_at_desc">Sort: Sent At (newest)</option>
              <option value="sent_at_asc">Sort: Sent At (oldest)</option>
              <option value="subject_asc">Sort: Subject (A–Z)</option>
              <option value="subject_desc">Sort: Subject (Z–A)</option>
            </select>
            <select id="emailHistoryStatusFilter" class="form-select">
              <option value="">All Statuses</option>
              <option value="sent">Sent</option>
              <option value="failed">Failed</option>
              <option value="queued">Queued</option>
            </select>
            <button type="button" class="btn-secondary" data-action="email-history-apply-filters">Apply Filters</button>
            <button type="button" class="btn-secondary" data-action="email-history-clear-filters">Clear</button>
            <button type="button" class="btn-secondary" data-action="email-history-refresh">Refresh</button>
            <button type="button" class="btn-secondary" data-action="email-history-download">Download CSV</button>
            <div id="emailHistoryStatus" class="text-sm text-gray-600"></div>
          </div>
          <div id="emailHistoryList" class="border rounded-sm divide-y max-h-[60vh] overflow-auto">
            <!-- rows injected here -->
          </div>
          <div id="emailHistoryDrawerOverlay" class="email-drawer-overlay hidden" aria-hidden="true"></div>
          <!-- Detail Drawer -->
          <div id="emailHistoryDrawer" class="email-drawer hidden" aria-hidden="true" role="region" aria-label="Email Details">
            <div class="drawer-header flex items-center justify-between px-3 py-2 border-b">
              <div class="font-semibold">Email Details</div>
              <div class="flex items-center gap-3">
                <button type="button" class="text-sm" title="Close" aria-label="Close" data-action="email-history-close-drawer">✕</button>
              </div>
            </div>
            <div class="drawer-meta px-3 py-2 border-b text-xs" id="emailHistoryDrawerMeta">
              <div class="flex items-center gap-2"><span class="text-gray-500">Subject:</span> <span class="font-mono" id="ehdSubject"></span> <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-subject">Copy</button></div>
              <div class="flex items-center gap-2 mt-1"><span class="text-gray-500">To:</span> <span class="font-mono" id="ehdTo"></span> <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-to">Copy</button></div>
              <div class="flex items-center gap-2 mt-1"><span class="text-gray-500">Type:</span> <span class="font-mono" id="ehdType"></span> <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-type">Copy</button></div>
              <div class="flex items-center gap-3 mt-2">
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-headers">Copy Headers</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-toggle-json">Minify JSON</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-curl">Copy cURL (POST)</button>
                <span class="text-gray-400">→ paste your endpoint</span>
              </div>
              <div class="flex flex-wrap items-center gap-2 mt-2">
                <label for="ehdEndpoint" class="text-gray-500">Test endpoint:</label>
                <input id="ehdEndpoint" type="url" class="form-input text-xs" placeholder="https://your-endpoint.example/ingest" />
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-save-endpoint">Save</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-open-test">Open in new tab</button>
              </div>
            </div>
            <div class="p-3 text-xs overflow-auto" id="emailHistoryDrawerContent">
              <!-- populated by JS -->
            </div>
          </div>
          <datalist id="emailTypeOptions"></datalist>
          <div class="flex items-center justify-between mt-3">
            <button type="button" class="btn-secondary" data-action="email-history-prev">Prev</button>
            <div id="emailHistoryPage" class="text-sm text-gray-600">Page 1</div>
            <button type="button" class="btn-secondary" data-action="email-history-next">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Square Settings Modal (hidden by default) -->
    <div id="squareSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="squareSettingsTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="squareSettingsTitle" class="admin-card-title">🟩 Square Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-square-settings" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="flex items-center justify-between mb-2">
            <button id="squareSettingsBtn" type="button" class="btn-secondary">
              Status
              <span id="squareConfiguredChip" class="status-chip chip-off">Not configured</span>
            </button>
          </div>

          <!-- Connection Status -->
          <div id="squareConnectionStatus" class="mb-4 p-3 rounded-lg border border-gray-200 bg-gray-50">
            <div class="flex items-center gap-2">
              <span id="connectionIndicator" class="w-3 h-3 rounded-full bg-gray-400"></span>
              <span id="connectionText" class="text-sm text-gray-700">Not Connected</span>
            </div>
          </div>

          <!-- Config Form (client saves via JS) -->
          <form id="squareConfigForm" data-action="prevent-submit" class="space-y-4">
            <!-- Environment -->
            <div>
              <label class="block text-sm font-medium mb-1">Environment</label>
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="environment" value="sandbox" checked>
                  <span>Sandbox</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="environment" value="production">
                  <span>Production</span>
                </label>
              </div>
            </div>

            <!-- App ID / Location ID -->
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="squareAppId" class="block text-sm font-medium mb-1">Application ID</label>
                <input id="squareAppId" name="app_id" type="text" class="form-input w-full" placeholder="sq0idp-...">
              </div>
              <div>
                <label for="squareLocationId" class="block text-sm font-medium mb-1">Location ID</label>
                <input id="squareLocationId" name="location_id" type="text" class="form-input w-full" placeholder="L8K4...">
              </div>
            </div>

            <!-- Access Token (never prefilled) -->
            <div>
              <label for="squareAccessToken" class="block text-sm font-medium mb-1">Access Token</label>
              <input id="squareAccessToken" name="access_token" type="password" class="form-input w-full" placeholder="Paste your Square access token">
              <p class="text-xs text-gray-500 mt-1">Token is never prefetched for security. Saving will store it server-side.</p>
            </div>

            <!-- Sync options -->
            <div>
              <label class="block text-sm font-medium mb-2">Sync Options</label>
              <div class="grid gap-2 md:grid-cols-2">
                <label class="inline-flex items-center gap-2">
                  <input id="syncPrices" type="checkbox" checked>
                  <span>Sync Prices</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="syncInventory" type="checkbox" checked>
                  <span>Sync Inventory</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="syncDescriptions" type="checkbox">
                  <span>Sync Descriptions</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="autoSync" type="checkbox">
                  <span>Enable Auto Sync</span>
                </label>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <button id="saveSquareSettingsBtn" type="button" class="btn-brand" data-action="square-save-settings">Save Settings</button>
              <button type="button" class="btn-secondary btn--square-action" data-action="square-test-connection">Test Connection</button>
              <button type="button" class="btn-secondary btn--square-action" data-action="square-sync-items">Sync Items</button>
              <button type="button" class="btn-danger btn--square-action" data-action="square-clear-token">Clear Token</button>
            </div>

            <div id="connectionResult" class="text-sm text-gray-600"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Account Settings Modal (iframe embed) -->
    <div id="accountSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="accountSettingsTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="accountSettingsTitle" class="admin-card-title">👤 Account Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="accountSettingsFrame" title="Account Settings" src="about:blank" data-src="/sections/admin_router.php?section=account-settings&modal=1" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- AI Settings Modal (iframe embed) -->
    <div id="aiSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="aiSettingsTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="aiSettingsTitle" class="admin-card-title">🤖 AI Provider</h2>
          <button type="button" class="admin-modal-close" data-action="close-ai-settings" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="categoriesFrame" title="Category Management" src="about:blank" data-src="/category_management.php" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- AI Tools Modal (iframe embed) -->
    <div id="aiToolsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="aiToolsTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="aiToolsTitle" class="admin-card-title">🛠️ AI &amp; Automation Tools</h2>
          <button type="button" class="admin-modal-close" data-action="close-ai-tools" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="aiToolsFrame" title="AI &amp; Automation Tools" src="about:blank" data-src="/sections/admin_router.php?section=marketing&modal=1" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- Categories Modal (hidden by default) -->
    <div id="categoriesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="categoriesTitle">
      <div class="admin-modal admin-modal-content" style="max-width: 60rem; max-height: none;">
        <div class="modal-header">
          <h2 id="categoriesTitle" class="admin-card-title">📂 Category Management</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="space-y-4">
            <iframe src="/sections/admin_categories.php?modal=1" style="width:100%;height:100%;min-height:250px;border:none;" class="wf-admin-embed-frame"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- Attributes Management Modal (hidden by default) -->
    <div id="attributesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="attributesTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="attributesTitle" class="admin-card-title">🧩 Gender, Size &amp; Color Management</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div id="attributesResult" class="text-sm text-gray-500 mb-2"></div>
          <div class="grid gap-4 md:grid-cols-3">
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Gender</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="gender">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add gender (e.g., Unisex)" maxlength="64">
                <button type="submit" class="btn btn-brand" data-action="attr-add" data-type="gender">Add</button>
              </form>
              <ul id="attrListGender" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Size</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="size">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add size (e.g., XL)" maxlength="64">
                <button type="submit" class="btn btn-brand" data-action="attr-add" data-type="size">Add</button>
              </form>
              <ul id="attrListSize" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Color</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="color">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add color (e.g., Royal Blue)" maxlength="64">
                <button type="submit" class="btn btn-brand" data-action="attr-add" data-type="color">Add</button>
              </form>
              <ul id="attrListColor" class="attr-list space-y-1"></ul>
            </div>
          </div>
          <div class="attributes-actions flex justify-end mt-4">
            <button type="button" class="btn btn-secondary" data-action="attr-save-order">Save Order</button>
          </div>
        </div>
      </div>
    </div>

    <!-- CSS Rules Modal (hidden by default) -->
    <div id="cssRulesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="cssRulesTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="cssRulesTitle" class="admin-card-title">🎨 CSS Rules</h2>
          <button type="button" class="admin-modal-close" data-action="close-css-rules" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <form id="cssRulesForm" data-action="prevent-submit" class="space-y-4">
            <p class="text-sm text-gray-700">Edit core CSS variables used site-wide. Changes are saved to the database and applied instantly.</p>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="cssToastBg" class="block text-sm font-medium mb-1">Toast Background</label>
                <input id="cssToastBg" name="toast_bg" type="color" class="form-input w-full" value="#87ac3a" />
                <p class="text-xs text-gray-500">Maps to <code>--toast-bg</code></p>
              </div>
              <div>
                <label for="cssToastText" class="block text-sm font-medium mb-1">Toast Text</label>
                <input id="cssToastText" name="toast_text" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--toast-text</code></p>
              </div>
            </div>

            <!-- ... -->
          </form>
        </div>
      </div>
    </div>

    <!-- All modal logic handled by Vite bridge -->
    <!-- Dashboard Configuration Modal (hidden by default) -->
    <div id="dashboardConfigModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="dashboardConfigTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="dashboardConfigTitle" class="admin-card-title">⚙️ Dashboard Configuration</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="space-y-4">
            <p class="text-sm text-gray-700">Toggle which sections are active on your Dashboard, then click Save.</p>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm" id="dashboardSectionsTable">
                <thead>
                  <tr class="border-b">
                    <th class="p-2 text-left">Order</th>
                    <th class="p-2 text-left">Section</th>
                    <th class="p-2 text-left">Key</th>
                    <th class="p-2 text-left">Width</th>
                    <th class="p-2 text-left">Active</th>
                  </tr>
                </thead>
                <tbody id="dashboardSectionsBody"></tbody>
              </table>
            </div>
            <div class="flex justify-between items-center">
              <div id="dashboardConfigResult" class="text-sm text-gray-500"></div>
              <div class="flex items-center gap-2">
                <button type="button" class="btn" data-action="dashboard-config-reset">Reset to defaults</button>
                <button type="button" class="btn-secondary" data-action="dashboard-config-refresh">Refresh</button>
                <button type="button" class="btn-brand" data-action="dashboard-config-save">Save</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logging Status Modal (hidden by default) -->
    <div id="loggingStatusModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 class="admin-card-title">📜 Logging Status</h2>
          <button type="button" class="admin-modal-close" data-action="close-logging-status" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div class="space-y-3">
            <div id="loggingSummary" class="text-sm text-gray-700">Current log levels and destinations will appear here.</div>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-secondary" data-action="logging-refresh-status">Refresh</button>
              <button type="button" class="btn-secondary" data-action="logging-open-file">Open Latest Log File</button>
              <button type="button" class="btn-danger" data-action="logging-clear-logs">Clear Logs</button>
            </div>
            <div id="loggingStatusResult" class="status status--info"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Secrets Manager Modal (hidden by default) -->
    <div id="secretsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 class="admin-card-title">🔒 Secrets Manager</h2>
          <button type="button" class="admin-modal-close" data-action="close-secrets-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="secretsForm" data-action="prevent-submit" class="space-y-4">
            <input type="hidden" id="secretsCsrf" value="<?= htmlspecialchars((string)($__secrets_csrf ?? '')) ?>">
            <p class="text-sm text-gray-700">Paste JSON or key=value lines to update secrets. Sensitive values are never prefilled.</p>
            <textarea id="secretsPayload" name="secrets_payload" class="form-textarea w-full" rows="8" placeholder='{"SMTP_PASS":"..."}'></textarea>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-brand" data-action="secrets-save">Save Secrets</button>
              <button type="button" class="btn-secondary" data-action="secrets-rotate">Rotate Keys</button>
              <button type="button" class="btn-secondary" data-action="secrets-export">Export Secrets</button>
            </div>
            <div id="secretsResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    <!-- AI Settings Modal (hidden by default) -->
    <div id="aiSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 class="admin-card-title">🤖 AI Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-ai-settings" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="aiSettingsForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="aiProvider" class="block text-sm font-medium mb-1">AI Provider</label>
                <select id="aiProvider" name="ai_provider" class="form-select w-full">
                  <option value="jons_ai">Jon&apos;s AI (Local)</option>
                  <option value="openai">OpenAI</option>
                  <option value="anthropic">Anthropic</option>
                  <option value="google">Google AI</option>
                </select>
              </div>
              <div>
                <label for="aiTemperature" class="block text-sm font-medium mb-1">Temperature</label>
                <input id="aiTemperature" name="ai_temperature" type="range" min="0" max="1" step="0.1" class="w-full" />
                <span id="aiTemperatureValue" class="text-xs text-gray-500">0.7</span>
              </div>
            </div>

            <div id="aiProviderSettings">
              <!-- Dynamic settings will be populated here based on selected provider -->
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="aiMaxTokens" class="block text-sm font-medium mb-1">Max Tokens</label>
                <input id="aiMaxTokens" name="ai_max_tokens" type="number" min="100" max="4000" class="form-input w-full" value="1000" />
              </div>
              <div>
                <label for="aiTimeout" class="block text-sm font-medium mb-1">Timeout (seconds)</label>
                <input id="aiTimeout" name="ai_timeout" type="number" min="5" max="120" class="form-input w-full" value="30" />
              </div>
            </div>

            <div class="flex items-center">
              <input id="fallbackToLocal" name="fallback_to_local" type="checkbox" class="mr-2" />
              <label for="fallbackToLocal" class="text-sm">Fallback to local AI if external API fails</label>
            </div>

            <div class="flex justify-between items-center">
              <div id="aiSettingsResult" class="text-sm text-gray-500"></div>
              <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary" data-action="test-ai-provider">Test Provider</button>
                <button type="button" class="btn-brand" data-action="save-ai-settings">Save Settings</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
?>
<!-- WF: SETTINGS WRAPPER END -->
<script>
(() => {
  const MODAL = 'dashboardConfigModal';
  const TBODY = 'dashboardSectionsBody';
  const STATUS = 'dashboardConfigResult';

  // Fallback: if API returns no available_sections, use this local map
  const FALLBACK_SECTIONS = {
    metrics: { title: '📊 Quick Metrics' },
    recent_orders: { title: '📋 Recent Orders' },
    low_stock: { title: '⚠️ Low Stock Alerts' },
    inventory_summary: { title: '📦 Inventory Summary' },
    customer_summary: { title: '👥 Customer Overview' },
    marketing_tools: { title: '📈 Marketing Tools' },
    order_fulfillment: { title: '🚚 Order Fulfillment' },
    reports_summary: { title: '📊 Reports Summary' }
  };

  const updateOrderNumbers=()=>{document.querySelectorAll('#'+TBODY+' tr').forEach((row,i)=>{const span=row.querySelector('span');if(span)span.textContent=i+1;});};
  const setStatus=(m,ok)=>{const s=document.getElementById(STATUS);if(!s)return;s.textContent=m||'';s.classList.toggle('text-green-600',!!ok);s.classList.toggle('text-red-600',ok===false);};
  const show=()=>{const el=document.getElementById(MODAL);if(el){el.classList.remove('hidden');el.classList.add('show');el.removeAttribute('aria-hidden');}};
  const api=function(u,p){return fetch(u,{method:p?'POST':'GET',headers:p?{'Content-Type':'application/json'}:{},body:p?JSON.stringify(p):undefined,credentials:'include'}).then(function(r){return r.text()}).then(function(t){var j={};try{j=JSON.parse(t)}catch(_){throw new Error('Non-JSON')}if(!j.success)throw new Error(j.error||'Bad');return j.data||{}});};
  const get=()=>api('/api/dashboard_sections.php?action=get_sections');

  const draw = function(d) {
    console.log('🎯 Dashboard Config draw() called with data:', d);
    var tb = document.getElementById(TBODY);
    if (!tb) {
      console.error('❌ Dashboard Config: tbody not found:', TBODY);
      return;
    }

    var avail = (d && d.available_sections) ? d.available_sections : {};
    if (!avail || Object.keys(avail).length === 0) {
      console.warn('⚠️ Dashboard Config: available_sections missing from API response; using FALLBACK_SECTIONS');
      avail = FALLBACK_SECTIONS;
    }
    var act = (d && Array.isArray(d.sections)) ? d.sections : [];
    var ks = new Set(Object.keys(avail));

    act.forEach(function(s) {
      if (s && s.section_key) ks.add(s.section_key);
    });

    var active = new Set(act.map(function(s) { return s.section_key; }));
    var sectionData = Array.from(ks).sort().map(function(k, i) {
      var found = null;
      for (var ii = 0; ii < act.length; ii++) {
        if (act[ii] && act[ii].section_key === k) {
          found = act[ii];
          break;
        }
      }

      var section = found || {
        section_key: k,
        display_order: i + 1,
        is_active: 0,
        show_title: 1,
        show_description: 1,
        custom_title: null,
        custom_description: null,
        width_class: 'half-width'
      };

      var info = avail[k];
      return {
        key: k,
        title: (info && info.title) || k,
        active: active.has(k),
        order: section.display_order || (i + 1),
        width: section.width_class || 'half-width',
        section_key: section.section_key,
        display_order: section.display_order,
        is_active: section.is_active,
        show_title: section.show_title,
        show_description: section.show_description,
        custom_title: section.custom_title,
        custom_description: section.custom_description,
        width_class: section.width_class
      };
    });

    console.log('📊 Dashboard Config sectionData:', sectionData);
    tb.innerHTML = '';

    sectionData.forEach(function(item, i) {
      var tr = document.createElement('tr');
      tr.className = 'border-b';

      var html = '<td class="p-2">' +
        '<div class="flex items-center gap-1">' +
        '<button class="text-xs p-1" data-action="move-up" data-key="' + item.key + '" ' + (i === 0 ? 'disabled' : '') + '>▲</button>' +
        '<button class="text-xs p-1" data-action="move-down" data-key="' + item.key + '" ' + (i === sectionData.length - 1 ? 'disabled' : '') + '>▼</button>' +
        '<span class="ml-1 text-gray-500">' + item.order + '</span>' +
        '</div>' +
        '</td>' +
        '<td class="p-2">' + item.title + '</td>' +
        '<td class="p-2"><code>' + item.key + '</code></td>' +
        '<td class="p-2">' +
        '<select class="dash-width text-xs" data-key="' + item.key + '">' +
        '<option value="half-width" ' + (item.width === 'half-width' ? 'selected' : '') + '>Half</option>' +
        '<option value="full-width" ' + (item.width === 'full-width' ? 'selected' : '') + '>Full</option>' +
        '</select>' +
        '</td>' +
        '<td class="p-2">' +
        '<input type="checkbox" class="dash-active" data-key="' + item.key + '" ' + (item.active ? 'checked' : '') + '>' +
        '</td>';

      tr.innerHTML = html;
      tb.appendChild(tr);
    });

    console.log('✅ Dashboard Config: Successfully rendered', sectionData.length, 'sections');
  };
  const payload = function() {
    var rows = Array.prototype.slice.call(document.querySelectorAll('#' + TBODY + ' tr'));
    return {
      action: 'update_sections',
      sections: rows.map(function(row, i) {
        var elA = row.querySelector('.dash-active');
        var key = (elA && elA.dataset) ? elA.dataset.key : undefined;
        var elW = row.querySelector('.dash-width');
        var width = (elW ? elW.value : 'half-width');
        var active = (elA && elA.checked ? 1 : 0);
        return {
          key: key,
          section_key: key,
          display_order: i + 1,
          is_active: active,
          show_title: 1,
          show_description: 1,
          custom_title: null,
          custom_description: null,
          width_class: width
        };
      }).filter(function(s) { return s.key; })
    };
  };

  // Add direct click handler to the button to bypass Vite module interference
  const attachDashboardConfigButtonHandler = function() {
    const dashboardBtn = document.getElementById('dashboardConfigBtn');
    if (dashboardBtn && !dashboardBtn.__wfDashboardClickAttached) {
      dashboardBtn.__wfDashboardClickAttached = true;
      dashboardBtn.addEventListener('click', function(e) {
        console.log('🎯 Dashboard Config button clicked directly');
        e.preventDefault();
        e.stopPropagation();
        show();
        setStatus('Loading…', true);
        get().then(function(data) {
          console.log('🎯 Dashboard Config API response:', data);
          draw(data);
          setStatus('Loaded', true);
        }).catch(function(error) {
          console.error('❌ Dashboard Config API error:', error);
          console.warn('⚠️ Falling back to default sections');
          try {
            draw({ sections: [], available_sections: FALLBACK_SECTIONS });
            setStatus('Loaded (defaults)', true);
          } catch (e2) {
            setStatus('Load failed', false);
          }
        });
      });
      console.log('✅ Dashboard Config: Direct click handler attached');
    } else if (!dashboardBtn) {
      console.error('❌ Dashboard Config: Button not found with ID dashboardConfigBtn');
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachDashboardConfigButtonHandler);
  } else {
    attachDashboardConfigButtonHandler();
  }

  document.addEventListener('click', function(e) {
    const a = e.target.closest('[data-action]');
    if (!a) return;
    const action = a.dataset.action;

    // Skip open-dashboard-config since we handle it directly above
    if (action === 'open-dashboard-config') {
      return; // Let the direct handler take care of it
    }
    if (action === 'dashboard-config-refresh') {
      e.preventDefault();
      setStatus('Refreshing…', true);
      get().then(function(data) {
        draw(data);
        setStatus('Refreshed', true);
      }).catch(function(err) {
        console.error('❌ Dashboard Config refresh error:', err);
        console.warn('⚠️ Falling back to default sections');
        try {
          draw({ sections: [], available_sections: FALLBACK_SECTIONS });
          setStatus('Refreshed (defaults)', true);
        } catch (e2) {
          setStatus('Refresh failed', false);
        }
      });
    }
    if (action === 'dashboard-config-reset') {
      e.preventDefault();
      setStatus('Resetting…', true);
      api('/api/dashboard_sections.php?action=reset_defaults').then(function() {
        return get();
      }).then(function(data) {
        draw(data);
        setStatus('Defaults restored', true);
      }).catch(function() {
        setStatus('Reset failed', false);
      });
    }
    if (action === 'dashboard-config-save') {
      e.preventDefault();
      const p = payload();
      if (!p.sections.length) {
        setStatus('Select at least one', false);
        return;
      }
      setStatus('Saving…', true);
      api('/api/dashboard_sections.php?action=update_sections', p).then(function() {
        setStatus('Saved', true);
      }).catch(function() {
        setStatus('Save failed', false);
      });
    }
    if (action === 'move-up') {
      e.preventDefault();
      const key = e.target.dataset.key;
      const rows = Array.prototype.slice.call(document.querySelectorAll('#' + TBODY + ' tr'));
      const idx = rows.findIndex(function(r) {
        var __el = r.querySelector('.dash-active');
        return (__el && __el.dataset ? __el.dataset.key : undefined) === key;
      });
      if (idx > 0) {
        rows[idx].parentNode.insertBefore(rows[idx], rows[idx - 1]);
        updateOrderNumbers();
      }
    }
    if (action === 'move-down') {
      e.preventDefault();
      const key = e.target.dataset.key;
      const rows = Array.prototype.slice.call(document.querySelectorAll('#' + TBODY + ' tr'));
      const idx = rows.findIndex(function(r) {
        var __el = r.querySelector('.dash-active');
        return (__el && __el.dataset ? __el.dataset.key : undefined) === key;
      });
      if (idx < rows.length - 1) {
        rows[idx].parentNode.insertBefore(rows[idx + 1], rows[idx]);
        updateOrderNumbers();
      }
    }
  });
})();
</script>

    </div>
    </div>
</div>
