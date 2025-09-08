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
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/inventory#categories">Categories</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/inventory#attributes">Gender, Size &amp; Color Management</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/room_main">Room Settings</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/inventory#room-category-links">Room-Category Links</a>
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/inventory#templates">Template Manager</a>
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
          <a class="admin-settings-button btn-primary btn-full-width" href="/admin/room_main#mapper">Room Mapper</a>
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

    <script>
      (function(){
        try {
          if (!document || !document.addEventListener) return;
          const log = (...args) => { try { console.info('[SettingsFailsafe]', ...args); } catch(_) {} };

          const ensureStatus = (modalEl, text) => {
            try {
              const header = modalEl && modalEl.querySelector('.modal-header');
              if (!header) return;
              let chip = header.querySelector('.modal-status-chip');
              if (!chip) {
                chip = document.createElement('span');
                chip.className = 'modal-status-chip';
                chip.style.cssText = 'margin-left:8px;font-size:12px;color:#059669;';
                chip.setAttribute('aria-live','polite');
                header.appendChild(chip);
              }
              if (text) chip.textContent = text;
            } catch(_) {}
          };

          const lazyFrame = (frameId, modalId) => {
            if (!frameId) return;
            try {
              const f = document.getElementById(frameId);
              if (f && f.getAttribute('src') === 'about:blank') {
                const ds = f.getAttribute('data-src');
                if (ds) {
                  // Focus on load for accessibility and set status
                  f.addEventListener('load', () => {
                    try {
                      const modalEl = modalId ? document.getElementById(modalId) : f.closest('.admin-modal-overlay');
                      ensureStatus(modalEl, 'Loaded');
                      // Best-effort focus into iframe content
                      setTimeout(() => {
                        try { f.contentWindow && f.contentWindow.focus && f.contentWindow.focus(); } catch(_) {}
                        try { f.focus(); } catch(_) {}
                      }, 0);
                    } catch(_) {}
                  }, { once: true });
                  f.setAttribute('src', ds);
                }
              }
            } catch(_) {}
          };

          const tryOpen = (modalId, frameId) => {
            if (showModal(modalId)) {
              const modalEl = document.getElementById(modalId);
              if (frameId) {
                ensureStatus(modalEl, 'Loading…');
                lazyFrame(frameId, modalId);
              } else {
                ensureStatus(modalEl, 'Loaded');
              }
              log('delegated-open', modalId);
              return true;
            }
            return false;
          };

          document.addEventListener('click', (e) => {
            const t = e.target;
            if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
              const id = t.id;
              if (id) {
                try { e.preventDefault(); e.stopPropagation(); } catch(_) {}
                t.classList.add('hidden'); t.classList.remove('show'); t.setAttribute('aria-hidden','true'); log('overlay-close', id);
              }
            }
          }, true);

          document.addEventListener('keydown', (e) => {
            try {
              if (e.key === 'Escape' || e.key === 'Esc') {
                const openOverlays = Array.from(document.querySelectorAll('.admin-modal-overlay.show'));
                if (openOverlays.length) {
                  const top = openOverlays[openOverlays.length - 1];
                  e.preventDefault(); e.stopPropagation();
                  top.classList.add('hidden'); top.classList.remove('show'); top.setAttribute('aria-hidden','true');
                  log('esc-close', top.id || '(no id)');
                }
              }
            } catch(_) {}
          }, true);
        } catch(_) {}
      })();
    </script>
                  </div>
                </div>
              </div>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="smtpUsername" class="block text-sm font-medium mb-1">Username</label>
                  <input id="smtpUsername" name="smtp_username" type="text" class="form-input w-full" placeholder="username" autocomplete="username" />
                </div>
                <div>
                  <label for="smtpPassword" class="block text-sm font-medium mb-1">Password</label>
                  <input id="smtpPassword" name="smtp_password" type="password" class="form-input w-full" placeholder="••••••••" autocomplete="new-password" />
                  <p class="text-xs text-gray-500 mt-1">Password is never prefetched for security. Saving will store it server-side.</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <input id="smtpDebug" type="checkbox" class="form-checkbox" />
                <label for="smtpDebug" class="text-sm">Enable SMTP Debug</label>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-primary" data-action="email-save-settings">Save</button>
              <div class="flex items-center gap-2">
                <input id="testRecipient" type="email" class="form-input" placeholder="test@whimsicalfrog.com" />
                <button type="button" class="btn-secondary" data-action="email-send-test">Send Test Email</button>
              </div>
            </div>
            <div id="emailSettingsResult" class="status status--info"></div>
          </form>
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
