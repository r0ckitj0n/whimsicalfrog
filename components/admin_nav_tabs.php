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
<div class="admin-tab-navigation mb-1">
    <div class="wf-admin-nav-row">
        <div class="wf-nav-left"></div>

        <!-- Centered tabs -->
        <div class="wf-nav-center">
        <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
            <?php $tooltipId = $tooltipIds[$key] ?? ''; ?>
            <?php
                // Route all tabs through the canonical admin router so header/navbar is always rendered.
                $href = ($key === '') ? '/admin' : ('/admin/?section=' . urlencode($key));
            ?>
            <a href="<?= $href ?>"
               id="<?= $tooltipId ?>"
               class="admin-nav-tab <?= $cssClass ?> <?= ($section === $key || ($key === '' && !$section)) ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
        </div>

        <!-- Right-aligned help bubble: single green circle containing '?' and slider toggle -->
        <div class="wf-nav-right">
            <button type="button"
                    id="adminHelpCombo"
                    class="admin-nav-tab admin-tab-help admin-help-combo"
                    data-help-toggle-root="1"
                    aria-pressed="false"
                    aria-label="Open documentation or toggle Help &amp; Hints"
                    title="Help &amp; Hints">
                <span class="help-q" data-action="open-admin-help-modal" aria-hidden="true">?</span>
                <span class="wf-toggle" data-action="help-toggle-global-tooltips" aria-hidden="true"><span class="wf-knob"></span></span>
            </button>
        </div>
    </div>
</div>

<!-- Help Documentation Modal -->
<div id="adminHelpDocsModal" class="admin-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="adminHelpDocsTitle">
    <div class="admin-modal w-full max-w-5xl flex flex-col" role="document">
        <div class="flex items-center justify-between p-4 border-b">
            <h2 id="adminHelpDocsTitle" class="text-lg font-semibold">Documentation</h2>
            <button type="button" class="admin-modal-close admin-modal-close--tight" data-action="close-admin-help-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body flex-1">
            <iframe id="adminHelpDocsFrame" class="admin-help-iframe" title="Admin Documentation" loading="lazy"></iframe>
        </div>
        <div class="flex items-center justify-end gap-2 p-3 border-t">
            <a href="/api/admin_file_proxy.php?path=documentation/ADMIN_GUIDE.md" target="_blank" rel="noopener" class="btn btn-secondary">Open in new tab</a>
            <button type="button" class="btn btn-primary" data-action="close-admin-help-modal">Close</button>
        </div>
    </div>
    
</div>
