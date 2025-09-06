<?php
// Admin Reports (Thin Delegator)
// Bootstraps layout if needed and delegates rendering to the canonical sections/admin_reports.php

require_once dirname(__DIR__) . '/includes/vite_helper.php';

if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_reports_footer_shutdown')) {
        function __wf_admin_reports_footer_shutdown() {
            @include __DIR__ . '/../partials/footer.php';
        }
    }
    register_shutdown_function('__wf_admin_reports_footer_shutdown');
}

include dirname(__DIR__) . '/sections/admin_reports.php';
return;
?>
        
        <div class="metric-card primary">
            <div class="metric-label">Orders</div>
            <div class="metric-value"><?= $metrics['filteredOrderCount'] ?></div>
            <div class="metric-meta">For selected period</div>
        </div>
        
        <div class="metric-card secondary">
            <div class="metric-label">Average Order Value</div>
            <div class="metric-value">$<?= number_format($metrics['averageOrderValue'], 2) ?></div>
            <div class="metric-meta">All time average</div>
        </div>
        
        <div class="metric-card warning">
            <div class="metric-label">Total Customers</div>
            <div class="metric-value"><?= $metrics['totalCustomers'] ?></div>
            <div class="metric-meta">All time</div>
        </div>
        
        <div class="metric-card success">
            <div class="metric-label">Payments Received</div>
            <div class="metric-value"><?= $paymentsReceived ?></div>
            <div class="metric-meta">Selected period</div>
        </div>
        
        <div class="metric-card danger">
            <div class="metric-label">Payments Pending</div>
            <div class="metric-value"><?= $paymentsPending ?></div>
            <div class="metric-meta">Selected period</div>
        </div>
    </div>

    <!- Sales Over Time Chart ->
    <div class="admin-card">
        <h3 class="admin-card-title">📈 Sales Performance Over Time</h3>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!- Payment Method Distribution ->
    <div class="admin-card">
        <h3 class="admin-card-title">💳 Payment Method Distribution</h3>
        <div class="chart-container">
            <canvas id="paymentMethodChart"></canvas>
        </div>
    </div>

    <!- Top Products Table ->
    <div class="admin-card">
        <h3 class="admin-card-title">🏆 Top Selling Products</h3>
        <?php if (empty($topProducts)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">📊</div>
                <div class="empty-title">No Sales Data</div>
                <div class="empty-subtitle">No product sales data available for the selected period.</div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $sku => $data): ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($data['name']) ?></td>
                                <td><?= $data['quantity'] ?></td>
                                <td class="font-medium">$<?= number_format($data['revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!- Low Stock Alerts ->
    <div class="admin-card">
        <h3 class="admin-card-title">⚠️ Inventory Alerts</h3>
        <?php if (empty($lowStockProducts)): ?>
            <div class="admin-empty-state">
                <div class="empty-icon">✅</div>
                <div class="empty-title">All Good!</div>
                <div class="empty-subtitle">No low stock alerts at this time.</div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Reorder Point</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $product):
                            $stockLevel = intval($product['stockLevel'] ?? 0);
                            $reorderPoint = intval($product['reorderPoint'] ?? 0);
                            $status = $stockLevel === 0 ? 'Out of Stock' : 'Low Stock';
                            $statusClass = $stockLevel === 0 ? 'danger' : 'warning';
                            ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($product['name'] ?? 'Unknown') ?></td>
                                <td><?= $stockLevel ?></td>
                                <td><?= $reorderPoint ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!- Print Action ->
    <div class="report-actions">
                <button type="button" class="btn btn-secondary js-print-button">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print Report
        </button>
    </div>
    </div>
</div>

<!-- Pass data to JS -->
<script type="application/json" id="reports-data">
    <?= json_encode($chartData) ?>
</script>

