<?php
/**
 * Dashboard Metrics API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);

header('Content-Type: application/json');

// Auth check
if (!((strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false))) {
    AuthHelper::requireAdmin();
}

try {
    $db = Database::getInstance();

    // 1. Fetch core metrics
    $total_items = (int) (Database::queryOne('SELECT COUNT(*) as count FROM items')['count'] ?? 0);
    $total_orders = (int) (Database::queryOne('SELECT COUNT(*) as count FROM orders')['count'] ?? 0);
    $total_customers = (int) (Database::queryOne('SELECT COUNT(*) as count FROM users WHERE role != "admin"')['count'] ?? 0);
    $total_revenue = (float) (Database::queryOne('SELECT SUM(total_amount) as revenue FROM orders')['revenue'] ?? 0);

    // 2. Fetch Report Summary Metrics (matching reports_summary.php)
    $since7 = date('Y-m-d', strtotime('-7 days'));
    $since30 = date('Y-m-d', strtotime('-30 days'));

    $totals7 = Database::queryOne('SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_amount),0) AS revenue FROM orders WHERE created_at >= ?', [$since7]);
    $totals30 = Database::queryOne('SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_amount),0) AS revenue FROM orders WHERE created_at >= ?', [$since30]);

    // Payment method breakdown
    $paymentBreakdown = Database::queryAll(
        "SELECT payment_method AS method, COUNT(*) AS cnt
         FROM orders
         WHERE payment_method IS NOT NULL AND payment_method != ''
         GROUP BY payment_method
         ORDER BY cnt DESC, method ASC
         LIMIT 4"
    );

    // 7-day daily sales for sparkline
    $dailyRows = Database::queryAll(
        "SELECT DATE(created_at) AS d, COALESCE(SUM(total_amount),0) AS revenue
         FROM orders
         WHERE created_at >= ?
         GROUP BY DATE(created_at)
         ORDER BY d ASC",
        [$since7]
    );

    // 3. Fetch Top Stock Items (Aligned with inventory_summary.php)
    $topStockItems = Database::queryAll('SELECT name, sku, stock_quantity FROM items ORDER BY stock_quantity DESC LIMIT 3');

    // 4. Fetch Recent Customers
    $recentCustomers = Database::queryAll(
        "SELECT id, username, email, created_at
         FROM users
         WHERE role != 'admin'
         ORDER BY created_at DESC
         LIMIT 5"
    );

    // 5. Fetch Marketing Stats (Aligned with production marketing_tools.php)
    $marketingStats = Database::queryOne('SELECT 
        (SELECT COUNT(*) FROM email_campaigns) as email_campaigns,
        (SELECT COUNT(*) FROM discount_codes WHERE (end_date IS NULL OR end_date >= CURDATE())) as active_discounts,
        (SELECT COUNT(*) FROM social_posts WHERE scheduled_date >= CURDATE()) as scheduled_posts
    ');

    Response::success([
        'total_items' => $total_items,
        'total_orders' => $total_orders,
        'total_customers' => $total_customers,
        'total_revenue' => $total_revenue,
        'top_stock_items' => $topStockItems,
        'recent_customers' => $recentCustomers,
        'reports' => [
            'last_7d' => [
                'revenue' => (float) $totals7['revenue'],
                'orders' => (int) $totals7['orders_count']
            ],
            'last_30d' => [
                'orders' => (int) $totals30['orders_count']
            ],
            'payment_breakdown' => $paymentBreakdown,
            'daily_sales' => $dailyRows
        ],
        'marketing' => [
            'email_campaigns' => (int) ($marketingStats['email_campaigns'] ?? 0),
            'active_discounts' => (int) ($marketingStats['active_discounts'] ?? 0),
            'scheduled_posts' => (int) ($marketingStats['scheduled_posts'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
