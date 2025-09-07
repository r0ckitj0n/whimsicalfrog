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

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="cssBrandPrimary" class="block text-sm font-medium mb-1">Brand Primary</label>
                <input id="cssBrandPrimary" name="brand_primary" type="color" class="form-input w-full" value="#87ac3a" />
                <p class="text-xs text-gray-500">Maps to <code>--brand-primary</code></p>
              </div>
              <div>
                <label for="cssBrandSecondary" class="block text-sm font-medium mb-1">Brand Secondary</label>
                <input id="cssBrandSecondary" name="brand_secondary" type="color" class="form-input w-full" value="#6b8e23" />
                <p class="text-xs text-gray-500">Maps to <code>--brand-secondary</code></p>
              </div>
            </div>

            <!-- Links -->
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="cssLinkColor" class="block text-sm font-medium mb-1">Link Color</label>
                <input id="cssLinkColor" name="link_color" type="color" class="form-input w-full" value="#87ac3a" />
                <p class="text-xs text-gray-500">Maps to <code>--link-color</code></p>
              </div>
              <div>
                <label for="cssLinkHoverColor" class="block text-sm font-medium mb-1">Link Hover Color</label>
                <input id="cssLinkHoverColor" name="link_hover_color" type="color" class="form-input w-full" value="#BF5700" />
                <p class="text-xs text-gray-500">Maps to <code>--link-hover-color</code></p>
              </div>
            </div>

            <!-- Text -->
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="cssTextColor" class="block text-sm font-medium mb-1">Body Text</label>
                <input id="cssTextColor" name="text_color" type="color" class="form-input w-full" value="#111827" />
                <p class="text-xs text-gray-500">Maps to <code>--text-color</code></p>
              </div>
              <div>
                <label for="cssHeadingColor" class="block text-sm font-medium mb-1">Heading Text</label>
                <input id="cssHeadingColor" name="heading_color" type="color" class="form-input w-full" value="#0f172a" />
                <p class="text-xs text-gray-500">Maps to <code>--heading-color</code></p>
              </div>
              <div>
                <label for="cssTextMuted" class="block text-sm font-medium mb-1">Muted Text</label>
                <input id="cssTextMuted" name="text_color_muted" type="color" class="form-input w-full" value="#6b7280" />
                <p class="text-xs text-gray-500">Maps to <code>--text-color-muted</code></p>
              </div>
            </div>

            <!-- Overlay & Modal -->
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="cssOverlayBg" class="block text-sm font-medium mb-1">Overlay Background</label>
                <input id="cssOverlayBg" name="overlay_bg" type="text" class="form-input w-full" placeholder="rgba(0,0,0,0.5)" />
                <p class="text-xs text-gray-500">Supports rgba or hex. Maps to <code>--overlay-bg</code></p>
              </div>
              <div>
                <label for="cssModalBg" class="block text-sm font-medium mb-1">Modal Background</label>
                <input id="cssModalBg" name="modal_bg" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--modal-bg</code></p>
              </div>
              <div>
                <label for="cssModalBorder" class="block text-sm font-medium mb-1">Modal Border</label>
                <input id="cssModalBorder" name="modal_border" type="text" class="form-input w-full" placeholder="rgba(0,0,0,0.08)" />
                <p class="text-xs text-gray-500">Supports rgba or hex. Maps to <code>--modal-border</code></p>
              </div>
            </div>

            <!-- Inputs -->
            <div class="grid gap-4 md:grid-cols-4">
              <div>
                <label for="cssInputBg" class="block text-sm font-medium mb-1">Input Background</label>
                <input id="cssInputBg" name="input_bg" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--input-bg</code></p>
              </div>
              <div>
                <label for="cssInputText" class="block text-sm font-medium mb-1">Input Text</label>
                <input id="cssInputText" name="input_text" type="color" class="form-input w-full" value="#111827" />
                <p class="text-xs text-gray-500">Maps to <code>--input-text</code></p>
              </div>
              <div>
                <label for="cssInputBorder" class="block text-sm font-medium mb-1">Input Border</label>
                <input id="cssInputBorder" name="input_border" type="color" class="form-input w-full" value="#d1d5db" />
                <p class="text-xs text-gray-500">Maps to <code>--input-border</code></p>
              </div>
              <div>
                <label for="cssInputPlaceholder" class="block text-sm font-medium mb-1">Input Placeholder</label>
                <input id="cssInputPlaceholder" name="input_placeholder" type="color" class="form-input w-full" value="#9ca3af" />
                <p class="text-xs text-gray-500">Maps to <code>--input-placeholder</code></p>
              </div>
            </div>

            <!-- Secondary Buttons -->
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="cssBtnSecondaryBg" class="block text-sm font-medium mb-1">Secondary Button BG</label>
                <input id="cssBtnSecondaryBg" name="button_bg_secondary" type="color" class="form-input w-full" value="#374151" />
                <p class="text-xs text-gray-500">Maps to <code>--button-bg-secondary</code></p>
              </div>
              <div>
                <label for="cssBtnSecondaryText" class="block text-sm font-medium mb-1">Secondary Button Text</label>
                <input id="cssBtnSecondaryText" name="button_text_secondary" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--button-text-secondary</code></p>
              </div>
              <div>
                <label for="cssBtnSecondaryHover" class="block text-sm font-medium mb-1">Secondary Button Hover BG</label>
                <input id="cssBtnSecondaryHover" name="button_bg_secondary_hover" type="color" class="form-input w-full" value="#111827" />
                <p class="text-xs text-gray-500">Maps to <code>--button-bg-secondary-hover</code></p>
              </div>
            </div>

            <!-- Primary Buttons & Brand Accent -->
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="cssBtnPrimaryBg" class="block text-sm font-medium mb-1">Primary Button BG</label>
                <input id="cssBtnPrimaryBg" name="button_bg_primary" type="color" class="form-input w-full" value="#87ac3a" />
                <p class="text-xs text-gray-500">Maps to <code>--button-bg-primary</code> (defaults to <code>--brand-primary</code>)</p>
              </div>
              <div>
                <label for="cssBtnPrimaryText" class="block text-sm font-medium mb-1">Primary Button Text</label>
                <input id="cssBtnPrimaryText" name="button_text_primary" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--button-text-primary</code></p>
              </div>
              <div>
                <label for="cssBrandRed" class="block text-sm font-medium mb-1">Brand Red</label>
                <input id="cssBrandRed" name="brand_red" type="color" class="form-input w-full" value="#b91c1c" />
                <p class="text-xs text-gray-500">Maps to <code>--brand-red</code> (used for alerts/errors)</p>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-primary" data-action="css-rules-save">Save</button>
              <button type="button" class="btn-secondary" data-action="close-css-rules">Close</button>
            </div>
            <div id="cssRulesResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- Business Information Modal (hidden by default) -->
    <div id="businessInfoModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="businessInfoTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="businessInfoTitle" class="admin-card-title">🏷️ Business Information</h2>
          <button type="button" class="admin-modal-close" data-action="close-business-info" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="businessInfoForm" data-action="prevent-submit" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizName" class="block text-sm font-medium mb-1">Business Name</label>
                <input id="bizName" name="business_name" type="text" class="form-input w-full" placeholder="Whimsical Frog" />
              </div>
              <div>
                <label for="bizLegalName" class="block text-sm font-medium mb-1">Legal Name</label>
                <input id="bizLegalName" name="legal_name" type="text" class="form-input w-full" placeholder="Whimsical Frog LLC" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizOwner" class="block text-sm font-medium mb-1">Owner</label>
                <input id="bizOwner" name="owner" type="text" class="form-input w-full" placeholder="Calvin &amp; Lisa Lemley" />
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizPhone" class="block text-sm font-medium mb-1">Phone</label>
                <input id="bizPhone" name="phone" type="text" class="form-input w-full" placeholder="(555) 555-5555" />
              </div>
              <div>
                <label for="bizEmail" class="block text-sm font-medium mb-1">Email</label>
                <input id="bizEmail" name="email" type="email" class="form-input w-full" placeholder="info@domain.com" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Address</label>
              <input id="bizAddr1" name="address1" type="text" class="form-input w-full mb-2" placeholder="Address line 1" />
              <input id="bizAddr2" name="address2" type="text" class="form-input w-full mb-2" placeholder="Address line 2 (optional)" />
              <div class="grid gap-4 md:grid-cols-3">
                <input id="bizCity" name="city" type="text" class="form-input w-full" placeholder="City" />
                <input id="bizState" name="state" type="text" class="form-input w-full" placeholder="State" />
                <input id="bizZip" name="zip" type="text" class="form-input w-full" placeholder="ZIP" />
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizWebsite" class="block text-sm font-medium mb-1">Website</label>
                <input id="bizWebsite" name="website" type="url" class="form-input w-full" placeholder="https://whimsicalfrog.com" />
              </div>
              <div>
                <label for="bizTaxRate" class="block text-sm font-medium mb-1">Default Tax Rate (%)</label>
                <input id="bizTaxRate" name="tax_rate" type="number" step="0.01" min="0" class="form-input w-full" placeholder="7.00" />
              </div>
            </div>

            <div>
              <label for="bizHours" class="block text-sm font-medium mb-1">Hours</label>
              <textarea id="bizHours" name="hours" class="form-textarea w-full" rows="3" placeholder="Mon–Fri 9am–5pm"></textarea>
            </div>

            <div>
              <label for="bizReturnPolicy" class="block text-sm font-medium mb-1">Return Policy</label>
              <textarea id="bizReturnPolicy" name="return_policy" class="form-textarea w-full" rows="4" placeholder="Describe your return policy..."></textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="bizFacebook" class="block text-sm font-medium mb-1">Facebook</label>
                <input id="bizFacebook" name="facebook" type="url" class="form-input w-full" placeholder="https://facebook.com/yourpage" />
              </div>
              <div>
                <label for="bizInstagram" class="block text-sm font-medium mb-1">Instagram</label>
                <input id="bizInstagram" name="instagram" type="url" class="form-input w-full" placeholder="https://instagram.com/yourhandle" />
              </div>
              <div>
                <label for="bizTiktok" class="block text-sm font-medium mb-1">TikTok</label>
                <input id="bizTiktok" name="tiktok" type="url" class="form-input w-full" placeholder="https://tiktok.com/@yourhandle" />
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-primary" data-action="business-info-save">Save</button>
              <button type="button" class="btn-secondary" data-action="close-business-info">Close</button>
            </div>
            <div id="businessInfoResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- AI Provider Modal (hidden by default) -->
    <div id="aiSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="aiSettingsTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="aiSettingsTitle" class="admin-card-title">🤖 AI Provider</h2>
          <button type="button" class="admin-modal-close" data-action="close-ai-settings" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="aiProviderForm" data-action="prevent-submit" class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-1">Provider</label>
              <select id="aiProvider" name="ai_provider" class="form-select w-full">
                <option value="openai">OpenAI</option>
                <option value="anthropic">Anthropic</option>
                <option value="google">Google</option>
                <option value="meta">Meta</option>
              </select>
            </div>
            <div>
              <label for="aiApiKey" class="block text-sm font-medium mb-1">API Key</label>
              <input id="aiApiKey" name="api_key" type="password" class="form-input w-full" placeholder="Paste API key" />
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="aiModel" class="block text-sm font-medium mb-1">Model</label>
                <input id="aiModel" name="model" type="text" class="form-input w-full" placeholder="e.g., gpt-4o, claude-3-opus" />
              </div>
              <div>
                <label for="aiTemperature" class="block text-sm font-medium mb-1">Temperature</label>
                <input id="aiTemperature" name="temperature" type="number" min="0" max="2" step="0.1" value="0.7" class="form-input w-full" />
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <button id="saveAISettingsBtn" type="button" class="btn-primary" data-action="save-ai-settings">Save</button>
              <button type="button" class="btn-secondary" data-action="test-ai-settings">Test</button>
            </div>
            <div id="aiSettingsResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- AI & Automation Tools Modal (hidden by default) -->
    <div id="aiToolsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="aiToolsTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="aiToolsTitle" class="admin-card-title">🛠️ AI & Automation Tools</h2>
          <button type="button" class="admin-modal-close" data-action="close-ai-tools" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div class="space-y-3">
            <button type="button" class="btn-primary btn-full-width" data-action="ai-run-diagnostics">Run AI Diagnostics</button>
            <button type="button" class="btn-secondary btn-full-width" data-action="ai-clear-cache">Clear AI Cache</button>
            <button type="button" class="btn-secondary btn-full-width" data-action="ai-refresh-providers">Refresh Providers</button>
          </div>
          <div id="aiToolsResult" class="mt-3 status status--info"></div>
        </div>
      </div>
    </div>

    <!-- Email Settings Modal (hidden by default) -->
    <div id="emailSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="emailSettingsTitle">
      <div class="admin-modal">
        <div class="modal-header">
          <h2 id="emailSettingsTitle" class="admin-card-title">✉️ Email Configuration</h2>
          <button type="button" class="admin-modal-close" data-action="close-email-settings" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="emailSettingsForm" data-action="prevent-submit" class="space-y-4">
            <!-- Basic From and Admin Addresses -->
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="fromName" class="block text-sm font-medium mb-1">From Name</label>
                <input id="fromName" name="from_name" type="text" class="form-input w-full" placeholder="Whimsical Frog" />
              </div>
              <div>
                <label for="fromEmail" class="block text-sm font-medium mb-1">From Address</label>
                <input id="fromEmail" name="from_email" type="email" class="form-input w-full" placeholder="no-reply@whimsicalfrog.com" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <div>
                <label for="adminEmail" class="block text-sm font-medium mb-1">Admin Email</label>
                <input id="adminEmail" name="admin_email" type="email" class="form-input w-full" placeholder="owner@whimsicalfrog.com" />
              </div>
              <div>
                <label for="bccEmail" class="block text-sm font-medium mb-1">BCC Email</label>
                <input id="bccEmail" name="bcc_email" type="email" class="form-input w-full" placeholder="archive@whimsicalfrog.com" />
              </div>
              <div>
                <label for="replyToEmail" class="block text-sm font-medium mb-1">Reply-To</label>
                <input id="replyToEmail" name="reply_to" type="email" class="form-input w-full" placeholder="support@whimsicalfrog.com" />
              </div>
            </div>

            <!-- SMTP Toggle -->
            <div class="flex items-center gap-2">
              <input id="smtpEnabled" type="checkbox" class="form-checkbox" />
              <label for="smtpEnabled" class="text-sm">Use SMTP</label>
            </div>

            <!-- SMTP Settings -->
            <div id="smtpSettings" class="space-y-4">
              <div>
                <label for="smtpHost" class="block text-sm font-medium mb-1">SMTP Host</label>
                <input id="smtpHost" name="smtp_host" type="text" class="form-input w-full" placeholder="smtp.mailprovider.com" />
              </div>
              <div class="grid gap-4 md:grid-cols-4">
                <div>
                  <label for="smtpPort" class="block text-sm font-medium mb-1">Port</label>
                  <input id="smtpPort" name="smtp_port" type="number" class="form-input w-full" placeholder="587" />
                </div>
                <div>
                  <label for="smtpEncryption" class="block text-sm font-medium mb-1">Encryption</label>
                  <select id="smtpEncryption" name="smtp_encryption" class="form-select w-full">
                    <option value="">None</option>
                    <option value="ssl">SSL</option>
                    <option value="tls">TLS</option>
                  </select>
                </div>
                <div>
                  <label for="smtpTimeout" class="block text-sm font-medium mb-1">Timeout (sec)</label>
                  <input id="smtpTimeout" name="smtp_timeout" type="number" class="form-input w-full" placeholder="30" />
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Authentication</label>
                  <div class="flex items-center gap-2">
                    <input id="smtpAuth" type="checkbox" class="form-checkbox" />
                    <label for="smtpAuth" class="text-sm">Require Auth</label>
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
