<?php
// Admin Marketing Dashboard
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

require_once __DIR__ . '/../includes/functions.php';

// Get database instance
$db = Database::getInstance();

// Date range parameters
$startInput = $_GET['start_date'] ?? '';
$endInput = $_GET['end_date'] ?? '';
$startParam = $startInput ?: '1900-01-01';
$endParam = $endInput ?: '2100-12-31';

// Initialize data arrays
$metrics = [
    'customerCount' => 0,
    'orderCount' => 0,
    'totalSales' => 0,
    'itemsSold' => 0,
    'paymentsReceived' => 0,
    'paymentsPending' => 0
];

$chartData = [
    'monthLabels' => ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
    'salesData' => [0, 0, 0, 0, 0, 0],
    'paymentMethodLabels' => ['None'],
    'paymentMethodCounts' => [0]
];

$marketingData = [
    'recentOrders' => [],
    'topProducts' => [],
    'emailCampaigns' => [],
    'emailSubscribers' => [],
    'discountCodes' => [],
    'socialAccounts' => [],
    'socialPosts' => []
];

$tableStatus = [
    'emailCampaigns' => false,
    'discountCodes' => false,
    'socialAccounts' => false,
    'allTablesExist' => false
];

try {
    
    
    // Get customer metrics
    $metrics['customerCount'] = Database::queryRow("SELECT COUNT(*) FROM users WHERE role = 'Customer' OR roleType = 'Customer'") ?: 0;
    
    // Check if orders table exists and get order-related metrics
    $tablesResult = Database::queryAll("SHOW TABLES LIKE 'orders'");
    if (!empty($tablesResult)) {
        // Order metrics with date filtering
        $orderParams = [':start' => $startParam, ':end' => $endParam];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(date) BETWEEN :start AND :end");
        $stmt->execute($orderParams);
        $metrics['orderCount'] = $stmt->fetchColumn() ?: 0;
        
        $stmt = $db->prepare("SELECT SUM(total) FROM orders WHERE DATE(date) BETWEEN :start AND :end");
        $stmt->execute($orderParams);
        $metrics['totalSales'] = $stmt->fetchColumn() ?: 0;
        
        // Items sold calculation
        $stmt = $db->prepare("SELECT COALESCE(SUM(oi.quantity), 0) 
                              FROM order_items oi 
                              JOIN orders o ON oi.orderId COLLATE utf8mb4_unicode_ci = o.id COLLATE utf8mb4_unicode_ci 
                              WHERE DATE(o.date) BETWEEN :start AND :end");
        $stmt->execute($orderParams);
        $metrics['itemsSold'] = $stmt->fetchColumn() ?: 0;
        
        // Payment status metrics
        $stmt = $db->prepare("SELECT paymentStatus, COUNT(*) as cnt 
                              FROM orders 
                              WHERE DATE(date) BETWEEN :start AND :end 
                              GROUP BY paymentStatus");
        $stmt->execute($orderParams);
        $paymentStatusData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $metrics['paymentsReceived'] = $paymentStatusData['Received'] ?? 0;
        $metrics['paymentsPending'] = $paymentStatusData['Pending'] ?? 0;
        
        // Recent orders
        $stmt = $db->prepare("SELECT o.*, u.username, u.email 
                              FROM orders o 
                              LEFT JOIN users u ON o.userId = u.id 
                              WHERE DATE(o.date) BETWEEN :start AND :end 
                              ORDER BY o.date DESC 
                              LIMIT 5");
        $stmt->execute($orderParams);
        $marketingData['recentOrders'] = $stmt->fetchAll();
        
        // Monthly sales data for chart
        $stmt = $db->prepare("SELECT DATE_FORMAT(date, '%Y-%m-01') as month_start, SUM(total) as total 
                              FROM orders 
                              WHERE DATE(date) BETWEEN :start AND :end 
                              GROUP BY month_start 
                              ORDER BY month_start");
        $stmt->execute($orderParams);
        $monthlySalesData = $stmt->fetchAll();
        
        if (!empty($monthlySalesData)) {
            $chartData['monthLabels'] = array_map(fn($data) => date("M", strtotime($data['month_start'])), $monthlySalesData);
            $chartData['salesData'] = array_map(fn($data) => (float)$data['total'], $monthlySalesData);
        }
        
        // Top products
        $stmt = $db->prepare("SELECT i.name, SUM(oi.quantity) as units 
                              FROM order_items oi
                              JOIN orders o ON oi.orderId COLLATE utf8mb4_unicode_ci = o.id COLLATE utf8mb4_unicode_ci
                              JOIN items i ON oi.sku COLLATE utf8mb4_unicode_ci = i.sku COLLATE utf8mb4_unicode_ci
                              WHERE DATE(o.date) BETWEEN :start AND :end
                              GROUP BY oi.sku, i.name
                              ORDER BY units DESC
                              LIMIT 5");
        $stmt->execute($orderParams);
        $marketingData['topProducts'] = $stmt->fetchAll();
        
        // Payment method distribution
        $stmt = $db->prepare("SELECT paymentMethod, COUNT(*) as cnt 
                              FROM orders 
                              WHERE DATE(date) BETWEEN :start AND :end 
                              GROUP BY paymentMethod");
        $stmt->execute($orderParams);
        $paymentMethodData = $stmt->fetchAll();
        
        if (!empty($paymentMethodData)) {
            $chartData['paymentMethodLabels'] = array_column($paymentMethodData, 'paymentMethod');
            $chartData['paymentMethodCounts'] = array_map('intval', array_column($paymentMethodData, 'cnt'));
        }
    }
    
    // Check marketing tables
    $tables = ['email_campaigns', 'discount_codes', 'social_accounts', 'social_posts'];
    foreach ($tables as $table) {
        $result = Database::queryAll("SHOW TABLES LIKE '$table'");
        $tableStatus[str_replace('_', '', $table)] = !empty($result);
    }
    
    // Load marketing data if tables exist
    if ($tableStatus['emailcampaigns']) {
        $marketingData['emailCampaigns'] = Database::queryAll("SELECT * FROM email_campaigns ORDER BY created_date DESC");
        
        $subscribersTable = Database::queryAll("SHOW TABLES LIKE 'email_subscribers'");
        if (!empty($subscribersTable)) {
            $marketingData['emailSubscribers'] = Database::queryAll("SELECT * FROM email_subscribers WHERE status = 'active'");
        }
    }
    
    if ($tableStatus['discountcodes']) {
        $marketingData['discountCodes'] = Database::queryAll("SELECT * FROM discount_codes ORDER BY start_date DESC");
    }
    
    if ($tableStatus['socialaccounts']) {
        $marketingData['socialAccounts'] = Database::queryAll("SELECT * FROM social_accounts");
        $marketingData['socialPosts'] = Database::queryAll("SELECT * FROM social_posts ORDER BY scheduled_date DESC");
    }
    
    $tableStatus['allTablesExist'] = $tableStatus['emailcampaigns'] && $tableStatus['discountcodes'] && $tableStatus['socialaccounts'];
    
} catch (Exception $e) {
    Logger::error('Marketing Dashboard Error: ' . $e->getMessage());
}

// Helper function for generating IDs
function generateId($prefix, $length = 3) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $id .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $id;
}
?>

<div class="admin-content-container">
    <div class="admin-filter-section">
        <form class="admin-filter-form" method="get" action="">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="marketing">
            
            <input type="date" id="mFrom" name="start_date" class="admin-form-input" 
                   value="<?= htmlspecialchars($startInput) ?>">
            
            <input type="date" id="mTo" name="end_date" class="admin-form-input" 
                   value="<?= htmlspecialchars($endInput) ?>">
            
            <button type="submit" class="btn-primary admin-filter-button">Apply Filter</button>
        </form>
    </div>

    <div class="admin-table-section">
        <!-- Dashboard Stats -->
    <div class="dashboard-stats-grid mb-8">
        <div class="stat-card">
            <div class="stat-icon customers">👥</div>
            <div class="stat-content">
                <h3 class="stat-label">Total Customers</h3>
                <p class="stat-value"><?= number_format($metrics['customerCount']) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orders">🛒</div>
            <div class="stat-content">
                <h3 class="stat-label">Total Orders</h3>
                <p class="stat-value"><?= number_format($metrics['orderCount']) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon sales">💰</div>
            <div class="stat-content">
                <h3 class="stat-label">Total Sales</h3>
                <p class="stat-value">$<?= number_format($metrics['totalSales'], 2) ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon items">📦</div>
            <div class="stat-content">
                <h3 class="stat-label">Items Sold</h3>
                <p class="stat-value"><?= number_format($metrics['itemsSold']) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon payments">✅</div>
            <div class="stat-content">
                <h3 class="stat-label">Payments Received</h3>
                <p class="stat-value"><?= number_format($metrics['paymentsReceived']) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon pending">⏳</div>
            <div class="stat-content">
                <h3 class="stat-label">Pending Payments</h3>
                <p class="stat-value"><?= number_format($metrics['paymentsPending']) ?></p>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Content Grid -->
    <div class="dashboard-grid">
        <!-- Left Column -->
        <div class="dashboard-column">
            <!-- Sales Overview Chart -->
            <div class="admin-card">
                <div class="card-header">
                    <h3 class="card-title">Sales Overview</h3>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <!-- Payment Methods Chart -->
            <div class="admin-card">
                <div class="card-header">
                    <h3 class="card-title">Payment Methods</h3>
                </div>
                <div class="chart-container chart-payment-methods">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="admin-card">
                <div class="card-header">
                    <h3 class="card-title">Top Items</h3>
                </div>
                <div class="top-products-list">
                    <?php if (!empty($marketingData['topProducts'])): ?>
                        <?php foreach ($marketingData['topProducts'] as $product): ?>
                        <div class="product-item">
                            <span class="product-name"><?= htmlspecialchars($product['name'] ?? '') ?></span>
                            <span class="product-units"><?= number_format($product['units']) ?> units</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="admin-empty-state">
                            <div class="empty-icon">📊</div>
                            <div class="empty-subtitle">No item data available</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="dashboard-column">
            <!-- Recent Orders -->
            <div class="admin-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Orders</h3>
                </div>
                <div class="recent-orders-container">
                    <?php if (!empty($marketingData['recentOrders'])): ?>
                        <div class="table-container">
                            <table class="admin-table compact">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marketingData['recentOrders'] as $order): ?>
                                    <tr>
                                        <td class="order-id"><?= htmlspecialchars($order['id'] ?? '') ?></td>
                                        <td class="customer-name"><?= htmlspecialchars($order['username'] ?? $order['email'] ?? 'Unknown') ?></td>
                                        <td class="order-amount">$<?= number_format($order['total'] ?? 0, 2) ?></td>
                                        <td class="order-date"><?= date('M d, Y', strtotime($order['date'] ?? 'now')) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state">
                            <div class="empty-icon">📋</div>
                            <div class="empty-subtitle">No recent orders</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Marketing Tools -->
            <div class="admin-card">
                <div class="card-header">
                    <h3 class="card-title">Marketing Tools</h3>
                </div>
                <div class="marketing-tools-grid">
                    <button onclick="showMarketingTool('email-campaigns')" class="tool-card">
                        <div class="tool-icon email">📧</div>
                        <div class="tool-content">
                            <h4 class="tool-title">Email Campaigns</h4>
                            <p class="tool-description">Create and manage email marketing campaigns</p>
                        </div>
                    </button>
                    
                    <button onclick="showMarketingTool('discount-codes')" class="tool-card">
                        <div class="tool-icon discount">🏷️</div>
                        <div class="tool-content">
                            <h4 class="tool-title">Discount Codes</h4>
                            <p class="tool-description">Generate promotional codes for customers</p>
                        </div>
                    </button>
                    
                    <button onclick="showMarketingTool('social-media')" class="tool-card">
                        <div class="tool-icon social">📱</div>
                        <div class="tool-content">
                            <h4 class="tool-title">Social Media</h4>
                            <p class="tool-description">Manage social media integrations</p>
                        </div>
                    </button>
                    
                    <a href="/?page=admin&section=reports" class="tool-card">
                        <div class="tool-icon analytics">📈</div>
                        <div class="tool-content">
                            <h4 class="tool-title">Analytics</h4>
                            <p class="tool-description">View detailed sales and customer analytics</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Marketing Tool Sections -->
    <div id="marketing-tool-sections">
        <!-- Email Campaigns Section -->
        <div id="email-campaigns-section" class="marketing-tool-section" style="display: none;">
            <div class="admin-card">
                <div class="section-header">
                    <h2 class="section-title">Email Campaigns</h2>
                    <button class="btn-primary" onclick="toggleNewCampaignForm()">
                        ➕ New Campaign
                    </button>
                </div>
                
                <!-- New Campaign Form -->
                <div id="new-campaign-form" class="form-container" style="display: none;">
                    <h3 class="form-title">Create New Campaign</h3>
                    <form id="campaign-form" action="process_email_campaign.php" method="post" class="marketing-form">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="id" value="<?= generateId('EC') ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="campaign-name" class="form-label">Campaign Name</label>
                                <input type="text" id="campaign-name" name="name" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="campaign-subject" class="form-label">Email Subject</label>
                                <input type="text" id="campaign-subject" name="subject" class="form-input" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="campaign-content" class="form-label">Email Content</label>
                            <textarea id="campaign-content" name="content" rows="8" class="form-textarea" required></textarea>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="campaign-audience" class="form-label">Target Audience</label>
                                <select id="campaign-audience" name="target_audience" class="form-select">
                                    <option value="all">All Subscribers</option>
                                    <option value="customers">Customers Only</option>
                                    <option value="non-customers">Non-Customers</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="campaign-schedule" class="form-label">Schedule</label>
                                <select id="campaign-schedule" name="schedule_type" class="form-select">
                                    <option value="draft">Save as Draft</option>
                                    <option value="send_now">Send Immediately</option>
                                    <option value="schedule">Schedule for Later</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="schedule-date-container" class="form-group" style="display: none;">
                            <label for="campaign-date" class="form-label">Schedule Date</label>
                            <input type="datetime-local" id="campaign-date" name="scheduled_date" class="form-input">
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="toggleNewCampaignForm()">Cancel</button>
                            <button type="submit" class="btn-primary">Create Campaign</button>
                        </div>
                    </form>
                </div>
                
                <!-- Campaigns List -->
                <div class="campaigns-list">
                    <?php if (!empty($marketingData['emailCampaigns'])): ?>
                        <div class="table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marketingData['emailCampaigns'] as $campaign): ?>
                                    <tr>
                                        <td class="campaign-name"><?= htmlspecialchars($campaign['name'] ?? '') ?></td>
                                        <td class="campaign-subject"><?= htmlspecialchars($campaign['subject'] ?? '') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($campaign['status'] ?? 'draft') ?>">
                                                <?= ucfirst($campaign['status'] ?? 'Draft') ?>
                                            </span>
                                        </td>
                                        <td class="campaign-date"><?= date('M d, Y', strtotime($campaign['created_date'] ?? 'now')) ?></td>
                                        <td>
                                            <div class="flex space-x-2">
                                                <button class="text-green-600 hover:text-green-800" title="Edit Campaign">✏️</button>
                                                <button class="text-red-600 hover:text-red-800" title="Delete Campaign">🗑️</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state">
                            <div class="empty-icon">📧</div>
                            <div class="empty-title">No Email Campaigns</div>
                            <div class="empty-subtitle">Create your first email campaign to get started</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Subscribers Section -->
                <?php if (!empty($marketingData['emailSubscribers'])): ?>
                <div class="subscribers-section">
                    <div class="subscribers-info">
                        <h4 class="subscribers-title">Active Subscribers</h4>
                        <p class="subscribers-count"><?= count($marketingData['emailSubscribers']) ?> subscribers</p>
                    </div>
                    <button class="btn-secondary btn-sm">Manage Subscribers</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Discount Codes Section -->
        <div id="discount-codes-section" class="marketing-tool-section" style="display: none;">
            <div class="admin-card">
                <div class="section-header">
                    <h2 class="section-title">Discount Codes</h2>
                    <button class="btn-primary" onclick="toggleNewDiscountForm()">
                        ➕ New Discount Code
                    </button>
                </div>
                
                <!-- New Discount Form -->
                <div id="new-discount-form" class="form-container" style="display: none;">
                    <h3 class="form-title">Create New Discount Code</h3>
                    <form id="discount-form" action="process_discount_code.php" method="post" class="marketing-form">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="id" value="<?= generateId('DC') ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="discount-code" class="form-label">Discount Code</label>
                                <div class="input-group">
                                    <input type="text" id="discount-code" name="code" class="form-input" required>
                                    <button type="button" class="btn-secondary" onclick="generateDiscountCode()">Generate</button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount-type" class="form-label">Discount Type</label>
                                <select id="discount-type" name="discount_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="percentage">Percentage</option>
                                    <option value="fixed">Fixed Amount</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="discount-value" class="form-label">Discount Value</label>
                                <input type="number" id="discount-value" name="discount_value" class="form-input" 
                                       step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount-usage-limit" class="form-label">Usage Limit</label>
                                <input type="number" id="discount-usage-limit" name="usage_limit" class="form-input" 
                                       min="1" placeholder="Unlimited if blank">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="discount-start-date" class="form-label">Start Date</label>
                                <input type="date" id="discount-start-date" name="start_date" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount-end-date" class="form-label">End Date</label>
                                <input type="date" id="discount-end-date" name="end_date" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="toggleNewDiscountForm()">Cancel</button>
                            <button type="submit" class="btn-primary">Create Discount Code</button>
                        </div>
                    </form>
                </div>
                
                <!-- Discount Codes List -->
                <div class="discount-codes-list">
                    <?php if (!empty($marketingData['discountCodes'])): ?>
                        <div class="table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Usage</th>
                                        <th>Status</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marketingData['discountCodes'] as $code): ?>
                                    <tr>
                                        <td class="discount-code"><?= htmlspecialchars($code['code'] ?? '') ?></td>
                                        <td class="discount-type"><?= ucfirst($code['discount_type'] ?? '') ?></td>
                                        <td class="discount-value">
                                            <?php if (($code['discount_type'] ?? '') === 'percentage'): ?>
                                                <?= number_format($code['discount_value'] ?? 0, 1) ?>%
                                            <?php else: ?>
                                                $<?= number_format($code['discount_value'] ?? 0, 2) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="discount-usage">
                                            <?= ($code['used_count'] ?? 0) ?> / <?= ($code['usage_limit'] ?? '∞') ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($code['status'] ?? 'active') ?>">
                                                <?= ucfirst($code['status'] ?? 'Active') ?>
                                            </span>
                                        </td>
                                        <td class="discount-expires">
                                            <?= $code['end_date'] ? date('M d, Y', strtotime($code['end_date'])) : 'Never' ?>
                                        </td>
                                        <td>
                                            <div class="flex space-x-2">
                                                <button class="text-green-600 hover:text-green-800" title="Edit Code">✏️</button>
                                                <button class="text-red-600 hover:text-red-800" title="Delete Code">🗑️</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state">
                            <div class="empty-icon">🏷️</div>
                            <div class="empty-title">No Discount Codes</div>
                            <div class="empty-subtitle">Create your first discount code to boost sales</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Social Media Section -->
        <div id="social-media-section" class="marketing-tool-section" style="display: none;">
            <div class="admin-card">
                <div class="section-header">
                    <h2 class="section-title">Social Media Management</h2>
                    <button class="btn-primary" onclick="toggleNewPostForm()">
                        ➕ New Post
                    </button>
                </div>
                
                <!-- Social Accounts -->
                <?php if (!empty($marketingData['socialAccounts'])): ?>
                <div class="social-accounts-section">
                    <h3 class="subsection-title">Connected Accounts</h3>
                    <div class="social-accounts-grid">
                        <?php foreach ($marketingData['socialAccounts'] as $account): ?>
                        <div class="social-account-card <?= ($account['status'] ?? '') === 'connected' ? 'connected' : 'disconnected' ?>">
                            <div class="platform-icon platform-<?= strtolower($account['platform'] ?? '') ?>">
                                <?php
                                $platformIcons = [
                                    'facebook' => '📘',
                                    'instagram' => '📷',
                                    'twitter' => '🐦',
                                    'linkedin' => '💼'
                                ];
                                echo $platformIcons[strtolower($account['platform'] ?? '')] ?? '📱';
                                ?>
                            </div>
                            <div class="account-info">
                                <h4 class="platform-name"><?= ucfirst($account['platform'] ?? '') ?></h4>
                                <p class="account-handle">@<?= htmlspecialchars($account['username'] ?? 'Not connected') ?></p>
                                <span class="connection-status">
                                    <?= ucfirst($account['status'] ?? 'Disconnected') ?>
                                </span>
                            </div>
                            <div class="account-actions">
                                <button class="btn-secondary btn-sm">
                                    <?= ($account['status'] ?? '') === 'connected' ? 'Disconnect' : 'Connect' ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Social Posts -->
                <div class="social-posts-section">
                    <h3 class="subsection-title">Recent Posts</h3>
                    <?php if (!empty($marketingData['socialPosts'])): ?>
                        <div class="social-posts-grid">
                            <?php foreach (array_slice($marketingData['socialPosts'], 0, 6) as $post): ?>
                            <div class="social-post-card">
                                <div class="post-header">
                                    <div class="platform-icon platform-<?= strtolower($post['platform'] ?? '') ?>">
                                        <?php
                                        $platformIcons = [
                                            'facebook' => '📘',
                                            'instagram' => '📷',
                                            'twitter' => '🐦'
                                        ];
                                        echo $platformIcons[strtolower($post['platform'] ?? '')] ?? '📱';
                                        ?>
                                    </div>
                                    <div class="post-meta">
                                        <span class="post-platform"><?= ucfirst($post['platform'] ?? '') ?></span>
                                        <span class="post-date"><?= date('M d, Y', strtotime($post['scheduled_date'] ?? 'now')) ?></span>
                                    </div>
                                    <span class="status-badge status-<?= strtolower($post['status'] ?? 'draft') ?>">
                                        <?= ucfirst($post['status'] ?? 'Draft') ?>
                                    </span>
                                </div>
                                <div class="post-content">
                                    <?= nl2br(htmlspecialchars(substr($post['content'] ?? '', 0, 150))) ?>
                                    <?= strlen($post['content'] ?? '') > 150 ? '...' : '' ?>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="text-green-600 hover:text-green-800" title="Edit Post">✏️</button>
                                    <button class="text-red-600 hover:text-red-800" title="Delete Post">🗑️</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state">
                            <div class="empty-icon">📱</div>
                            <div class="empty-title">No Social Posts</div>
                            <div class="empty-subtitle">Create your first social media post</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Setup Notice for Missing Tables -->
    <?php if (!$tableStatus['allTablesExist']): ?>
    <div class="admin-card mt-8">
        <div class="setup-notice">
            <h3 class="setup-title">Marketing Setup Required</h3>
            <p class="setup-description">Some marketing features require database setup. Click below to initialize missing tables.</p>
            <button class="btn-primary" onclick="initializeMarketingTables()">
                🚀 Setup Marketing Tables
            </button>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart Data
const chartData = <?= json_encode($chartData) ?>;

// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: chartData.monthLabels,
                datasets: [{
                    label: 'Sales ($)',
                    data: chartData.salesData,
                    borderColor: 'var(--primary-color, #87ac3a)',
                    backgroundColor: 'rgba(135, 172, 58, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Payment Methods Chart
    const paymentCtx = document.getElementById('paymentMethodChart');
    if (paymentCtx) {
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.paymentMethodLabels,
                datasets: [{
                    data: chartData.paymentMethodCounts,
                    backgroundColor: [
                        '#87ac3a',
                        '#a3cc4a',
                        '#6b8e23',
                        '#9bb83a',
                        '#7ea32d'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});

// Marketing Tool Functions
function showMarketingTool(tool) {
    // Hide all sections
    document.querySelectorAll('.marketing-tool-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show selected section
    const section = document.getElementById(tool + '-section');
    if (section) {
        section.style.display = 'block';
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

function toggleNewCampaignForm() {
    const form = document.getElementById('new-campaign-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleNewDiscountForm() {
    const form = document.getElementById('new-discount-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleNewPostForm() {
    // This would show a social post form if implemented
    alert('Social post creation coming soon!');
}

function generateDiscountCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('discount-code').value = code;
}

function initializeMarketingTables() {
    if (confirm('This will create the necessary database tables for marketing features. Continue?')) {
        // This would trigger table creation
        alert('Marketing table initialization coming soon!');
    }
}

// Schedule type change handler
document.addEventListener('change', function(e) {
    if (e.target.id === 'campaign-schedule') {
        const dateContainer = document.getElementById('schedule-date-container');
        dateContainer.style.display = e.target.value === 'schedule' ? 'block' : 'none';
    }
});
</script>
