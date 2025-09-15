<?php
// Admin Settings (JS-powered). Renders the wrapper the module expects and seeds minimal context.
// Guard auth if helper exists
if (function_exists('isLoggedIn') && !isLoggedIn()) {
    echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login to access settings</h1></div>';
    return;
}

// Current user for account prefill
$userData = function_exists('getCurrentUser') ? (getCurrentUser() ?? []) : [];
$uid = $userData['id'] ?? ($userData['userId'] ?? '');
$firstNamePrefill = $userData['firstName'] ?? ($userData['first_name'] ?? '');
$lastNamePrefill = $userData['lastName'] ?? ($userData['last_name'] ?? '');
$emailPrefill = $userData['email'] ?? '';

// Basic page title to match admin design
?>
<!-- WF: SETTINGS WRAPPER START -->
<div class="settings-page container mx-auto px-4 py-6" data-page="admin-settings" data-user-id="<?= htmlspecialchars((string)$uid) ?>">
  <noscript>
    <div class="admin-alert alert-warning">
      JavaScript is required to use the Settings page.
    </div>
  </noscript>

  <!-- Root containers the JS module can enhance -->
  <div id="adminSettingsRoot" class="admin-settings-root">
    <!-- Settings cards grid using legacy classes -->
    <div class="settings-grid">
      <!-- Content Management -->
      <section class="settings-section content-section card-theme-blue">
        <header class="section-header">
          <h3 class="section-title">Content Management</h3>
          <p class="section-description">Organize products, categories, and room content</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-dashboard-config">Dashboard Configuration</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-categories">Categories</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-attributes">Genders, Sizes, &amp; Colors</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-settings" onclick="return window.__openLegacyModal && window.__openLegacyModal('room-settings');">Room Settings</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-category-links" onclick="return window.__openLegacyModal && window.__openLegacyModal('room-category-links');">Room-Category Links</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-template-manager" onclick="return window.__openLegacyModal && window.__openLegacyModal('template-manager');">Template Manager</button>
        </div>
      </section>

      <!-- Visual & Design -->
      <section class="settings-section visual-section card-theme-purple">
        <header class="section-header">
          <h3 class="section-title">Visual &amp; Design</h3>
          <p class="section-description">Customize appearance and interactive elements</p>
        </header>
        <div class="section-content">
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/dashboard#css">CSS Rules</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/dashboard#background">Background Manager</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/?section=room-map-editor">Room Map Editor</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/room_main#area-mapper">Area-Item Mapper</a>
        </div>
      </section>

      <!-- Business & Analytics -->
      <section class="settings-section business-section card-theme-emerald">
        <header class="section-header">
          <h3 class="section-title">Business &amp; Analytics</h3>
          <p class="section-description">Manage sales, promotions, and business insights</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-business-info">Business Information</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-square-settings">Configure Square</button>
        </div>
      </section>

      <!-- Communication -->
      <section class="settings-section communication-section card-theme-orange">
        <header class="section-header">
          <h3 class="section-title">Communication</h3>
          <p class="section-description">Email configuration and customer messaging</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-settings">Email Configuration</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-history">Email History</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-test">Send Sample Email</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-logging-status">Logging Status</button>
          <a class="admin-settings-button btn-primary btn-full-width" href="/receipt.php">Receipt Messages</a>
        </div>
      </section>

      <!-- Technical & System -->
      <section class="settings-section technical-section card-theme-red">
        <header class="section-header">
          <h3 class="section-title">Technical &amp; System</h3>
          <p class="section-description">System tools and advanced configuration</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-account-settings">Account Settings</button>
          <a class="admin-settings-button btn-secondary btn-full-width" href="/admin/account_settings">Open Account Settings Page (fallback)</a>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-secrets-modal">Secrets Manager</button>
          <a class="admin-settings-button btn-secondary btn-full-width" href="/admin/secrets">Open Secrets Page (fallback)</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/cost_breakdown_manager">Cost Breakdown Manager</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/customers">User Manager</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/reports_browser.php">Reports &amp; Documentation Browser</a>
        </div>
      </section>

      <!-- AI & Automation -->
      <section class="settings-section ai-automation-section card-theme-teal">
        <header class="section-header">
          <h3 class="section-title">AI &amp; Automation</h3>
          <p class="section-description">Artificial intelligence and automation settings</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width ai-settings-btn" data-action="open-ai-settings">AI Provider</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width ai-tools-btn" data-action="open-ai-tools">AI &amp; Automation Tools</button>
        </div>
      </section>
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
              <button id="saveSquareSettingsBtn" type="button" class="btn-primary" data-action="square-save-settings">Save Settings</button>
              <button type="button" class="btn-secondary" data-action="square-test-connection">Test Connection</button>
              <button type="button" class="btn-secondary" data-action="square-sync-items">Sync Items</button>
              <button type="button" class="btn-danger" data-action="square-clear-token">Clear Token</button>
            </div>

            <div id="connectionResult" class="text-sm text-gray-600"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Categories Modal (hidden by default) -->
    <div id="categoriesModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="categoriesTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="categoriesTitle" class="admin-card-title">📂 Categories</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="space-y-4">
            <div class="flex items-center gap-2">
              <input id="catNewName" type="text" class="form-input" placeholder="New category name" />
              <button type="button" class="btn-primary" data-action="cat-add">Add</button>
            </div>
            <div id="catResult" class="text-sm text-gray-600"></div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Items</th>
                    <th class="p-2 text-left">Actions</th>
                  </tr>
                </thead>
                <tbody id="catTableBody"></tbody>
              </table>
            </div>
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
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="gender" onsubmit="return false;">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add gender (e.g., Unisex)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="gender">Add</button>
              </form>
              <ul id="attrListGender" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Size</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="size" onsubmit="return false;">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add size (e.g., XL)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="size">Add</button>
              </form>
              <ul id="attrListSize" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Color</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="color" onsubmit="return false;">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add color (e.g., Royal Blue)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="color">Add</button>
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

    <!-- ... -->

    <!-- Inline Settings failsafe script removed; Vite bridge handles all wiring. -->
    <!-- ... -->
                  e.preventDefault(); e.stopPropagation();
                  top.classList.add('hidden'); top.classList.remove('show'); top.setAttribute('aria-hidden','true');
                  log('esc-close', top.id || '(no id)');
                }
              }
            } catch(_) {}
          }, true);

          // Minimal open/close helpers to ensure Settings buttons work even without bundles
          const __settingsShowModal = (id) => {
            try {
              const el = document.getElementById(id);
              if (!el) return false;
              // Reparent overlay to <body> to avoid stacking/overflow contexts
              try { if (el.parentNode && el.parentNode !== document.body) document.body.appendChild(el); } catch(_) {}
              // Enforce fixed, full-viewport overlay with strong stacking context
              try {
                el.style.position = 'fixed';
                el.style.left = '0';
                el.style.top = '0';
                el.style.right = '0';
                el.style.bottom = '0';
                el.style.width = '100%';
                el.style.height = '100%';
                el.style.zIndex = '99999';
              } catch(_) {}
              // Offset overlay below header so dialog never appears under it
              try {
                const header = document.querySelector('.site-header') || document.querySelector('.universal-page-header');
                const hh = header && header.getBoundingClientRect ? Math.max(40, Math.round(header.getBoundingClientRect().height)) : 64;
                el.style.paddingTop = (hh + 12) + 'px';
                el.style.alignItems = 'flex-start';
              } catch(_) {}
              try { el.removeAttribute('hidden'); } catch(_) {}
              try { el.classList.remove('hidden'); } catch(_) {}
              try { el.classList.add('show'); } catch(_) {}
              try { el.setAttribute('aria-hidden','false'); } catch(_) {}
              try { el.style.display = 'flex'; } catch(_) {}
              try { console.info('[SettingsFailsafe] showModal', id); } catch(_) {}
              return true;
            } catch(_) { return false; }
          };
          <!-- SettingsFailsafe script removed: all modal logic handled by Vite bridge. -->
                  </div>
                </div>
              </div>
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
            <p class="text-sm text-gray-700">Manage which sections appear on your Dashboard. Add/remove from the lists below, then click Save.</p>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <h3 class="text-base font-semibold mb-2">Active Sections</h3>
                <ul id="dashboardActiveSections" class="list-disc pl-5 text-sm text-gray-800"></ul>
              </div>
              <div>
                <h3 class="text-base font-semibold mb-2">Available Sections</h3>
                <ul id="dashboardAvailableSections" class="list-disc pl-5 text-sm text-gray-800"></ul>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <div id="dashboardConfigResult" class="text-sm text-gray-500"></div>
              <div class="flex items-center gap-2">
                <button type="button" class="btn" data-action="dashboard-config-reset">Reset to defaults</button>
                <button type="button" class="btn-secondary" data-action="dashboard-config-refresh">Refresh</button>
                <button type="button" class="btn-primary" data-action="dashboard-config-save">Save</button>
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
            <p class="text-sm text-gray-700">Paste JSON or key=value lines to update secrets. Sensitive values are never prefilled.</p>
            <textarea id="secretsPayload" name="secrets_payload" class="form-textarea w-full" rows="8" placeholder='{"SMTP_PASS":"..."}'></textarea>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-primary" data-action="secrets-save">Save Secrets</button>
              <button type="button" class="btn-secondary" data-action="secrets-rotate">Rotate Keys</button>
              <button type="button" class="btn-secondary" data-action="secrets-export">Export Secrets</button>
            </div>
            <div id="secretsResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
?>
<!-- WF: SETTINGS WRAPPER END -->
