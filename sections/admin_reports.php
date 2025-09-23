<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_reports_footer_shutdown')) {
        function __wf_admin_reports_footer_shutdown()
        {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_reports_footer_shutdown');
}

// Always include admin navbar on reports page, even when accessed directly
$section = 'reports';
include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';

$pdo = Database::getInstance();
$timeframe = $_GET['timeframe'] ?? '7d';
$days = ($timeframe === '30d') ? 30 : 7;

// Get basic sales data with error handling
$salesData = [];
$paymentData = [];

try {
    // Use correct column names: date, total, paymentMethod
    $salesData = Database::queryAll(
        "SELECT DATE(date) as date, COUNT(*) as orders, SUM(total) as revenue
         FROM orders WHERE date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
         GROUP BY DATE(date) ORDER BY date"
    );

    $paymentData = Database::queryAll(
        "SELECT paymentMethod as payment_method, COUNT(*) as count, SUM(total) as revenue FROM orders 
         WHERE date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
         GROUP BY paymentMethod ORDER BY count DESC"
    );

    // Get summary stats with correct columns
    $totalStats = Database::queryOne(
        "SELECT COUNT(*) as total_orders, SUM(total) as total_revenue, 
                AVG(total) as avg_order_value, COUNT(DISTINCT userId) as unique_customers
         FROM orders WHERE date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
    );

    // Get order status breakdown
    $statusData = Database::queryAll(
        "SELECT order_status, COUNT(*) as count FROM orders 
         WHERE date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
         GROUP BY order_status ORDER BY count DESC"
    );

    // Get recent orders
    $recentOrders = Database::queryAll(
        "SELECT id, userId as customerName, total, paymentMethod, order_status, date
         FROM orders ORDER BY date DESC LIMIT 10"
    );

    // Get top customers
    $topCustomers = Database::queryAll(
        "SELECT userId as customer, COUNT(*) as order_count, SUM(total) as total_spent
         FROM orders WHERE date >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
         GROUP BY userId ORDER BY total_spent DESC LIMIT 5"
    );

} catch (PDOException $e) {
    // Fallback: try without date filtering if columns don't exist
    try {
        $salesData = Database::queryAll(
            "SELECT DATE(NOW()) as date, COUNT(*) as orders, SUM(totalAmount) as revenue
             FROM orders 
             GROUP BY DATE(NOW())"
        );

        $paymentData = Database::queryAll(
            "SELECT paymentMethod as payment_method, COUNT(*) as count FROM orders 
             GROUP BY paymentMethod"
        );
    } catch (PDOException $e2) {
        // If still failing, use empty data
        error_log("Reports query failed: " . $e2->getMessage());
    }
}

$chartLabels = [];
$chartRevenue = [];
$chartOrders = [];
foreach ($salesData as $day) {
    $chartLabels[] = date('M j', strtotime($day['date']));
    $chartRevenue[] = floatval($day['revenue'] ?? 0);
    $chartOrders[] = intval($day['orders'] ?? 0);
}

// Add sample data if no real data
if (empty($chartLabels)) {
    $chartLabels = ['Today'];
    $chartRevenue = [0];
    $chartOrders = [0];
}

$paymentLabels = [];
$paymentCounts = [];
foreach ($paymentData as $payment) {
    $paymentLabels[] = ucfirst($payment['payment_method'] ?? 'Unknown');
    $paymentCounts[] = intval($payment['count'] ?? 0);
}

// Add sample data if no payment data
if (empty($paymentLabels)) {
    $paymentLabels = ['No Data'];
    $paymentCounts = [1];
}
?>

<style>
body[data-page='admin/reports'] #admin-section-content {
    padding-top: 0 !important;
    margin-top: 0 !important;
    border-top: none !important;
}
.admin-reports-page {
    padding-top: 0 !important;
    margin-top: 0 !important;
}
</style>

<div class="admin-reports-page">
    <div class="flex justify-end items-center mb-6">
        <select onchange="window.location.href='?section=reports&timeframe=' + this.value" class="admin-form-select">
            <option value="7d" <?= $timeframe === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="30d" <?= $timeframe === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
        </select>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-green-600">$<?= number_format($totalStats['total_revenue'] ?? 0, 2) ?></div>
            <div class="text-sm text-gray-600">Total Revenue</div>
        </div>
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-blue-600"><?= number_format($totalStats['total_orders'] ?? 0) ?></div>
            <div class="text-sm text-gray-600">Total Orders</div>
        </div>
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-purple-600">$<?= number_format($totalStats['avg_order_value'] ?? 0, 2) ?></div>
            <div class="text-sm text-gray-600">Avg Order Value</div>
        </div>
        <div class="admin-card text-center">
            <div class="text-2xl font-bold text-orange-600"><?= number_format($totalStats['unique_customers'] ?? 0) ?></div>
            <div class="text-sm text-gray-600">Customers</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="admin-card">
            <h3 class="admin-card-title">📈 Sales Performance</h3>
            <div style="height: 300px;"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="admin-card">
            <h3 class="admin-card-title">💳 Payment Methods</h3>
            <div style="height: 300px;"><canvas id="paymentMethodChart"></canvas></div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="admin-card">
        <h3 class="admin-card-title">📋 Recent Orders</h3>
        <table class="admin-table">
            <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['id']) ?></td>
                    <td><?= htmlspecialchars($order['customerName']) ?></td>
                    <td>$<?= number_format($order['total'], 2) ?></td>
                    <td><?= htmlspecialchars($order['order_status'] ?? 'Pending') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script type="application/json" id="reports-data">
<?= json_encode([
    'labels' => $chartLabels,
    'revenue' => $chartRevenue,
    'orders' => $chartOrders,
    'paymentLabels' => $paymentLabels,
    'paymentCounts' => $paymentCounts
]) ?>
</script>

<?php echo vite_entry('src/entries/admin-reports.js'); ?>
