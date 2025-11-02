<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Detect modal context
$isModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

// In modal context, output minimal header and mark body as embedded
if ($isModal) {
    $page = 'admin/marketing';
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
    <div id="marketingOverviewModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="marketingOverviewTitle">
        <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
            <div class="modal-header">
                <h2 id="marketingOverviewTitle" class="admin-card-title">📈 Marketing Overview</h2>
                <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded border p-2 bg-white">
                        <div class="text-sm font-medium mb-1">Sales (last 7 days)</div>
                        <canvas id="salesChart" height="220"></canvas>
                    </div>
                    <div class="rounded border p-2 bg-white">
                        <div class="text-sm font-medium mb-1">Payment Methods</div>
                        <canvas id="paymentMethodChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
    </div>

    <?php /* Overview stats are omitted in modal context */ ?>
    <?php if (!$isModal): ?>
    <!-- Stats Cards: entire card clickable (compact) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
        <div class="admin-card p-2 cursor-pointer" data-action="open-suggestions-manager" role="button" aria-label="View suggestions">
            <div class="text-center">
                <div class="text-xl font-bold text-blue-600"><?php echo $suggestionCount ?></div>
                <div class="text-xs text-gray-600">AI Suggestions</div>
            </div>
        </div>
        <div class="admin-card p-2 cursor-pointer" data-action="open-newsletters-manager" role="button" aria-label="View campaigns">
            <div class="text-center">
                <div class="text-xl font-bold text-green-600">3</div>
                <div class="text-xs text-gray-600">Campaigns</div>
            </div>
        </div>
        <div class="admin-card p-2 cursor-pointer" data-action="open-marketing-overview" role="button" aria-label="View conversion report">
            <div class="text-center">
                <div class="text-xl font-bold text-purple-600">2.4%</div>
                <div class="text-xs text-gray-600">Conversion</div>
            </div>
        </div>
        <div class="admin-card p-2 cursor-pointer" data-action="open-newsletters-manager" role="button" aria-label="View emails">
            <div class="text-center">
                <div class="text-xl font-bold text-orange-600">15</div>
                <div class="text-xs text-gray-600">Emails Sent</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tools (single list of categories with sub-boxes) -->
    <?php if ($isModal): ?>
      <div class="admin-card">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="border rounded p-3">
            <button data-action="open-suggestions-manager" class="btn btn-primary w-full">🤖 Suggestions Manager</button>
            <div class="text-sm text-gray-600 mt-2">Generate AI content, price, and cost for an item, review/edit, then apply.</div>
          </div>
          <div class="border rounded p-3">
            <button data-action="open-content-generator" class="btn btn-secondary w-full">✍️ Content Generator</button>
            <div class="text-sm text-gray-600 mt-2">Create AI-assisted marketing content.</div>
          </div>
          <div class="border rounded p-3">
            <button data-action="open-social-manager" class="btn btn-secondary w-full">📱 Social Accounts Manager</button>
            <div class="text-sm text-gray-600 mt-2">Connect accounts and manage posts.</div>
          </div>
          <div class="border rounded p-3">
            <button data-action="open-intent-heuristics-manager" class="btn btn-secondary w-full">🧠 Intent Heuristics Config</button>
            <div class="text-sm text-gray-600 mt-2">Tune upsell scoring (weights, budgets, keywords, seasonality).</div>
          </div>
          <div class="border rounded p-3">
            <button data-action="open-automation-manager" class="btn btn-secondary w-full">⚙️ Automation Manager</button>
            <div class="text-sm text-gray-600 mt-2">Set up flows and triggers.</div>
          </div>
          <div class="border rounded p-3 md:col-span-2">
            <button data-action="open-ai-provider-parent" class="btn btn-secondary w-full">🤖 AI Settings</button>
            <div class="text-sm text-gray-600 mt-2">Configure provider, models, credentials, and behavior.</div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="admin-card">
          <h3 class="admin-card-title">🤖 AI Tools</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="border rounded p-3">
              <button data-action="open-suggestions-manager" class="btn btn-primary w-full">🤖 Suggestions Manager</button>
              <div class="text-sm text-gray-600 mt-2">Generate AI content, price, and cost for an item, review/edit, then apply.</div>
            </div>
            <div class="border rounded p-3">
              <button data-action="open-content-generator" class="btn btn-secondary w-full">✍️ Content Generator</button>
              <div class="text-sm text-gray-600 mt-2">Create AI-assisted marketing content.</div>
            </div>
            <div class="border rounded p-3">
              <button data-action="open-social-manager" class="btn btn-secondary w-full">📱 Social Accounts Manager</button>
              <div class="text-sm text-gray-600 mt-2">Connect accounts and manage posts.</div>
            </div>
            <div class="border rounded p-3">
              <button data-action="open-intent-heuristics-manager" class="btn btn-secondary w-full">🧠 Intent Heuristics Config</button>
              <div class="text-sm text-gray-600 mt-2">Tune upsell scoring (weights, budgets, keywords, seasonality).</div>
            </div>
            <div class="border rounded p-3 md:col-span-2">
              <button data-action="open-ai-provider-parent" class="btn btn-secondary w-full">🤖 AI Settings</button>
              <div class="text-sm text-gray-600 mt-2">Configure provider, models, credentials, and behavior.</div>
            </div>
          </div>
        </div>
        <div class="admin-card">
          <h3 class="admin-card-title">📧 Email Marketing</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="border rounded p-3">
              <button data-action="open-newsletters-manager" class="btn btn-primary w-full">📧 Newsletter Manager</button>
              <div class="text-sm text-gray-600 mt-2">Create, schedule, and review newsletters.</div>
            </div>
            <div class="border rounded p-3">
              <button data-action="open-automation-manager" class="btn btn-secondary w-full">⚙️ Automation Manager</button>
              <div class="text-sm text-gray-600 mt-2">Set up flows and triggers.</div>
            </div>
          </div>
        </div>
        <div class="admin-card">
          <h3 class="admin-card-title">💰 Promotions</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="border rounded p-3">
              <button data-action="open-discounts-manager" class="btn btn-primary w-full">💸 Discount Codes Manager</button>
              <div class="text-sm text-gray-600 mt-2">Generate and manage discount codes.</div>
            </div>
            <div class="border rounded p-3">
              <button data-action="open-coupons-manager" class="btn btn-secondary w-full">🎟️ Coupons Manager</button>
              <div class="text-sm text-gray-600 mt-2">Create printable or digital coupons.</div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

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

    $chartData = [
        'sales' => [ 'labels' => $labels, 'values' => $salesValues ],
        'payments' => [ 'labels' => $paymentLabels, 'values' => $paymentValues ],
    ];
} catch (Throwable $e) {
    $chartData = [ 'sales' => [ 'labels' => [], 'values' => [] ], 'payments' => [ 'labels' => [], 'values' => [] ] ];
}
?>
<script type="application/json" id="marketingChartData"><?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE); ?></script>

<?php echo vite_entry('src/entries/admin-marketing.js'); ?>
