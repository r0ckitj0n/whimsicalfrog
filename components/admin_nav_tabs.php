<?php
// components/admin_nav_tabs.php
// Renders the Admin navigation tabs consistently across all admin pages.
// Expects $section to be set in the including scope (current active section key)

if (!isset($section)) {
    $section = $_GET['section'] ?? '';
}

$tabs = [
    '' => ['Dashboard', 'admin-tab-dashboard'],
    'customers' => ['Customers', 'admin-tab-customers'],
    'inventory' => ['Inventory', 'admin-tab-inventory'],
    'orders' => ['Orders', 'admin-tab-orders'],
    'pos' => ['POS', 'admin-tab-pos'],
    'reports' => ['Reports', 'admin-tab-reports'],
    'marketing' => ['Marketing', 'admin-tab-marketing'],
    'settings' => ['Settings', 'admin-tab-settings'],
];

$tooltipIds = [
    '' => 'adminDashboardTab',
    'customers' => 'adminCustomersTab',
    'inventory' => 'adminInventoryTab',
    'orders' => 'adminOrdersTab',
    'pos' => 'adminPosTab',
    'reports' => 'adminReportsTab',
    'marketing' => 'adminMarketingTab',
    'settings' => 'adminSettingsTab'
];
?>
<div class="admin-tab-navigation">
    <div class="wf-admin-nav-row">
        <div class="wf-nav-left"></div>

        <!-- Centered tabs -->
        <div class="wf-nav-center">
        <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
            <?php $tooltipId = $tooltipIds[$key] ?? ''; ?>
            <?php
                // Route all tabs through the canonical admin router so header/navbar is always rendered.
                $href = ($key === '') ? '/admin' : ('/admin?section=' . urlencode($key));
            ?>
            <a href="<?= $href ?>"
               id="<?= $tooltipId ?>"
               class="admin-nav-tab <?= $cssClass ?> <?= ($section === $key || ($key === '' && !$section)) ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
        </div>

        <!-- Right-aligned help bubble: separate buttons for ? and toggle -->
        <div class="wf-nav-right">
            <div class="admin-help-container">
                <button type="button"
                        id="adminHelpDocsBtn"
                        class="admin-nav-tab admin-tab-help admin-help-docs"
                        data-action="open-admin-help-modal"
                        aria-label="Open Help Documentation">
                    <span class="help-q" aria-hidden="true">?</span>
                </button>
                <button type="button"
                        id="adminHelpToggleBtn"
                        class="admin-nav-tab admin-tab-help admin-help-toggle"
                        data-action="help-toggle-global-tooltips"
                        aria-pressed="false"
                        aria-label="Toggle Help Tooltips">
                    <span class="wf-toggle" aria-hidden="true"><span class="wf-knob"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Help Documentation Modal -->
<div id="adminHelpDocsModal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="adminHelpDocsTitle">
    <div class="admin-modal w-full max-w-5xl flex flex-col" role="document">
        <div class="modal-body">
            <iframe id="adminHelpDocsFrame" title="Admin Help Documentation" src="about:blank" data-src="/help.php" class="wf-admin-embed-frame"></iframe>
        </div>
        <div class="flex items-center justify-end gap-2 p-3 border-t" style="padding-bottom: 1rem;">
            <button type="button" class="modal-close-btn absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl leading-none" data-action="close-admin-help-modal" aria-label="Close">×</button>
            <a href="/help.php" target="_blank" rel="noopener" class="btn btn-secondary">Open in new tab</a>
            <button type="button" class="btn btn-primary" data-action="close-admin-help-modal">Close</button>
        </div>
    </div>
    
</div>
