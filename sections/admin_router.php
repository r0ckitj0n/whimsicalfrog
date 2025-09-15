<?php
// Admin Router - Central administrative interface (migrated from admin/admin.php)
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_root_footer_shutdown')) {
        function __wf_admin_root_footer_shutdown() {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_root_footer_shutdown');
}

// Get current user data (lightweight; avoid loading section data up-front)
if (!function_exists('getCurrentUser') && class_exists('AuthHelper')) {
    $userData = AuthHelper::getCurrentUser() ?? [];
} else {
    $userData = getCurrentUser() ?? [];
}
$adminName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
if (empty($adminName)) { $adminName = $userData['username'] ?? 'Admin'; }
$adminRole = $userData['role'] ?? 'Administrator';
?>

<div class="admin-dashboard page-content">
    <?php
    // Derive admin section from query when loading this router directly
    if (!isset($adminSection) || $adminSection === '' || $adminSection === null) {
        $raw = isset($_GET['section']) ? strtolower((string)$_GET['section']) : '';
        $aliases = [
            'index' => 'dashboard', 'home' => 'dashboard',
            'order' => 'orders',
            'product' => 'inventory', 'products' => 'inventory',
            'customer' => 'customers', 'user' => 'customers', 'users' => 'customers',
            'report' => 'reports',
            'setting' => 'settings', 'admin_settings' => 'settings', 'admin_settings.php' => 'settings',
            // Tool aliases
            'room_config_manager' => 'room-config-manager', 'room-config-manager' => 'room-config-manager',
            'room_map_manager' => 'room-map-manager', 'room-map-manager' => 'room-map-manager',
            'area_item_mapper' => 'area-item-mapper', 'area-item-mapper' => 'area-item-mapper',
            'room_map_editor' => 'room-map-editor', 'room-map-editor' => 'room-map-editor',
        ];
        if (isset($aliases[$raw])) { $raw = $aliases[$raw]; }
        $adminSection = $raw;
    }
    // Default to dashboard when section missing or unknown
    $currentSection = $adminSection ?: 'dashboard';
    ?>

    <div id="admin-section-content">
        <?php
        switch ($currentSection) {
            case 'customers':
                include dirname(__DIR__) . '/sections/admin_customers.php';
                break;
            case 'inventory':
            case 'admin_inventory':
                include dirname(__DIR__) . '/sections/admin_inventory.php';
                break;
            case 'orders':
                include dirname(__DIR__) . '/sections/admin_orders.php';
                break;
            case 'pos':
                include dirname(__DIR__) . '/sections/admin_pos.php';
                break;
            case 'reports':
                include dirname(__DIR__) . '/sections/admin_reports.php';
                break;
            case 'marketing':
                include dirname(__DIR__) . '/sections/admin_marketing.php';
                break;
            case 'settings':
                include dirname(__DIR__) . '/sections/admin_settings.php';
                break;
            case 'room-config-manager':
                include dirname(__DIR__) . '/sections/tools/room_config_manager.php';
                break;
            case 'room-map-manager':
                include dirname(__DIR__) . '/sections/tools/room_map_manager.php';
                break;
            case 'area-item-mapper':
                include dirname(__DIR__) . '/sections/tools/area_item_mapper.php';
                break;
            case 'room-map-editor':
                include dirname(__DIR__) . '/sections/tools/room_map_editor.php';
                break;
            case 'cost-breakdown-manager':
            case 'cost_breakdown_manager':
                include dirname(__DIR__) . '/sections/tools/cost_breakdown_manager.php';
                break;
            case 'db-status':
            case 'db_status':
                include dirname(__DIR__) . '/sections/tools/db_status.php';
                break;
            case 'reports-browser':
            case 'reports_browser':
                include dirname(__DIR__) . '/sections/tools/reports_browser.php';
                break;
            case 'db-web-manager':
            case 'db_web_manager':
                include dirname(__DIR__) . '/sections/tools/db_web_manager.php';
                break;
            case 'db-manager':
            case 'db_manager':
                include dirname(__DIR__) . '/sections/tools/db_manager.php';
                break;
            case 'db-quick':
            case 'db_quick':
                include dirname(__DIR__) . '/sections/tools/db_quick.php';
                break;
            case 'cron-manager':
            case 'cron_manager':
                include dirname(__DIR__) . '/sections/tools/cron_manager.php';
                break;
            case 'attributes-embed':
            case 'attributes_embed':
                include dirname(__DIR__) . '/components/embeds/attributes_embed.php';
                break;
            case 'categories-embed':
            case 'categories_embed':
                include dirname(__DIR__) . '/components/embeds/categories_embed.php';
                break;
            case 'secrets':
                include dirname(__DIR__) . '/sections/admin_secrets.php';
                break;
            case 'categories':
                include dirname(__DIR__) . '/sections/admin_categories.php';
                break;
            case 'account-settings':
            case 'account_settings':
                include dirname(__DIR__) . '/sections/account_settings.php';
                break;
            case 'dashboard':
            default:
                include dirname(__DIR__) . '/sections/admin_dashboard.php';
                break;
        }
        ?>
    </div>
</div>
