<?php
// components/admin_breadcrumbs.php
// Lightweight breadcrumb for admin sections using the clean router (/admin/?section=...)

$section = isset($adminSection) && is_string($adminSection) ? strtolower($adminSection) : '';
if (!$section && isset($_GET['section'])) {
    $section = strtolower((string)$_GET['section']);
}

$map = [
    'dashboard' => 'Dashboard',
    'customers' => 'Customers',
    'inventory' => 'Inventory',
    'orders' => 'Orders',
    'pos' => 'POS',
    'reports' => 'Reports',
    'marketing' => 'Marketing',
    'settings' => 'Settings',
    'categories' => 'Categories',
    'secrets' => 'Secrets',
    'account-settings' => 'Account Settings',
    // Tools
    'room-config-manager' => 'Room Config Manager',
    'room-map-manager' => 'Room Map Manager',
    'area-item-mapper' => 'Area–Item Mapper',
    'room-map-editor' => 'Room Map Editor',
    'cost-breakdown-manager' => 'Cost Breakdown Manager',
    'db-status' => 'DB Status',
    'db-web-manager' => 'DB Web Manager',
];

// Normalize common aliases
$aliases = [
    'admin' => 'dashboard',
    'home' => 'dashboard',
    'index' => 'dashboard',
    'customer' => 'customers',
    'user' => 'customers',
    'users' => 'customers',
    'product' => 'inventory',
    'products' => 'inventory',
    'order' => 'orders',
    'db_status' => 'db-status',
    'db_web_manager' => 'db-web-manager',
    'room_config_manager' => 'room-config-manager',
    'room_map_manager' => 'room-map-manager',
    'area_item_mapper' => 'area-item-mapper',
    'room_map_editor' => 'room-map-editor',
    'cost_breakdown_manager' => 'cost-breakdown-manager',
    'account_settings' => 'account-settings',
];

if (isset($aliases[$section])) {
    $section = $aliases[$section];
}

$label = $map[$section] ?? 'Dashboard';

// Render breadcrumb
?>
<nav class="admin-breadcrumb" aria-label="Breadcrumb">
  <ol class="flex items-center gap-2 text-sm text-gray-600">
    <li><a href="/admin" class="text-blue-600 hover:text-blue-800">Admin</a></li>
    <?php if ($section && $section !== 'dashboard'): ?>
      <li>›</li>
      <li><a href="/admin/?section=<?= htmlspecialchars($section) ?>" class="text-blue-600 hover:text-blue-800"><?= htmlspecialchars($label) ?></a></li>
    <?php endif; ?>
  </ol>
</nav>
