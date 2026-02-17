<?php
/**
 * includes/helpers/MarketingOverviewHelper.php
 * Helper class for generating marketing overview datasets
 */

class MarketingOverviewHelper
{
    public static function getSalesData($tf, $labels)
    {
        $totalsMap = array_fill_keys($labels, 0.0);
        $rows = Database::queryAll("SELECT DATE(created_at) as day, SUM(total_amount) as sum_total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY day ASC", [$tf - 1]);
        foreach ($rows as $r) {
            $day = $r['day'] ?? null;
            if ($day && isset($totalsMap[$day])) {
                $totalsMap[$day] = (float) ($r['sum_total'] ?? 0);
            }
        }
        return [
            'labels' => array_values($labels),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_values($totalsMap),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => '#3b82f6'
                ]
            ]
        ];
    }

    public static function getKPIs($tf)
    {
        $rowKpi = Database::queryOne("SELECT SUM(total_amount) as rev, COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf - 1]);
        $total_revenue = (float) ($rowKpi['rev'] ?? 0);
        $order_count = (int) ($rowKpi['cnt'] ?? 0);
        $average_order_value = $order_count > 0 ? round($total_revenue / $order_count, 2) : 0.0;

        $rowCust = Database::queryOne("SELECT COUNT(DISTINCT user_id) as cust FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf]);
        $total_customers = (int) ($rowCust['cust'] ?? 0);

        // Conversion rate simulation or calculation if sessions table exists
        $conversion_rate = 0.0;
        try {
            $sessions = Database::queryOne("SELECT COUNT(*) as cnt FROM analytics_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf])['cnt'] ?? 0;
            if ($sessions > 0) {
                $conversion_rate = round(($order_count / $sessions) * 100, 2);
            }
        } catch (Throwable $e) {
        }

        return [
            'total_revenue' => $total_revenue,
            'order_count' => $order_count,
            'average_order_value' => $average_order_value,
            'total_customers' => $total_customers,
            'conversion_rate' => $conversion_rate,
            'growth_percentage' => 0.0 // Placeholder for growth calculation
        ];
    }

    public static function getPaymentMethodData($tf)
    {
        $labels = [];
        $values = [];
        try {
            $rows = Database::queryAll("SELECT payment_method, COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY payment_method ORDER BY cnt DESC", [$tf]);
            foreach ($rows as $r) {
                $labels[] = $r['payment_method'] ?: 'Other';
                $values[] = (int) $r['cnt'];
            }
        } catch (Throwable $e) {
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public static function getTopCategoriesData($tf)
    {
        $labels = [];
        $values = [];
        try {
            // Join with items table to get the category name correctly
            $rows = Database::queryAll(
                "SELECT i.category, SUM(oi.unit_price * oi.quantity) as revenue 
                 FROM order_items oi 
                 JOIN orders o ON oi.order_id = o.id 
                 JOIN items i ON oi.sku = i.sku
                 WHERE o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                 GROUP BY i.category 
                 ORDER BY revenue DESC 
                 LIMIT 5",
                [$tf]
            );
            foreach ($rows as $r) {
                $labels[] = $r['category'] ?: 'Uncategorized';
                $values[] = (float) $r['revenue'];
            }
        } catch (Throwable $e) {
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public static function getOrderStatusData($tf)
    {
        $labels = [];
        $values = [];
        try {
            $rows = Database::queryAll("SELECT status, COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY status", [$tf]);
            foreach ($rows as $r) {
                $labels[] = ucfirst($r['status']);
                $values[] = (int) $r['cnt'];
            }
        } catch (Throwable $e) {
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public static function getNewVsReturningData($tf)
    {
        $labels = ['New', 'Returning'];
        $values = [0, 0];
        try {
            $total_orders = Database::queryOne("SELECT COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf])['cnt'];
            $returningCount = Database::queryOne("SELECT COUNT(*) as cnt FROM (SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) > 1 AND MAX(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)) as r", [$tf])['cnt'];
            $values = [(int) ($total_orders - $returningCount), (int) $returningCount];
        } catch (Throwable $e) {
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public static function getShippingMethodData($tf)
    {
        $labels = [];
        $values = [];
        try {
            $rows = Database::queryAll("SELECT shipping_method, COUNT(*) as cnt FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY shipping_method", [$tf]);
            foreach ($rows as $r) {
                $labels[] = $r['shipping_method'] ?: 'Standard';
                $values[] = (int) $r['cnt'];
            }
        } catch (Throwable $e) {
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public static function getAOVTrendData($tf, $dayLabels)
    {
        $values = array_fill_keys($dayLabels, 0.0);
        try {
            $rows = Database::queryAll("SELECT DATE(created_at) as day, AVG(total_amount) as avg_total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)", [$tf]);
            foreach ($rows as $r) {
                if (isset($values[$r['day']]))
                    $values[$r['day']] = round((float) $r['avg_total'], 2);
            }
        } catch (Throwable $e) {
        }
        return ['labels' => array_values($dayLabels), 'values' => array_values($values)];
    }
}
