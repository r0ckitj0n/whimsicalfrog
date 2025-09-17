<?php
// Admin Settings (JS-powered). Renders the wrapper the module expects and seeds minimal context.
// Guard auth if helper exists
if (function_exists('isLoggedIn') && !isLoggedIn()) {
    echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login to access settings</h1></div>';
    return;
}
// CSRF token for Secrets actions
try {
    require_once dirname(__DIR__) . '/includes/csrf.php';
    $__secrets_csrf = csrf_token('admin_secrets');
} catch (Throwable $____) { $__secrets_csrf = ''; }

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
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-settings">Room Settings</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-category-links">Room-Category Links</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-template-manager">Template Manager</button>
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

      <!-- Health & Diagnostics -->
      <section class="settings-section health-section card-theme-slate">
        <header class="section-header">
          <h3 class="section-title">Health &amp; Diagnostics</h3>
          <p class="section-description">Check for missing backgrounds and item images</p>
        </header>
        <div class="section-content">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-health-diagnostics">Open Health &amp; Diagnostics</button>
        </div>
      </section>

      <!-- Help & Hints -->
      <section class="settings-section help-hints-section card-theme-amber">
        <header class="section-header">
          <h3 class="section-title">Help &amp; Hints</h3>
          <p class="section-description">Control admin tooltips and contextual banners</p>
        </header>
        <div class="section-content grid gap-2">
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="hints-enable-session">Enable tooltips (this session)</button>
          <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="hints-enable-persist">Enable tooltips (always)</button>
          <button type="button" class="admin-settings-button btn-secondary btn-full-width" data-action="hints-disable">Disable tooltips</button>
          <div class="text-xs text-gray-600 mt-1">Tooltips appear after a short hover. You can toggle them per session or persistently.</div>
          <div class="border-t my-2"></div>
          <button type="button" class="admin-settings-button btn-secondary btn-full-width" data-action="hints-restore-banners-session">Restore dismissed banners (this session)</button>
          <button type="button" class="admin-settings-button btn-secondary btn-full-width" data-action="hints-restore-banners-persist">Restore dismissed banners (always)</button>
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
              <a class="btn" href="/admin/dashboard#background">Background Manager</a>
              <a class="btn" href="/admin/inventory">Inventory</a>
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
        </div>
      </div>
    </div>

    <!-- Room Settings Modal (iframe embed) -->
    <div id="roomSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="roomSettingsTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="roomSettingsTitle" class="admin-card-title">🚪 Room Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="roomSettingsFrame" title="Room Settings" src="about:blank" data-src="/admin/?section=room-config-manager" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- Room-Category Links Modal (iframe embed) -->
    <div id="roomCategoryLinksModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="roomCategoryLinksTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="roomCategoryLinksTitle" class="admin-card-title">🔗 Room-Category Links</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="roomCategoryLinksFrame" title="Room-Category Links" src="about:blank" data-src="/admin/?section=categories" class="wf-admin-embed-frame"></iframe>
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
          <iframe id="templateManagerFrame" title="Template Manager" src="about:blank" data-src="/admin/?section=reports" class="wf-admin-embed-frame"></iframe>
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
                <input id="bizName" type="text" class="form-input w-full" placeholder="Whimsical Frog" />
              </div>
              <div>
                <label for="bizEmail" class="block text-sm font-medium mb-1">Business Email</label>
                <input id="bizEmail" type="email" class="form-input w-full" placeholder="info@yourdomain.com" />
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
                <button type="button" class="btn-secondary" data-action="business-reset-branding">Reset Branding</button>
              </div>
            </div>

            <div class="border-t pt-4 mt-4">
              <h3 class="text-sm font-semibold mb-2">Brand Fonts</h3>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label for="brandFontPrimary" class="block text-sm font-medium mb-1">Primary Font</label>
                  <input id="brandFontPrimary" type="text" class="form-input w-full" placeholder="e.g., Inter, system-ui" />
                </div>
                <div>
                  <label for="brandFontSecondary" class="block text-sm font-medium mb-1">Secondary Font</label>
                  <input id="brandFontSecondary" type="text" class="form-input w-full" placeholder="e.g., Merriweather" />
                </div>
              </div>
            </div>

            <div class="border-t pt-4 mt-4">
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold">Custom CSS Variables</h3>
                <button type="button" class="text-xs text-blue-600 hover:underline" data-action="open-brand-vars-help" aria-controls="brandVarsHelp" aria-expanded="false">ⓘ What can I set?</button>
              </div>
              <p class="text-xs text-gray-600 mb-2">Define variables without the leading ":root{". One per line, e.g. <code>--brand-radius: 8px;</code></p>
              <textarea id="customCssVars" class="form-textarea w-full" rows="4" placeholder="--brand-radius: 8px;\n--card-shadow: 0 2px 8px rgba(0,0,0,.08);"></textarea>
              <div id="brandVarsHelp" class="hidden mt-2 border rounded p-3 bg-gray-50 text-xs" role="note">
                <div class="font-semibold mb-1">Examples</div>
                <ul class="list-disc ml-5 space-y-1">
                  <li><code>--button-radius: 8px;</code> — used for rounded buttons</li>
                  <li><code>--card-shadow: 0 2px 8px rgba(0,0,0,.08);</code> — soft card shadow</li>
                  <li><code>--link-underline-offset: 2px;</code> — adjust underline gap</li>
                </ul>
                <div class="mt-2">These are applied as <code>:root</code> variables at runtime and can be referenced in your CSS (e.g., <code>border-radius: var(--button-radius)</code>).</div>
                <div class="mt-2 text-right">
                  <button type="button" class="text-blue-600 hover:underline" data-action="close-brand-vars-help">Close</button>
                </div>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizWebsite" class="block text-sm font-medium mb-1">Website</label>
                <input id="bizWebsite" type="url" class="form-input w-full" placeholder="https://yourdomain.com" />
              </div>
              <div>
                <label for="bizLogoUrl" class="block text-sm font-medium mb-1">Logo URL</label>
                <input id="bizLogoUrl" type="url" class="form-input w-full" placeholder="https://.../logo.png" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizTagline" class="block text-sm font-medium mb-1">Tagline</label>
                <input id="bizTagline" type="text" class="form-input w-full" placeholder="Great deals, great vibes" />
              </div>
              <div>
                <label for="bizDescription" class="block text-sm font-medium mb-1">Short Description</label>
                <input id="bizDescription" type="text" class="form-input w-full" placeholder="What customers should know" />
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="bizPhone" class="block text-sm font-medium mb-1">Phone</label>
                <input id="bizPhone" type="text" class="form-input w-full" placeholder="(555) 555-5555" />
              </div>
              <div>
                <label for="bizHours" class="block text-sm font-medium mb-1">Hours</label>
                <input id="bizHours" type="text" class="form-input w-full" placeholder="Mon-Fri 9am-5pm" />
              </div>
            </div>
            <div>
              <label for="bizAddress" class="block text-sm font-medium mb-1">Address</label>
              <textarea id="bizAddress" class="form-textarea w-full" rows="3" placeholder="123 Main St, City, ST 00000"></textarea>
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
              <h3 class="text-sm font-semibold mb-2">Branding</h3>
              <div class="grid gap-4 md:grid-cols-5">
                <div>
                  <label for="brandPrimary" class="block text-sm font-medium mb-1">Primary Color</label>
                  <input id="brandPrimary" type="color" class="form-input w-full" value="#0ea5e9" />
                </div>
                <div>
                  <label for="brandSecondary" class="block text-sm font-medium mb-1">Secondary Color</label>
                  <input id="brandSecondary" type="color" class="form-input w-full" value="#6366f1" />
                </div>
                <div>
                  <label for="brandAccent" class="block text-sm font-medium mb-1">Accent Color</label>
                  <input id="brandAccent" type="color" class="form-input w-full" value="#22c55e" />
                </div>
                <div>
                  <label for="brandBackground" class="block text-sm font-medium mb-1">Background Color</label>
                  <input id="brandBackground" type="color" class="form-input w-full" value="#ffffff" />
                </div>
                <div>
                  <label for="brandText" class="block text-sm font-medium mb-1">Text Color</label>
                  <input id="brandText" type="color" class="form-input w-full" value="#111827" />
                </div>
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
              <button type="button" class="btn-primary" data-action="business-save">Save</button>
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

    <!-- Account Settings Modal (iframe embed) -->
    <div id="accountSettingsModal" class="admin-modal-overlay hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="accountSettingsTitle">
      <div class="admin-modal admin-modal-content">
        <div class="modal-header">
          <h2 id="accountSettingsTitle" class="admin-card-title">👤 Account Settings</h2>
          <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="accountSettingsFrame" title="Account Settings" src="about:blank" data-src="/admin/account_settings" class="wf-admin-embed-frame"></iframe>
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
          <iframe id="aiSettingsFrame" title="AI Provider" src="about:blank" data-src="/admin/?section=marketing" class="wf-admin-embed-frame"></iframe>
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
          <iframe id="aiToolsFrame" title="AI &amp; Automation Tools" src="about:blank" data-src="/admin/?section=marketing" class="wf-admin-embed-frame"></iframe>
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
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="gender">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add gender (e.g., Unisex)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="gender">Add</button>
              </form>
              <ul id="attrListGender" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Size</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="size">
                <input type="text" class="attr-input text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="Add size (e.g., XL)" maxlength="64">
                <button type="submit" class="btn btn-primary" data-action="attr-add" data-type="size">Add</button>
              </form>
              <ul id="attrListSize" class="attr-list space-y-1"></ul>
            </div>
            <div class="attr-col">
              <h3 class="text-base font-semibold mb-2">Color</h3>
              <form class="flex gap-2 mb-2" data-action="attr-add-form" data-type="color">
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
            <input type="hidden" id="secretsCsrf" value="<?= htmlspecialchars((string)($__secrets_csrf ?? '')) ?>">
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
