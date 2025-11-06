<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Detect modal context
$isModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

// In modal context, output minimal header and mark body as embedded
if ($isModal) {
    $page = 'admin/marketing';
    // Hint modal_header to also load the marketing entry in dev so event wiring is active inside the iframe
    $extraViteEntry = 'src/entries/admin-marketing.js';
    require_once dirname(__DIR__) . '/partials/modal_header.php';
}

// When not in modal, include full admin layout and navbar
if (!$isModal) {
    if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
        $page = 'admin';
        include dirname(__DIR__) . '/partials/header.php';
        if (!function_exists('__wf_admin_marketing_footer_shutdown')) {
            function __wf_admin_marketing_footer_shutdown()
            {
                @include __DIR__ . '/../partials/footer.php';
            }
        }
        register_shutdown_function('__wf_admin_marketing_footer_shutdown');
    }
    // Always include admin navbar on marketing page when not embedded in a modal
    $section = 'marketing';
    include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';
}

$pdo = Database::getInstance();

// Get marketing suggestions count with error handling
$suggestionCount = 0;
try {
    $result = Database::queryOne("SELECT COUNT(*) as count FROM marketing_suggestions");
    $suggestionCount = $result['count'] ?? 0;
} catch (PDOException $e) {
    // Table might not exist yet
    error_log("Marketing suggestions table not found: " . $e->getMessage());
}

// Precompute KPIs for initial render (non-modal)
$kpiRevenue7d = 0.0; $kpiOrders7d = 0; $kpiAov7d = 0.0; $kpiCustomers30d = 0;
try {
    $rowKpi = Database::queryOne("SELECT SUM(total) as rev, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
    if ($rowKpi) { $kpiRevenue7d = (float)($rowKpi['rev'] ?? 0); $kpiOrders7d = (int)($rowKpi['cnt'] ?? 0); $kpiAov7d = $kpiOrders7d > 0 ? round($kpiRevenue7d / $kpiOrders7d, 2) : 0.0; }
    $rowCust = Database::queryOne("SELECT COUNT(DISTINCT userId) as cust FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    if ($rowCust) { $kpiCustomers30d = (int)($rowCust['cust'] ?? 0); }
} catch (Throwable $e) { /* leave KPIs at zero if query fails */ }
?>



<div class="admin-marketing-page">
    
    <!-- Overview (intro removed; container neutralized to preserve structure around sub-modals) -->
    <div class="admin-card mb-0">
        <!-- intro removed per request; keep a tiny placeholder to avoid mis-nesting -->
        <div class="hidden"></div>

    <!-- Sub-modals inside iframe -->
    <div id="socialManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="socialManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="socialManagerTitle" class="admin-card-title">📱 Social Accounts Manager</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="socialManagerContent" class="space-y-2 text-sm text-gray-700">Loading accounts…</div>
            </div>
        </div>
    </div>

    <div id="newsletterManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="newsletterManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="newsletterManagerTitle" class="admin-card-title">📧 Newsletter Manager</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="newsletterManagerContent" class="space-y-3 text-sm text-gray-700">Loading newsletters…</div>
            </div>
        </div>
    </div>

    <div id="automationManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="automationManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="automationManagerTitle" class="admin-card-title">⚙️ Automation Manager</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="automationManagerContent" class="space-y-3 text-sm text-gray-700">Loading automations…</div>
            </div>
        </div>
    </div>

    <div id="discountManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="discountManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="discountManagerTitle" class="admin-card-title">💸 Discount Codes Manager</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="discountManagerContent" class="space-y-3 text-sm text-gray-700">Loading discounts…</div>
            </div>
        </div>
    </div>

    <div id="couponManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="couponManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="couponManagerTitle" class="admin-card-title">🎟️ Coupons Manager</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="couponManagerContent" class="space-y-3 text-sm text-gray-700">Loading coupons…</div>
            </div>
        </div>
    </div>

    <div id="suggestionsManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="suggestionsManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="suggestionsManagerTitle" class="admin-card-title">🤖 Suggestions Manager</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="suggestionsManagerContent" class="text-sm text-gray-700">View and curate AI suggestions. (Coming soon)</div>
            </div>
        </div>
    </div>

    <div id="contentGeneratorModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="contentGeneratorTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="contentGeneratorTitle" class="admin-card-title">✍️ Content Generator</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div id="contentGeneratorContent" class="space-y-3 text-sm text-gray-700">Loading content generator…</div>
            </div>
        </div>
    </div>

    <div id="intentHeuristicsManagerModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="intentHeuristicsManagerTitle">
        <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="intentHeuristicsManagerTitle" class="admin-card-title">🧠 Intent Heuristics Config</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <iframe id="intentHeuristicsManagerFrame" title="Intent Heuristics Config" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/intent_heuristics_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
            </div>
        </div>
    </div>

    <!-- Marketing Overview (Charts) -->

</div>
    </div>

    <?php /* Overview stats are omitted in modal context */ ?>
    <?php if (!$isModal): ?>
    <!-- Stats Cards: entire card clickable (compact) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
        <div class="admin-card p-2">
            <div class="text-center">
                <div class="text-xl font-bold text-blue-600" id="kpiRevenue"><?php echo number_format((float)($kpiRevenue7d ?? 0), 2) ?></div>
                <div class="text-xs text-gray-600">Revenue (7d)</div>
            </div>
        </div>
        <div class="admin-card p-2">
            <div class="text-center">
                <div class="text-xl font-bold text-green-600" id="kpiOrders"><?php echo (int)($kpiOrders7d ?? 0) ?></div>
                <div class="text-xs text-gray-600">Orders (7d)</div>
            </div>
        </div>
        <div class="admin-card p-2">
            <div class="text-center">
                <div class="text-xl font-bold text-purple-600" id="kpiAov"><?php echo number_format((float)($kpiAov7d ?? 0), 2) ?></div>
                <div class="text-xs text-gray-600">Avg Order Value (7d)</div>
            </div>
        </div>
        <div class="admin-card p-2">
            <div class="text-center">
                <div class="text-xl font-bold text-orange-600" id="kpiCustomers"><?php echo (int)($kpiCustomers30d ?? 0) ?></div>
                <div class="text-xs text-gray-600">Customers (30d)</div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>

    <!-- Timeframe controls for charts -->
    <div class="flex justify-end mb-2">
      <div class="inline-flex gap-2" role="group" aria-label="Timeframe">
        <button class="btn btn-sm btn-primary" data-timeframe="7">7d</button>
        <button class="btn btn-sm btn-secondary" data-timeframe="30">30d</button>
        <button class="btn btn-sm btn-secondary" data-timeframe="90">90d</button>
      </div>
    </div>

    <!-- Tools (single list of categories with sub-boxes) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="admin-card">
        <h3 class="admin-card-title">📈 Performance</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="rounded border p-2 bg-white">
            <div class="text-sm font-medium mb-1">Sales (last 7 days)</div>
            <div class="h-[240px]"><canvas id="salesChart"></canvas></div>
          </div>
          <div class="rounded border p-2 bg-white">
            <div class="text-sm font-medium mb-1">Payment Methods (30 days)</div>
            <div class="h-[240px]"><canvas id="paymentMethodChart"></canvas></div>
          </div>
        </div>
      </div>
      <div class="admin-card">
        <h3 class="admin-card-title">🧠 Insights</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="rounded border p-2 bg-white">
            <div class="text-sm font-medium mb-1">Top Categories (30 days)</div>
            <div class="h-[240px]"><canvas id="topCategoriesChart"></canvas></div>
          </div>
          <div class="rounded border p-2 bg-white">
            <div class="text-sm font-medium mb-1">Top Products (30 days)</div>
            <div class="h-[240px]"><canvas id="topProductsChart"></canvas></div>
          </div>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mt-6">
      <div class="admin-card">
        <h3 class="admin-card-title">📦 Order Status (30 days)</h3>
        <div class="rounded border p-2 bg-white">
          <div class="h-[260px]"><canvas id="orderStatusChart"></canvas></div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
      <div class="admin-card">
        <h3 class="admin-card-title">🧑‍🤝‍🧑 Customers</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="rounded border p-2 bg-white">
            <div class="text-sm font-medium mb-1">New vs Returning (30 days)</div>
            <div class="h-[240px]"><canvas id="newReturningChart"></canvas></div>
          </div>
          <div class="rounded border p-2 bg-white">
            <div class="text-sm font-medium mb-1">Shipping Methods (30 days)</div>
            <div class="h-[240px]"><canvas id="shippingMethodChart"></canvas></div>
          </div>
        </div>
      </div>
      <div class="admin-card">
        <h3 class="admin-card-title">💹 AOV Trend</h3>
        <div class="rounded border p-2 bg-white">
          <div class="h-[260px]"><canvas id="aovTrendChart"></canvas></div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
      <div class="admin-card">
        <h3 class="admin-card-title">📣 Top Channels</h3>
        <div class="rounded border p-2 bg-white">
          <div class="h-[260px]"><canvas id="channelsChart"></canvas></div>
        </div>
      </div>
      <div class="admin-card">
        <h3 class="admin-card-title">💰 Revenue by Channel</h3>
        <div class="rounded border p-2 bg-white">
          <div class="h-[260px]"><canvas id="channelRevenueChart"></canvas></div>
        </div>
      </div>
    </div>

    </div>

<script>
// Intentionally left empty: all modal behavior and API calls are handled by Vite modules.
</script>

<?php
// Build chart data for Marketing Overview
try {
    // Labels for last 7 days including today
    $labels = [];
    $totalsMap = [];
    $start = new DateTime('-6 days');
    $end = new DateTime('today');
    $period = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
    foreach ($period as $d) {
        $key = $d->format('Y-m-d');
        $labels[] = $key;
        $totalsMap[$key] = 0.0;
    }

    // Sum order totals by day
    $rows = Database::queryAll("SELECT DATE(`date`) as day, SUM(total) as sum_total FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(`date`) ORDER BY day ASC");
    foreach ($rows as $r) {
        $day = $r['day'] ?? null; $sum = (float)($r['sum_total'] ?? 0);
        if ($day && isset($totalsMap[$day])) { $totalsMap[$day] = $sum; }
    }
    $salesValues = array_values($totalsMap);

    // Payment method distribution
    $payRows = Database::queryAll("SELECT LOWER(COALESCE(paymentMethod, 'other')) as method, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY LOWER(COALESCE(paymentMethod, 'other')) ORDER BY cnt DESC");
    $paymentLabels = [];
    $paymentValues = [];
    foreach ($payRows as $pr) { $paymentLabels[] = $pr['method']; $paymentValues[] = (int)$pr['cnt']; }

    // KPIs (7d revenue/orders/AOV) and customers (30d)
    $kpiRevenue7d = 0.0; $kpiOrders7d = 0; $kpiAov7d = 0.0; $kpiCustomers30d = 0;
    $rowKpi = Database::queryOne("SELECT SUM(total) as rev, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
    if ($rowKpi) { $kpiRevenue7d = (float)($rowKpi['rev'] ?? 0); $kpiOrders7d = (int)($rowKpi['cnt'] ?? 0); $kpiAov7d = $kpiOrders7d > 0 ? round($kpiRevenue7d / $kpiOrders7d, 2) : 0.0; }
    $rowCust = Database::queryOne("SELECT COUNT(DISTINCT userId) as cust FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    if ($rowCust) { $kpiCustomers30d = (int)($rowCust['cust'] ?? 0); }

    // Top categories (30d)
    $tcRows = Database::queryAll(
        "SELECT COALESCE(i.category, 'Uncategorized') as label, SUM(oi.quantity * oi.price) as revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         LEFT JOIN items i ON i.sku = oi.sku
         WHERE o.`date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY COALESCE(i.category, 'Uncategorized')
         ORDER BY revenue DESC
         LIMIT 5"
    );
    $topCatLabels = []; $topCatValues = [];
    foreach ($tcRows as $r) { $topCatLabels[] = $r['label']; $topCatValues[] = round((float)($r['revenue'] ?? 0), 2); }

    // Top products (30d)
    $tpRows = Database::queryAll(
        "SELECT oi.sku as sku, COALESCE(i.name, oi.sku) as label, SUM(oi.quantity * oi.price) as revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         LEFT JOIN items i ON i.sku = oi.sku
         WHERE o.`date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY oi.sku, label
         ORDER BY revenue DESC
         LIMIT 5"
    );
    $topProdLabels = []; $topProdValues = [];
    foreach ($tpRows as $r) { $topProdLabels[] = $r['label']; $topProdValues[] = round((float)($r['revenue'] ?? 0), 2); }

    // Order status distribution (30d)
    $stRows = Database::queryAll("SELECT COALESCE(order_status, 'unknown') as status, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY COALESCE(order_status, 'unknown') ORDER BY cnt DESC");
    $statusLabels = []; $statusValues = [];
    foreach ($stRows as $r) { $statusLabels[] = $r['status']; $statusValues[] = (int)($r['cnt'] ?? 0); }

    // New vs Returning (30d)
    $newCustomers = 0; $returningCustomers = 0;
    $winUsers = Database::queryAll("SELECT DISTINCT userId as uid FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $uids = array_values(array_filter(array_map(function($r){ return $r['uid'] ?? null; }, $winUsers)));
    if ($uids) {
        $placeholders = implode(',', array_fill(0, count($uids), '?'));
        $params = $uids;
        $retRows = Database::queryAll("SELECT COUNT(DISTINCT userId) as cnt FROM orders WHERE userId IN ($placeholders) AND `date` < DATE_SUB(CURDATE(), INTERVAL 30 DAY)", $params);
        $returningCustomers = (int)($retRows[0]['cnt'] ?? 0);
        $newCustomers = max(0, count($uids) - $returningCustomers);
    }

    // Shipping methods (30d)
    $shipRows = Database::queryAll("SELECT COALESCE(shippingMethod, 'Other') as method, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY COALESCE(shippingMethod, 'Other') ORDER BY cnt DESC");
    $shipLabels = []; $shipValues = [];
    foreach ($shipRows as $sr) { $shipLabels[] = $sr['method']; $shipValues[] = (int)($sr['cnt'] ?? 0); }

    // AOV trend (7d baseline for initial view)
    $aovMap = [];
    foreach ($labels as $dLabel) { $aovMap[$dLabel] = 0.0; }
    $aovRows = Database::queryAll("SELECT DATE(`date`) as day, SUM(total)/NULLIF(COUNT(*),0) as aov FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(`date`) ORDER BY day ASC");
    foreach ($aovRows as $ar) { $day = $ar['day'] ?? null; $val = (float)($ar['aov'] ?? 0); if ($day && isset($aovMap[$day])) $aovMap[$day] = round($val,2); }
    $aovTrend = [ 'labels' => $labels, 'values' => array_values($aovMap) ];

    // Attribution (Top Channels and Channel Revenue) — best-effort if analytics_sessions exists
    $chLabels = []; $chValues = [];
    $revChLabels = []; $revChValues = [];
    try {
        $chRows = Database::queryAll(
            "SELECT 
                CASE 
                  WHEN COALESCE(utm_source,'') <> '' THEN utm_source
                  WHEN COALESCE(referrer,'') <> '' THEN referrer
                  ELSE 'Direct'
                END as channel,
                COUNT(*) as cnt
             FROM analytics_sessions
             WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY channel
             ORDER BY cnt DESC
             LIMIT 6"
        );
        foreach ($chRows as $r) { $chLabels[] = $r['channel']; $chValues[] = (int)($r['cnt'] ?? 0); }
    } catch (Throwable $e) { /* ignore */ }
    try {
        $revRows = Database::queryAll(
            "SELECT 
                CASE 
                  WHEN COALESCE(utm_source,'') <> '' THEN utm_source
                  WHEN COALESCE(referrer,'') <> '' THEN referrer
                  ELSE 'Direct'
                END as channel,
                SUM(conversion_value) as revenue
             FROM analytics_sessions
             WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND converted = 1
             GROUP BY channel
             ORDER BY revenue DESC
             LIMIT 6"
        );
        foreach ($revRows as $r) { $revChLabels[] = $r['channel']; $revChValues[] = round((float)($r['revenue'] ?? 0), 2); }
    } catch (Throwable $e) { /* ignore */ }

    $chartData = [
        'sales' => [ 'labels' => $labels, 'values' => $salesValues ],
        'payments' => [ 'labels' => $paymentLabels, 'values' => $paymentValues ],
        'topCategories' => [ 'labels' => $topCatLabels, 'values' => $topCatValues ],
        'topProducts' => [ 'labels' => $topProdLabels, 'values' => $topProdValues ],
        'status' => [ 'labels' => $statusLabels, 'values' => $statusValues ],
        // Normalize to keys expected by admin-marketing.js updateKpis()
        'kpis' => [ 'revenue' => $kpiRevenue7d, 'orders' => $kpiOrders7d, 'aov' => $kpiAov7d, 'customers' => $kpiCustomers30d ],
        'newReturning' => [ 'labels' => ['New','Returning'], 'values' => [ $newCustomers, $returningCustomers ] ],
        'shipping' => [ 'labels' => $shipLabels, 'values' => $shipValues ],
        'aovTrend' => $aovTrend,
        'channels' => [ 'labels' => $chLabels, 'values' => $chValues ],
        'channelRevenue' => [ 'labels' => $revChLabels, 'values' => $revChValues ],
    ];
} catch (Throwable $e) {
    $chartData = [ 'sales' => [ 'labels' => [], 'values' => [] ], 'payments' => [ 'labels' => [], 'values' => [] ] ];
}
?>
<script type="application/json" id="marketingChartData"><?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE); ?></script>

<?php
// Emit marketing entry: prefer dev server when reachable (like modal_header), else fall back to prod manifest
$origin = getenv('WF_VITE_ORIGIN');
if (!$origin && file_exists(dirname(__DIR__) . '/hot')) {
    $origin = trim((string)@file_get_contents(dirname(__DIR__) . '/hot'));
}
if (!$origin) { $origin = 'http://localhost:5176'; }
try {
    $parts = @parse_url($origin);
    if (is_array($parts) && ($parts['host'] ?? '') === '127.0.0.1') {
        $origin = ($parts['scheme'] ?? 'http') . '://localhost' . (isset($parts['port']) ? (':' . $parts['port']) : '') . ($parts['path'] ?? '');
    }
} catch (Throwable $____e) { /* ignore */ }
$ctx = stream_context_create(['http'=>['timeout'=>0.6,'ignore_errors'=>true],'https'=>['timeout'=>0.6,'ignore_errors'=>true]]);
if (@file_get_contents(rtrim($origin,'/') . '/@vite/client', false, $ctx) !== false) {
    echo '<script crossorigin="anonymous" type="module" src="' . rtrim($origin,'/') . '/@vite/client"></script>' . "\n";
    echo '<script crossorigin="anonymous" type="module" src="' . rtrim($origin,'/') . '/src/entries/admin-marketing.js"></script>' . "\n";
} else {
    echo vite_entry('src/entries/admin-marketing.js');
}
?>
