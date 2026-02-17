<?php
/**
 * Reports Data API
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

@ini_set('display_errors', 0);
header('Content-Type: application/json');

// Auth check (allow localhost for dev)
if (!((strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false))) {
    AuthHelper::requireAdmin();
}

try {
    $db = Database::getInstance();
    $days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
    $sinceDate = date('Y-m-d H:i:s', strtotime("-$days days"));

    // 1. Sales Data (Daily labels, revenue, orders)
    $dailySales = Database::queryAll(
        "SELECT DATE(created_at) as d, SUM(total_amount) as revenue, COUNT(*) as orders
         FROM orders
         WHERE created_at >= ?
         GROUP BY DATE(created_at)
         ORDER BY d ASC",
        [$sinceDate]
    );

    $labels = [];
    $revenue = [];
    $orders = [];

    // Fill in missing days
    for ($i = $days; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));

        $found = false;
        foreach ($dailySales as $row) {
            if ($row['d'] === $date) {
                $revenue[] = (float) $row['revenue'];
                $orders[] = (int) $row['orders'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $revenue[] = 0.0;
            $orders[] = 0;
        }
    }

    $sales = [
        'labels' => $labels,
        'revenue' => $revenue,
        'orders' => $orders
    ];

    // 2. Payment Data
    $paymentRows = Database::queryAll(
        "SELECT payment_method as method, COUNT(*) as count
         FROM orders
         WHERE created_at >= ? AND payment_method IS NOT NULL AND payment_method != ''
         GROUP BY payment_method",
        [$sinceDate]
    );

    $paymentLabels = [];
    $paymentCounts = [];
    foreach ($paymentRows as $row) {
        $paymentLabels[] = $row['method'];
        $paymentCounts[] = (int) $row['count'];
    }

    $payment = [
        'paymentLabels' => $paymentLabels,
        'paymentCounts' => $paymentCounts
    ];

    // 3. Summary
    $summaryRow = Database::queryOne(
        "SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(*) as total_orders,
            COALESCE(AVG(total_amount), 0) as avg_order_value,
            COUNT(DISTINCT user_id) as unique_customers
         FROM orders
         WHERE created_at >= ?",
        [$sinceDate]
    );

    $summary = [
        'total_revenue' => (float) $summaryRow['total_revenue'],
        'total_orders' => (int) $summaryRow['total_orders'],
        'avg_order_value' => (float) $summaryRow['avg_order_value'],
        'unique_customers' => (int) $summaryRow['unique_customers']
    ];

    // 4. Recent Orders
    $recentOrders = Database::queryAll(
        "SELECT o.id, u.username, o.total_amount as total, o.status, o.created_at, o.payment_status
         FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         ORDER BY o.created_at DESC
         LIMIT 10"
    );

    // 5. Inventory Stats
    $totalItems = (int) Database::queryOne("SELECT COUNT(*) as count FROM items")['count'];
    $totalStock = (int) Database::queryOne("SELECT SUM(stock_level) as count FROM item_sizes")['count'];
    $lowStockCount = (int) Database::queryOne("SELECT COUNT(*) as count FROM item_sizes WHERE stock_level <= 5")['count'];

    $inventoryStats = [
        'total_items' => $totalItems,
        'total_stock' => $totalStock,
        'low_stock_count' => $lowStockCount
    ];

    Response::json([
        'success' => true,
        'sales' => $sales,
        'payment' => $payment,
        'summary' => $summary,
        'recentOrders' => $recentOrders,
        'inventoryStats' => $inventoryStats
    ]);

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
