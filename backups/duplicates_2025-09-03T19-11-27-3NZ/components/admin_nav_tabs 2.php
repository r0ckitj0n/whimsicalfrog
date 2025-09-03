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
    <div class="flex flex-wrap gap-2">
        <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
            <?php $tooltipId = $tooltipIds[$key] ?? ''; ?>
            <a href="/?page=admin<?= $key ? '&section=' . $key : '' ?>"
               id="<?= $tooltipId ?>"
               class="admin-nav-tab <?= $cssClass ?> <?= ($section === $key || ($key === '' && !$section)) ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
