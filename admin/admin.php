<?php
// Admin Dashboard - Main administrative interface
require_once __DIR__ . '/../includes/functions.php';

// Get current user data (lightweight; avoid loading section data up-front)
$userData = getCurrentUser() ?? [];
$adminName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
if (empty($adminName)) { $adminName = $userData['username'] ?? 'Admin'; }
$adminRole = $userData['role'] ?? 'Administrator';
?>

<div class="admin-dashboard page-content">



    <!-- Navigation Tabs -->
    <?php
    // The $adminSection variable is now passed from index.php
    // Default to 'dashboard' if it's empty or not set.
    $currentSection = $adminSection ?: 'dashboard';

$tabs = [
    'dashboard' => ['Dashboard', 'admin-tab-dashboard'],
    'customers' => ['Customers', 'admin-tab-customers'],
    'inventory' => ['Inventory', 'admin-tab-inventory'],
    'orders' => ['Orders', 'admin-tab-orders'],
    'pos' => ['POS', 'admin-tab-pos'],
    'reports' => ['Reports', 'admin-tab-reports'],
    'marketing' => ['Marketing', 'admin-tab-marketing'],
    'settings' => ['Settings', 'admin-tab-settings'],
    'secrets' => ['Secrets', 'admin-tab-secrets'],
];
?>
    <!-- Inline fallback to enforce horizontal admin navbar layout and spacing on all admin pages -->
    <style id="wf-admin-tabs-inline-fallback">
      body[data-page^='admin'] .admin-tab-navigation { position: fixed !important; top: var(--wf-admin-nav-top, calc(var(--wf-header-height, 64px) + 24px)) !important; left: 0; right: 0; z-index: 2000; margin: 0 !important; padding: 6px 12px !important; display:flex !important; justify-content:center !important; align-items:center !important; width:100% !important; text-align:center !important; }
      body[data-page^='admin'] .admin-tab-navigation > *,
      body[data-page^='admin'] .admin-tab-navigation .flex,
      body[data-page^='admin'] .admin-tab-navigation [class*='u-display-flex'],
      body[data-page^='admin'] .admin-tab-navigation > div,
      body[data-page^='admin'] .admin-tab-navigation ul { display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; gap: 10px !important; justify-content: center !important; align-items: center !important; margin: 0 auto !important; padding: 0 !important; list-style: none !important; width:100% !important; text-align:center !important; }
      body[data-page^='admin'] .admin-tab-navigation ul > li { display: inline-flex !important; margin: 0 !important; padding: 0 !important; }
      body[data-page^='admin'] .admin-tab-navigation .admin-nav-tab { display: inline-flex !important; width: auto !important; max-width: none !important; flex: 0 0 auto !important; white-space: nowrap; text-decoration: none; }
      body[data-page^='admin'] #admin-section-content { padding-top: var(--wf-admin-content-pad, 12px) !important; }
    </style>
    <script>
      (function(){
        try{
          var compute = function(){
            var h = document.querySelector('.site-header') || document.querySelector('.universal-page-header');
            if (h && h.getBoundingClientRect) {
              var hh = Math.max(40, Math.round(h.getBoundingClientRect().height));
              document.documentElement.style.setProperty('--wf-header-height', hh + 'px');
            }
            var hc = document.querySelector('.header-content');
            if (hc && hc.getBoundingClientRect) {
              var b = Math.round(hc.getBoundingClientRect().bottom + 12);
              document.documentElement.style.setProperty('--wf-admin-nav-top', b + 'px');
            }
            var nav = document.querySelector('.admin-tab-navigation');
            if (nav && nav.getBoundingClientRect) {
              var nh = Math.round(nav.getBoundingClientRect().height + 12);
              document.documentElement.style.setProperty('--wf-admin-content-pad', nh + 'px');
            }
          };
          if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', compute, {once:true}); else compute();
          window.addEventListener('load', compute, {once:true});
          window.addEventListener('resize', compute);
          try {
            if (window.ResizeObserver) {
              var ro = new ResizeObserver(function(){ compute(); });
              var hc = document.querySelector('.header-content'); if (hc) ro.observe(hc);
              var h = document.querySelector('.site-header') || document.querySelector('.universal-page-header'); if (h) ro.observe(h);
            }
          } catch(_){ }
        }catch(e){}
      })();
    </script>
    <?php if ($currentSection !== 'pos'): ?>
    <div class="admin-tab-navigation">
        <div class="u-display-flex u-flex-wrap-wrap u-gap-2">
            <?php foreach ($tabs as $key => [$label, $cssClass]): ?>
                <?php
            // Map tab keys to tooltip IDs
            $tooltipIds = [
                'dashboard' => 'adminDashboardTab',
                'customers' => 'adminCustomersTab',
                'inventory' => 'adminInventoryTab',
                'orders' => 'adminOrdersTab',
                'pos' => 'adminPosTab',
                'reports' => 'adminReportsTab',
                'marketing' => 'adminMarketingTab',
                'settings' => 'adminSettingsTab',
                'secrets' => 'adminSecretsTab'
            ];
                $tooltipId = $tooltipIds[$key] ?? '';
                $url = ($key === 'dashboard') ? '/admin' : "/admin/{$key}";
                ?>
                <a href="<?= $url ?>"
                   id="<?= $tooltipId ?>"
                   class="admin-nav-tab <?= $cssClass ?> <?= ($currentSection === $key) ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dynamic Section Content -->
    <div id="admin-section-content">
        <?php
        switch ($currentSection) {
            case 'customers':
                include 'admin_customers.php';
                break;
            case 'inventory':
            case 'admin_inventory':
                include 'admin_inventory.php';
                break;
            case 'orders':
                include 'admin_orders.php';
                break;
            case 'pos':
                include 'admin_pos.php';
                break;
            case 'reports':
                include 'admin_reports.php';
                break;
            case 'marketing':
                include 'admin_marketing.php';
                break;
            case 'settings':
                include 'admin_settings.php';
                break;
            case 'secrets':
                include 'admin_secrets.php';
                break;
            case 'categories':
                include 'admin_categories.php';
                break;
            case 'dashboard':
            default:
                // Load heavy data only for the dashboard
                $inventoryData = [];
                $ordersData = [];
                $customersData = [];
                try {
                    $inventoryData = Database::queryAll('SELECT * FROM items ORDER BY sku');
                    $ordersData = Database::queryAll('SELECT * FROM orders ORDER BY date DESC');
                    foreach ($ordersData as &$order) {
                        $order['items'] = Database::queryAll('SELECT * FROM order_items WHERE orderId = ?', [$order['id']]);
                        if (isset($order['shippingAddress']) && is_string($order['shippingAddress'])) {
                            $order['shippingAddress'] = json_decode($order['shippingAddress'], true);
                        }
                    }
                    $customersData = Database::queryAll('SELECT * FROM users ORDER BY firstName, lastName');
                } catch (Exception $e) {
                    Logger::error('Admin dashboard data loading failed', ['error' => $e->getMessage()]);
                }
                // Calculate dashboard metrics
                $totalProducts = count($inventoryData);
                $totalOrders = count($ordersData);
                $totalCustomers = count($customersData);
                $totalRevenue = array_sum(array_column($ordersData, 'total'));
                $formattedRevenue = '$' . number_format($totalRevenue, 2);
                include 'admin_dashboard.php';
                break;
        }
?>
    </div>
</div>
