<?php
/**
 * WhimsicalFrog Marketing Helper
 * Extracted from sections/admin_marketing.php for compliance and modularity.
 */

class MarketingHelper
{
    /**
     * Get marketing suggestions count
     */
    public static function getSuggestionCount(): int
    {
        try {
            $result = Database::queryOne("SELECT COUNT(*) as count FROM marketing_suggestions");
            return (int) ($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Marketing suggestions table not found: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch KPI data for the dashboard
     */
    public static function getKpiData(): array
    {
        $data = [
            'revenue7d' => 0.0,
            'orders7d' => 0,
            'aov7d' => 0.0,
            'customers30d' => 0
        ];

        try {
            $rowKpi = Database::queryOne("SELECT SUM(total_amount) as rev, COUNT(*) as cnt FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
            if ($rowKpi) {
                $data['revenue7d'] = (float) ($rowKpi['rev'] ?? 0);
                $data['orders7d'] = (int) ($rowKpi['cnt'] ?? 0);
                $data['aov7d'] = $data['orders7d'] > 0 ? round($data['revenue7d'] / $data['orders7d'], 2) : 0.0;
            }
            $rowCust = Database::queryOne("SELECT COUNT(DISTINCT user_id) as cust FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            if ($rowCust) {
                $data['customers30d'] = (int) ($rowCust['cust'] ?? 0);
            }
        } catch (Throwable $e) {
        }

        return $data;
    }

    /**
     * Build comprehensive chart data for the marketing dashboard
     */
    public static function getChartData(): array
    {
        try {
            // Labels for last 7 days
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

            // Daily Sales
            $rows = Database::queryAll("SELECT DATE(`created_at`) as day, SUM(total_amount) as sum_total FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(`created_at`) ORDER BY day ASC");
            foreach ($rows as $r) {
                $day = $r['day'] ?? null;
                if ($day && isset($totalsMap[$day]))
                    $totalsMap[$day] = (float) ($r['sum_total'] ?? 0);
            }

            // Payment Methods
            $payRows = Database::queryAll("SELECT LOWER(COALESCE(payment_method, 'other')) as method, COUNT(*) as cnt FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY LOWER(COALESCE(payment_method, 'other')) ORDER BY cnt DESC");
            $paymentLabels = [];
            $paymentValues = [];
            foreach ($payRows as $pr) {
                $paymentLabels[] = $pr['method'];
                $paymentValues[] = (int) $pr['cnt'];
            }

            // Top Categories
            $tcRows = Database::queryAll("SELECT COALESCE(i.category, 'Uncategorized') as label, SUM(oi.quantity * oi.unit_price) as revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN items i ON i.sku = oi.sku WHERE o.`created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY label ORDER BY revenue DESC LIMIT 5");
            $topCatLabels = [];
            $topCatValues = [];
            foreach ($tcRows as $r) {
                $topCatLabels[] = $r['label'];
                $topCatValues[] = round((float) ($r['revenue'] ?? 0), 2);
            }

            // Top Items
            $tpRows = Database::queryAll("SELECT COALESCE(i.name, oi.sku) as label, SUM(oi.quantity * oi.unit_price) as revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN items i ON i.sku = oi.sku WHERE o.`created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY label ORDER BY revenue DESC LIMIT 5");
            $topItemLabels = [];
            $topItemValues = [];
            foreach ($tpRows as $r) {
                $topItemLabels[] = $r['label'];
                $topItemValues[] = round((float) ($r['revenue'] ?? 0), 2);
            }

            // Order Status
            $stRows = Database::queryAll("SELECT COALESCE(status, 'unknown') as status, COUNT(*) as cnt FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status ORDER BY cnt DESC");
            $statusLabels = [];
            $statusValues = [];
            foreach ($stRows as $r) {
                $statusLabels[] = $r['status'];
                $statusValues[] = (int) ($r['cnt'] ?? 0);
            }

            // New vs Returning
            $newCustomers = 0;
            $returningCustomers = 0;
            $winUsers = Database::queryAll("SELECT DISTINCT user_id as uid FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $uids = array_values(array_filter(array_map(fn($r) => $r['uid'] ?? null, $winUsers)));
            if ($uids) {
                $placeholders = implode(',', array_fill(0, count($uids), '?'));
                $retRows = Database::queryAll("SELECT COUNT(DISTINCT user_id) as cnt FROM orders WHERE user_id IN ($placeholders) AND `created_at` < DATE_SUB(CURDATE(), INTERVAL 30 DAY)", $uids);
                $returningCustomers = (int) ($retRows[0]['cnt'] ?? 0);
                $newCustomers = max(0, count($uids) - $returningCustomers);
            }

            // Shipping Methods
            $shipRows = Database::queryAll("SELECT COALESCE(shipping_method, 'Other') as method, COUNT(*) as cnt FROM orders WHERE `created_at` >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY method ORDER BY cnt DESC");
            $shipLabels = [];
            $shipValues = [];
            foreach ($shipRows as $sr) {
                $shipLabels[] = $sr['method'];
                $shipValues[] = (int) $sr['cnt'];
            }

            // Attribution Channels (Best effort)
            $chLabels = [];
            $chValues = [];
            $revChLabels = [];
            $revChValues = [];
            try {
                $chRows = Database::queryAll("SELECT CASE WHEN COALESCE(utm_source,'') <> '' THEN utm_source WHEN COALESCE(referrer,'') <> '' THEN referrer ELSE 'Direct' END as channel, COUNT(*) as cnt FROM analytics_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY channel ORDER BY cnt DESC LIMIT 6");
                foreach ($chRows as $r) {
                    $chLabels[] = $r['channel'];
                    $chValues[] = (int) $r['cnt'];
                }
                $revRows = Database::queryAll("SELECT CASE WHEN COALESCE(utm_source,'') <> '' THEN utm_source WHEN COALESCE(referrer,'') <> '' THEN referrer ELSE 'Direct' END as channel, SUM(conversion_value) as revenue FROM analytics_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND converted = 1 GROUP BY channel ORDER BY revenue DESC LIMIT 6");
                foreach ($revRows as $r) {
                    $revChLabels[] = $r['channel'];
                    $revChValues[] = round((float) ($r['revenue'] ?? 0), 2);
                }
            } catch (Throwable $e) {
            }

            $kpis = self::getKpiData();

            return [
                'sales' => ['labels' => $labels, 'values' => array_values($totalsMap)],
                'payments' => ['labels' => $paymentLabels, 'values' => $paymentValues],
                'topCategories' => ['labels' => $topCatLabels, 'values' => $topCatValues],
                'topItems' => ['labels' => $topItemLabels, 'values' => $topItemValues],
                'status' => ['labels' => $statusLabels, 'values' => $statusValues],
                'kpis' => ['revenue' => $kpis['revenue7d'], 'orders' => $kpis['orders7d'], 'aov' => $kpis['aov7d'], 'customers' => $kpis['customers30d']],
                'newReturning' => ['labels' => ['New', 'Returning'], 'values' => [$newCustomers, $returningCustomers]],
                'shipping' => ['labels' => $shipLabels, 'values' => $shipValues],
                'channels' => ['labels' => $chLabels, 'values' => $chValues],
                'channelRevenue' => ['labels' => $revChLabels, 'values' => $revChValues],
            ];
        } catch (Throwable $e) {
            return ['sales' => ['labels' => [], 'values' => []], 'payments' => ['labels' => [], 'values' => []]];
        }
    }
}
