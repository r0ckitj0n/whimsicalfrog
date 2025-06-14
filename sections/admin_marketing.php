<?php
// Include database configuration
require_once 'api/config.php'; // Changed from '../api/config.php' to match other section files

// ---Date range---
$marketingStartInput = $_GET['start_date'] ?? '';
$marketingEndInput   = $_GET['end_date']   ?? '';

$startParam = $marketingStartInput === '' ? '1900-01-01' : $marketingStartInput;
$endParam   = $marketingEndInput   === '' ? '2100-12-31' : $marketingEndInput;

// Initialize all variables to prevent undefined variable errors
$customerCount = 0;
$orderCount = 0;
$totalSales = 0;
$productCount = 0;
$recentOrders = [];
$monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"]; // Default labels
$salesData = [0, 0, 0, 0, 0, 0]; // Default data
$topProducts = [];
$paymentsReceived = 0;
$paymentsPending = 0;
$paymentMethodLabels = [];
$paymentMethodCounts = [];

$emailCampaignsExist = false;
$discountCodesExist = false;
$socialAccountsExist = false; // Used for social_accounts and social_posts tables

$emailCampaigns = [];
$emailSubscribers = [];
$discountCodes = [];
$socialAccounts = [];
$socialPosts = [];

$allMarketingTablesExist = false; // Flag to hide setup button

// Connect to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get customer count
    $customerStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Customer' OR roleType = 'Customer'");
    $customerCount = $customerStmt->fetchColumn() ?: 0;
    
    // Check if orders table exists
    $orderTableExists = false;
    $stmtOrderCheck = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($stmtOrderCheck->rowCount() > 0) {
        $orderTableExists = true;
        
        // Get orders count within selected date range
        $orderRangeStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(date) BETWEEN :start AND :end");
        $orderRangeStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
        $orderCount = $orderRangeStmt->fetchColumn() ?: 0;
        
        // Get total sales within selected date range (column is `total` in orders table)
        $salesRangeStmt = $pdo->prepare("SELECT SUM(total) FROM orders WHERE DATE(date) BETWEEN :start AND :end");
        $salesRangeStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
        $totalSales = $salesRangeStmt->fetchColumn() ?: 0;
        
        // Get recent orders within selected range
        $recentOrdersStmt = $pdo->prepare("SELECT o.*, u.username, u.email 
                                        FROM orders o 
                                        LEFT JOIN users u ON o.userId = u.id 
                                        WHERE DATE(o.date) BETWEEN :start AND :end 
                                        ORDER BY o.date DESC 
                                        LIMIT 5");
        $recentOrdersStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
        $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Get monthly sales data within selected range
        $monthlySalesStmt = $pdo->prepare("SELECT DATE_FORMAT(date, '%Y-%m-01') as month_start, SUM(total) as total 
                                        FROM orders 
                                        WHERE DATE(date) BETWEEN :start AND :end 
                                        GROUP BY month_start 
                                        ORDER BY month_start");
        $monthlySalesStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
        $monthlySalesData = $monthlySalesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format monthly data for chart
        if (!empty($monthlySalesData)) {
            $monthLabels = []; // Reset default
            $salesData = [];   // Reset default
            foreach ($monthlySalesData as $data) {
                $monthName = date("M", strtotime($data['month_start']));
                $monthLabels[] = $monthName;
                $salesData[] = $data['total'];
            }
        }
        
        // Get top products
        $topProductsStmt = $pdo->prepare("SELECT p.name, SUM(oi.quantity) as units \n                                        FROM order_items oi\n                                        JOIN orders o ON oi.orderId COLLATE utf8mb4_unicode_ci = o.id COLLATE utf8mb4_unicode_ci\n                                        JOIN products p ON oi.productId COLLATE utf8mb4_unicode_ci = p.id COLLATE utf8mb4_unicode_ci\n                                        WHERE DATE(o.date) BETWEEN :start AND :end\n                                        GROUP BY oi.productId, p.name\n                                        ORDER BY units DESC\n                                        LIMIT 5");
        $topProductsStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
        $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    // Total units sold in selected range
    $unitsStmt = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity), COUNT(*)) \n                                FROM order_items oi \n                                JOIN orders o ON oi.orderId COLLATE utf8mb4_unicode_ci = o.id COLLATE utf8mb4_unicode_ci \n                                WHERE DATE(o.date) BETWEEN :start AND :end");
    $unitsStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
    $productCount = $unitsStmt->fetchColumn() ?: 0;
    
    // Check for email_campaigns table
    $stmtEmailCheck = $pdo->query("SHOW TABLES LIKE 'email_campaigns'");
    if ($stmtEmailCheck->rowCount() > 0) {
        $emailCampaignsExist = true;
        $campaignsStmt = $pdo->query("SELECT * FROM email_campaigns ORDER BY created_date DESC");
        $emailCampaigns = $campaignsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $stmtSubscribersCheck = $pdo->query("SHOW TABLES LIKE 'email_subscribers'");
        if($stmtSubscribersCheck->rowCount() > 0) {
            $subscribersStmt = $pdo->query("SELECT * FROM email_subscribers WHERE status = 'active'");
            $emailSubscribers = $subscribersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
    
    // Check for discount_codes table
    $stmtDiscountCheck = $pdo->query("SHOW TABLES LIKE 'discount_codes'");
    if ($stmtDiscountCheck->rowCount() > 0) {
        $discountCodesExist = true;
        $codesStmt = $pdo->query("SELECT * FROM discount_codes ORDER BY start_date DESC");
        $discountCodes = $codesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    // Check for social_accounts and social_posts tables
    $stmtSocialAccountsCheck = $pdo->query("SHOW TABLES LIKE 'social_accounts'");
    $stmtSocialPostsCheck = $pdo->query("SHOW TABLES LIKE 'social_posts'");
    if ($stmtSocialAccountsCheck->rowCount() > 0 && $stmtSocialPostsCheck->rowCount() > 0) {
        $socialAccountsExist = true; // This flag controls display of social media section
        
        $accountsStmt = $pdo->query("SELECT * FROM social_accounts");
        $socialAccounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $postsStmt = $pdo->query("SELECT * FROM social_posts ORDER BY scheduled_date DESC");
        $socialPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Check if all marketing tables exist to hide the setup button
    if ($emailCampaignsExist && $discountCodesExist && $socialAccountsExist) {
        $allMarketingTablesExist = true;
    }
    
    // Get payment status counts within selected range
    $paymentStatusStmt = $pdo->prepare("SELECT paymentStatus, COUNT(*) as cnt \n                                            FROM orders \n                                            WHERE DATE(date) BETWEEN :start AND :end \n                                            GROUP BY paymentStatus");
    $paymentStatusStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
    $paymentStatusCounts = $paymentStatusStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $paymentsReceived = $paymentStatusCounts['Received'] ?? 0;
    $paymentsPending  = $paymentStatusCounts['Pending']  ?? 0;

    // Get payment method distribution within selected range
    $paymentMethodStmt = $pdo->prepare("SELECT paymentMethod, COUNT(*) as cnt \n                                            FROM orders \n                                            WHERE DATE(date) BETWEEN :start AND :end \n                                            GROUP BY paymentMethod");
    $paymentMethodStmt->execute([':start'=>$startParam, ':end'=>$endParam]);
    $paymentMethodData = $paymentMethodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $paymentMethodLabels = array_column($paymentMethodData, 'paymentMethod');
    $paymentMethodCounts = array_map('intval', array_column($paymentMethodData, 'cnt'));

    // Default empty arrays if no payment methods
    if(empty($paymentMethodLabels)) { $paymentMethodLabels = ['None']; $paymentMethodCounts = [0]; }
    
} catch (PDOException $e) {
    // Log error, don't display to user directly for security
    error_log("Marketing Page Database Error: " . $e->getMessage());
    // Variables will retain their initialized default values (0, empty arrays, false)
}

// Function to generate unique IDs for new items
function generateId($prefix, $length = 3) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}
?>

<div class="admin-section-header flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
    <h2 class="text-2xl font-bold" style="color:#87ac3a !important;">Marketing Dashboard <span class="text-base font-medium ml-2" style="color:#87ac3a !important;">Performance (<?php echo htmlspecialchars($marketingStartInput ?: 'All'); ?> – <?php echo htmlspecialchars($marketingEndInput ?: 'All'); ?>)</span></h2>
    <form class="flex items-center gap-2" method="get" action="">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="marketing">
        <label class="text-sm font-medium" style="color:#87ac3a !important;" for="mFrom">From:</label>
        <input type="date" id="mFrom" name="start_date" value="<?php echo htmlspecialchars($marketingStartInput); ?>" class="border rounded p-1">
        <label class="text-sm font-medium" style="color:#87ac3a !important;" for="mTo">To:</label>
        <input type="date" id="mTo" name="end_date" value="<?php echo htmlspecialchars($marketingEndInput); ?>" class="border rounded p-1">
        <button type="submit" class="px-3 py-1 rounded bg-[#87ac3a] text-white hover:bg-[#a3cc4a] transition">Apply</button>
    </form>
</div>

<div class="admin-content">
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Customers</h3>
                <p class="stat-value"><?php echo $customerCount; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-info">
                <h3>Total Orders</h3>
                <p class="stat-value"><?php echo $orderCount; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3>Total Sales</h3>
                <p class="stat-value">$<?php echo number_format($totalSales, 2); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <h3>Products Sold</h3>
                <p class="stat-value"><?php echo $productCount; ?></p>
            </div>
        </div>

        <!-- Payments Received -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-check-alt"></i>
            </div>
            <div class="stat-info">
                <h3>Payments Received</h3>
                <p class="stat-value"><?php echo $paymentsReceived; ?></p>
            </div>
        </div>

        <!-- Pending Payments -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Payments</h3>
                <p class="stat-value"><?php echo $paymentsPending; ?></p>
            </div>
        </div>
    </div>
    
    <div class="dashboard-row">
        <div class="dashboard-column">
            <div class="dashboard-card">
                <h3>Sales Overview</h3>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h3>Payment Methods</h3>
                <div class="chart-container" style="height:300px;">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h3>Top Products</h3>
                <ul class="top-products-list">
                    <?php if (!empty($topProducts)): ?>
                        <?php foreach ($topProducts as $product): ?>
                            <li>
                                <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                <span class="product-orders"><?php echo $product['units']; ?> units</span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="no-data">No product data available</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="dashboard-column">
            <div class="dashboard-card">
                <h3>Recent Orders</h3>
                <div class="recent-orders">
                    <?php if (!empty($recentOrders)): ?>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['username'] ?? $order['email'] ?? 'Unknown'); ?></td>
                                        <td>$<?php echo number_format($order['total'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">No recent orders</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h3>Marketing Tools</h3>
                <div class="marketing-tools">
                    <a href="javascript:void(0)" onclick="showMarketingTool('email-campaigns')" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-envelope"></i></div>
                        <div class="tool-info">
                            <h4>Email Campaigns</h4>
                            <p>Create and manage email marketing campaigns</p>
                        </div>
                    </a>
                    <a href="javascript:void(0)" onclick="showMarketingTool('discount-codes')" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-tag"></i></div>
                        <div class="tool-info">
                            <h4>Discount Codes</h4>
                            <p>Generate promotional codes for customers</p>
                        </div>
                    </a>
                    <a href="javascript:void(0)" onclick="showMarketingTool('social-media')" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-share-alt"></i></div>
                        <div class="tool-info">
                            <h4>Social Media</h4>
                            <p>Manage social media integrations</p>
                        </div>
                    </a>
                    <a href="/?page=admin&section=reports" class="tool-card">
                        <div class="tool-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="tool-info">
                            <h4>Analytics</h4>
                            <p>View detailed sales and customer analytics</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Marketing Tool Content Sections -->
    <div id="marketing-tool-sections">
        <!-- Email Campaigns Section -->
        <div id="email-campaigns-section" class="marketing-tool-section">
            <div class="section-header">
                <h2>Email Campaigns</h2>
                <button class="button primary" onclick="toggleNewCampaignForm()">
                    <i class="fas fa-plus"></i> New Campaign
                </button>
            </div>
            
            <!-- New Campaign Form -->
            <div id="new-campaign-form" class="form-container" style="display: none;">
                <h3>Create New Campaign</h3>
                <form id="campaign-form" action="process_email_campaign.php" method="post">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="<?php echo generateId('EC'); ?>">
                    
                    <div class="form-group">
                        <label for="campaign-name">Campaign Name</label>
                        <input type="text" id="campaign-name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="campaign-subject">Email Subject</label>
                        <input type="text" id="campaign-subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="campaign-content">Email Content</label>
                        <textarea id="campaign-content" name="content" rows="10" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="campaign-audience">Target Audience</label>
                        <select id="campaign-audience" name="target_audience">
                            <option value="all">All Subscribers</option>
                            <option value="customers">Customers Only</option>
                            <option value="non-customers">Non-Customers</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="campaign-status">Status</label>
                        <select id="campaign-status" name="status">
                            <option value="draft">Draft</option>
                            <option value="scheduled">Scheduled</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="scheduled-date-group" style="display: none;">
                        <label for="campaign-date">Scheduled Date</label>
                        <input type="datetime-local" id="campaign-date" name="sent_date">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="button secondary" onclick="toggleNewCampaignForm()">Cancel</button>
                        <button type="submit" class="button primary">Save Campaign</button>
                    </div>
                </form>
            </div>
            
            <!-- Email Campaigns List -->
            <div class="data-table-container">
                <?php if ($emailCampaignsExist): ?>
                    <?php if (empty($emailCampaigns)): ?>
                        <p class="no-data">No email campaigns found. Create your first campaign!</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Sent/Scheduled</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emailCampaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                        <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($campaign['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($campaign['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($campaign['created_date'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($campaign['sent_date']) {
                                                echo date('M d, Y', strtotime($campaign['sent_date']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="actions">
                                            <button class="icon-button" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($campaign['status'] == 'draft'): ?>
                                                <button class="icon-button" title="Send">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="icon-button delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php elseif (!$allMarketingTablesExist): // Show setup button only if not all tables exist ?>
                    <div class="setup-notice">
                        <p>Email campaign features require database setup.</p>
                        <a href="setup_marketing_tables.php" class="button primary">Setup Marketing Tables</a>
                    </div>
                <?php else: // Tables for this specific feature might be missing, but others exist ?>
                     <p class="no-data">Email campaigns table not found. Please ensure marketing tables are fully set up.</p>
                <?php endif; ?>
            </div>
            
            <!-- Email Subscribers -->
            <?php if ($emailCampaignsExist && !empty($emailSubscribers)): ?>
                <div class="subscribers-section">
                    <h3>Active Subscribers</h3>
                    <p>You have <strong><?php echo count($emailSubscribers); ?></strong> active subscribers.</p>
                    <button class="button secondary">
                        <i class="fas fa-download"></i> Export Subscribers
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Discount Codes Section -->
        <div id="discount-codes-section" class="marketing-tool-section">
            <div class="section-header">
                <h2>Discount Codes</h2>
                <button class="button primary" onclick="toggleNewDiscountForm()">
                    <i class="fas fa-plus"></i> New Discount
                </button>
            </div>
            
            <!-- New Discount Form -->
            <div id="new-discount-form" class="form-container" style="display: none;">
                <h3>Create New Discount Code</h3>
                <form id="discount-form" action="process_discount_code.php" method="post">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="<?php echo generateId('DC'); ?>">
                    
                    <div class="form-group">
                        <label for="discount-code">Discount Code</label>
                        <input type="text" id="discount-code" name="code" required>
                        <button type="button" class="button secondary small" onclick="generateRandomCode()">Generate Random</button>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="discount-type">Discount Type</label>
                            <select id="discount-type" name="type">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="discount-value">Value</label>
                            <input type="number" id="discount-value" name="value" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="min-order">Minimum Order Amount ($)</label>
                        <input type="number" id="min-order" name="min_order_amount" min="0" step="0.01" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="max-uses">Maximum Uses (0 for unlimited)</label>
                        <input type="number" id="max-uses" name="max_uses" min="0" value="0">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start-date">Start Date</label>
                            <input type="date" id="start-date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end-date">End Date</label>
                            <input type="date" id="end-date" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="discount-status">Status</label>
                        <select id="discount-status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="button secondary" onclick="toggleNewDiscountForm()">Cancel</button>
                        <button type="submit" class="button primary">Save Discount</button>
                    </div>
                </form>
            </div>
            
            <!-- Discount Codes List -->
            <div class="data-table-container">
                <?php if ($discountCodesExist): ?>
                    <?php if (empty($discountCodes)): ?>
                        <p class="no-data">No discount codes found. Create your first discount code!</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Uses</th>
                                    <th>Valid Period</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($discountCodes as $code): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($code['code']); ?></strong></td>
                                        <td>
                                            <?php echo $code['type'] === 'percentage' ? 'Percentage' : 'Fixed'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($code['type'] === 'percentage') {
                                                echo htmlspecialchars($code['value']) . '%';
                                            } else {
                                                echo '$' . number_format($code['value'], 2);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($code['max_uses'] > 0) {
                                                echo htmlspecialchars($code['current_uses']) . '/' . htmlspecialchars($code['max_uses']);
                                            } else {
                                                echo htmlspecialchars($code['current_uses']) . '/∞';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo date('M d', strtotime($code['start_date'])) . ' - ' . 
                                                 date('M d, Y', strtotime($code['end_date']));
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($code['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($code['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <button class="icon-button" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="icon-button delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php elseif (!$allMarketingTablesExist): ?>
                    <div class="setup-notice">
                        <p>Discount code features require database setup.</p>
                        <a href="setup_marketing_tables.php" class="button primary">Setup Marketing Tables</a>
                    </div>
                <?php else: ?>
                    <p class="no-data">Discount codes table not found. Please ensure marketing tables are fully set up.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Social Media Section -->
        <div id="social-media-section" class="marketing-tool-section">
            <div class="section-header">
                <h2>Social Media</h2>
                <button class="button primary" onclick="toggleNewPostForm()">
                    <i class="fas fa-plus"></i> New Post
                </button>
            </div>
            
            <!-- Social Accounts -->
            <div class="social-accounts">
                <h3>Connected Accounts</h3>
                
                <?php if ($socialAccountsExist): ?>
                    <div class="accounts-list">
                        <?php if (empty($socialAccounts)): ?>
                            <p class="no-data">No social accounts connected.</p>
                        <?php else: ?>
                            <?php foreach ($socialAccounts as $account): ?>
                                <div class="social-account-card <?php echo $account['connected'] ? 'connected' : 'disconnected'; ?>">
                                    <div class="platform-icon">
                                        <i class="fab fa-<?php echo strtolower(htmlspecialchars($account['platform'])); ?>"></i>
                                    </div>
                                    <div class="account-info">
                                        <h4><?php echo htmlspecialchars($account['account_name']); ?></h4>
                                        <p><?php echo ucfirst(htmlspecialchars($account['platform'])); ?></p>
                                        <span class="connection-status">
                                            <?php echo $account['connected'] ? 'Connected' : 'Disconnected'; ?>
                                        </span>
                                    </div>
                                    <div class="account-actions">
                                        <?php if ($account['connected']): ?>
                                            <button class="button small secondary">Disconnect</button>
                                        <?php else: ?>
                                            <button class="button small primary">Connect</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <button class="button secondary add-account-button">
                            <i class="fas fa-plus"></i> Add Account
                        </button>
                    </div>
                <?php elseif (!$allMarketingTablesExist): ?>
                    <div class="setup-notice">
                        <p>Social media features require database setup.</p>
                        <a href="setup_marketing_tables.php" class="button primary">Setup Marketing Tables</a>
                    </div>
                <?php else: ?>
                     <p class="no-data">Social media tables not found. Please ensure marketing tables are fully set up.</p>
                <?php endif; ?>
            </div>
            
            <!-- New Post Form -->
            <?php if ($socialAccountsExist): ?>
                <div id="new-post-form" class="form-container" style="display: none;">
                    <h3>Create New Social Post</h3>
                    <form id="social-post-form" action="process_social_post.php" method="post">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="id" value="<?php echo generateId('SP'); ?>">
                        
                        <div class="form-group">
                            <label for="post-platform">Platform</label>
                            <select id="post-platform" name="platform" required>
                                <?php foreach ($socialAccounts as $account): ?>
                                    <?php if ($account['connected']): ?>
                                        <option value="<?php echo htmlspecialchars($account['platform']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($account['platform'])) . ' - ' . htmlspecialchars($account['account_name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="post-content">Post Content</label>
                            <textarea id="post-content" name="content" rows="4" required></textarea>
                            <div class="character-counter">
                                <span id="char-count">0</span>/280 characters
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="post-image">Image URL (optional)</label>
                            <input type="text" id="post-image" name="image_url">
                        </div>
                        
                        <div class="form-group">
                            <label for="post-date">Schedule Date</label>
                            <input type="datetime-local" id="post-date" name="scheduled_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="post-status">Status</label>
                            <select id="post-status" name="status">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="button secondary" onclick="toggleNewPostForm()">Cancel</button>
                            <button type="submit" class="button primary">Save Post</button>
                        </div>
                    </form>
                </div>
                
                <!-- Social Posts List -->
                <div class="social-posts">
                    <h3>Scheduled Posts</h3>
                    
                    <?php if (empty($socialPosts)): ?>
                        <p class="no-data">No social posts scheduled. Create your first post!</p>
                    <?php else: ?>
                        <div class="posts-list">
                            <?php foreach ($socialPosts as $post): ?>
                                <div class="social-post-card">
                                    <div class="post-header">
                                        <div class="platform-icon">
                                            <i class="fab fa-<?php echo strtolower(htmlspecialchars($post['platform'])); ?>"></i>
                                        </div>
                                        <div class="post-meta">
                                            <span class="post-platform"><?php echo ucfirst(htmlspecialchars($post['platform'])); ?></span>
                                            <span class="post-date">
                                                <?php 
                                                if ($post['status'] === 'posted') {
                                                    echo 'Posted: ' . date('M d, Y', strtotime($post['posted_date']));
                                                } else {
                                                    echo 'Scheduled: ' . date('M d, Y', strtotime($post['scheduled_date']));
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="post-status">
                                            <span class="status-badge status-<?php echo htmlspecialchars($post['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($post['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="post-content">
                                        <?php echo htmlspecialchars($post['content']); ?>
                                    </div>
                                    <?php if ($post['image_url']): ?>
                                        <div class="post-image">
                                            <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post image">
                                        </div>
                                    <?php endif; ?>
                                    <div class="post-actions">
                                        <?php if ($post['status'] !== 'posted'): ?>
                                            <button class="button small secondary">Edit</button>
                                            <button class="button small primary">Post Now</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales Chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Monthly Sales',
                data: <?php echo json_encode($salesData); ?>,
                backgroundColor: 'rgba(135, 172, 58, 0.2)',
                borderColor: '#87ac3a',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: '#87ac3a'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Payment Method Doughnut Chart
    const pCtx = document.getElementById('paymentMethodChart').getContext('2d');
    const paymentMethodChart = new Chart(pCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($paymentMethodLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($paymentMethodCounts); ?>,
                backgroundColor: [
                    '#87ac3a', '#4B5563', '#10B981', '#F59E0B', '#EF4444', '#6366F1'
                ],
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
    
    // Hide all marketing tool sections initially
    document.querySelectorAll('.marketing-tool-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Set current date as default for date inputs
    const today = new Date().toISOString().split('T')[0];
    if (document.getElementById('start-date')) { // For discount codes
        document.getElementById('start-date').value = today;
    }
    if (document.getElementById('end-date')) { // For discount codes
        const nextMonth = new Date();
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        document.getElementById('end-date').value = nextMonth.toISOString().split('T')[0];
    }
    
    // Campaign status change handler
    const campaignStatus = document.getElementById('campaign-status');
    if (campaignStatus) {
        campaignStatus.addEventListener('change', function() {
            const scheduledDateGroup = document.getElementById('scheduled-date-group');
            if (this.value === 'scheduled') {
                scheduledDateGroup.style.display = 'block';
            } else {
                scheduledDateGroup.style.display = 'none';
            }
        });
    }
    
    // Character counter for social posts
    const postContent = document.getElementById('post-content');
    const charCount = document.getElementById('char-count');
    if (postContent && charCount) {
        postContent.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            if (count > 280) {
                charCount.style.color = 'red';
            } else {
                charCount.style.color = '';
            }
        });
    }
});

// Show marketing tool section
function showMarketingTool(toolType) {
    // Hide all sections first
    document.querySelectorAll('.marketing-tool-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show the selected section
    const sectionId = toolType + '-section';
    const section = document.getElementById(sectionId);
    if (section) {
        section.style.display = 'block';
        
        // Scroll to the section
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

// Toggle new campaign form
function toggleNewCampaignForm() {
    const form = document.getElementById('new-campaign-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Toggle new discount form
function toggleNewDiscountForm() {
    const form = document.getElementById('new-discount-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Toggle new post form
function toggleNewPostForm() {
    const form = document.getElementById('new-post-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Generate random discount code
function generateRandomCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('discount-code').value = code;
}
</script>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
}

.stat-icon {
    font-size: 2rem;
    color: #87ac3a;
    margin-right: 15px;
}

.stat-info h3 {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 5px 0 0;
    color: #333;
}

.dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.dashboard-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.dashboard-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.dashboard-card h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.chart-container {
    height: 250px;
    position: relative;
}

.top-products-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.top-products-list li {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.top-products-list li:last-child {
    border-bottom: none;
}

.product-name {
    font-weight: 500;
}

.product-orders {
    color: #87ac3a;
    font-weight: 500;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
}

.mini-table th, .mini-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.mini-table th {
    font-weight: 500;
    color: #666;
}

.marketing-tools {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.tool-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.tool-card:hover {
    background-color: #f0f0f0;
    transform: translateY(-2px);
}

.tool-icon {
    width: 40px;
    height: 40px;
    background-color: #87ac3a;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.tool-info h4 {
    margin: 0;
    font-size: 1rem;
}

.tool-info p {
    margin: 5px 0 0;
    font-size: 0.8rem;
    color: #666;
}

.no-data {
    color: #999;
    font-style: italic;
    text-align: center;
    padding: 20px 0;
}

/* Marketing Tool Sections */
.marketing-tool-section {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    margin: 0;
    color: #333;
}

.button {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: background-color 0.3s;
}

.button.primary {
    background-color: #87ac3a;
    color: white;
}

.button.primary:hover {
    background-color: #a3cc4a;
}

.button.secondary {
    background-color: #f0f0f0;
    color: #333;
}

.button.secondary:hover {
    background-color: #e0e0e0;
}

.button.small {
    padding: 4px 8px;
    font-size: 12px;
}

/* Form Styles */
.form-container {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-container h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

/* Data Table */
.data-table-container {
    margin-bottom: 20px;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background-color: #f9f9f9;
    font-weight: 500;
    color: #555;
}

.data-table tr:hover {
    background-color: #f9f9f9;
}

.actions {
    white-space: nowrap;
}

.icon-button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: #666;
    transition: color 0.3s;
}

.icon-button:hover {
    color: #333;
}

.icon-button.delete:hover {
    color: #f44336;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-draft {
    background-color: #e0e0e0;
    color: #555;
}

.status-scheduled {
    background-color: #bbdefb;
    color: #1976d2;
}

.status-sent, .status-posted {
    background-color: #c8e6c9;
    color: #388e3c;
}

.status-active {
    background-color: #c8e6c9;
    color: #388e3c;
}

.status-inactive {
    background-color: #ffcdd2;
    color: #d32f2f;
}

/* Setup Notice */
.setup-notice {
    text-align: center;
    padding: 30px;
    background-color: #f9f9f9;
    border-radius: 8px;
}

.setup-notice p {
    margin-bottom: 15px;
    color: #666;
}

/* Social Media Styles */
.social-accounts {
    margin-bottom: 20px;
}

.accounts-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.social-account-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    position: relative;
}

.social-account-card.connected {
    border-left: 4px solid #4caf50;
}

.social-account-card.disconnected {
    border-left: 4px solid #f44336;
}

.platform-icon {
    font-size: 24px;
    margin-right: 15px;
}

.platform-icon .fa-facebook {
    color: #1877f2;
}

.platform-icon .fa-instagram {
    color: #c13584;
}

.platform-icon .fa-twitter {
    color: #1da1f2;
}

.account-info h4 {
    margin: 0;
    font-size: 16px;
}

.account-info p {
    margin: 5px 0 0;
    color: #666;
    font-size: 14px;
}

.connection-status {
    font-size: 12px;
    color: #666;
}

.account-actions {
    margin-left: auto;
}

.add-account-button {
    width: 100%;
    text-align: center;
    padding: 15px;
    margin-top: 10px;
}

/* Social Posts */
.posts-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.social-post-card {
    background-color: #f9f9f9;
    border-radius: 8px;
    overflow: hidden;
}

.post-header {
    display: flex;
    align-items: center;
    padding: 15px;
    background-color: #f0f0f0;
}

.post-meta {
    flex: 1;
    margin-left: 10px;
}

.post-platform {
    font-weight: 500;
    display: block;
}

.post-date {
    font-size: 12px;
    color: #666;
}

.post-content {
    padding: 15px;
    white-space: pre-wrap;
}

.post-image img {
    width: 100%;
    height: auto;
    display: block;
}

.post-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px;
    background-color: #f0f0f0;
}

/* Character Counter */
.character-counter {
    text-align: right;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

/* Subscribers Section */
.subscribers-section {
    margin-top: 20px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.subscribers-section p {
    margin: 0;
}

@media (max-width: 992px) {
    .dashboard-row {
        grid-template-columns: 1fr;
    }
    
    .marketing-tools {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
