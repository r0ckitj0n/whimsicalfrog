<?php
require_once __DIR__ . '/config.php';

try {
    $format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'json';
    $type = isset($_GET['type']) ? (string)$_GET['type'] : '';
    $tf = isset($_GET['timeframe']) ? (int)$_GET['timeframe'] : 7;
    if (!in_array($tf, [7, 30, 90], true)) { $tf = 7; }

    // Microcache (cache JSON payload only) keyed by timeframe; 5 minutes TTL
    $cacheDir = sys_get_temp_dir() . '/wf_cache';
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
    $cacheFile = $cacheDir . '/marketing_overview_' . $tf . '.json';
    $cacheTtl = 300; // seconds
    $payload = null;
    if (is_file($cacheFile) && (time() - @filemtime($cacheFile) < $cacheTtl)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) { $payload = $decoded; }
        }
    }

    if (!$payload) {

    // Build day labels map for sales
    $labels = [];
    $totalsMap = [];
    $start = new DateTime('-' . ($tf - 1) . ' days');
    $end = new DateTime('today');
    $period = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
    foreach ($period as $d) {
        $key = $d->format('Y-m-d');
        $labels[] = $key;
        $totalsMap[$key] = 0.0;
    }

    // Sales by day
    $rows = Database::queryAll("SELECT DATE(`date`) as day, SUM(total) as sum_total FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(`date`) ORDER BY day ASC", [$tf - 1]);
    foreach ($rows as $r) {
        $day = $r['day'] ?? null; $sum = (float)($r['sum_total'] ?? 0);
        if ($day && isset($totalsMap[$day])) { $totalsMap[$day] = $sum; }
    }
    $sales = [ 'labels' => array_values($labels), 'values' => array_values($totalsMap) ];

    // Payment method distribution (same timeframe)
    $payRows = Database::queryAll("SELECT LOWER(COALESCE(paymentMethod, 'other')) as method, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY LOWER(COALESCE(paymentMethod, 'other')) ORDER BY cnt DESC", [$tf]);
    $paymentLabels = []; $paymentValues = [];
    foreach ($payRows as $pr) { $paymentLabels[] = $pr['method']; $paymentValues[] = (int)$pr['cnt']; }
    $payments = [ 'labels' => $paymentLabels, 'values' => $paymentValues ];

    // KPIs (revenue/orders/AOV for timeframe) and customers (timeframe)
    $rowKpi = Database::queryOne("SELECT SUM(total) as rev, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf - 1]);
    $kpiRevenue = (float)($rowKpi['rev'] ?? 0);
    $kpiOrders = (int)($rowKpi['cnt'] ?? 0);
    $kpiAov = $kpiOrders > 0 ? round($kpiRevenue / $kpiOrders, 2) : 0.0;
    $rowCust = Database::queryOne("SELECT COUNT(DISTINCT userId) as cust FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf]);
    $kpiCustomers = (int)($rowCust['cust'] ?? 0);

    // Top categories/products (timeframe)
    $tcRows = Database::queryAll(
        "SELECT COALESCE(i.category, 'Uncategorized') as label, SUM(oi.quantity * oi.price) as revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         LEFT JOIN items i ON i.sku = oi.sku
         WHERE o.`date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY COALESCE(i.category, 'Uncategorized')
         ORDER BY revenue DESC
         LIMIT 5",
        [$tf]
    );
    $topCatLabels = []; $topCatValues = [];
    foreach ($tcRows as $r) { $topCatLabels[] = $r['label']; $topCatValues[] = round((float)($r['revenue'] ?? 0), 2); }

    $tpRows = Database::queryAll(
        "SELECT oi.sku as sku, COALESCE(i.name, oi.sku) as label, SUM(oi.quantity * oi.price) as revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         LEFT JOIN items i ON i.sku = oi.sku
         WHERE o.`date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY oi.sku, label
         ORDER BY revenue DESC
         LIMIT 5",
        [$tf]
    );
    $topProdLabels = []; $topProdValues = [];
    foreach ($tpRows as $r) { $topProdLabels[] = $r['label']; $topProdValues[] = round((float)($r['revenue'] ?? 0), 2); }

    // Status distribution
    $stRows = Database::queryAll("SELECT COALESCE(order_status, 'unknown') as status, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY COALESCE(order_status, 'unknown') ORDER BY cnt DESC", [$tf]);
    $statusLabels = []; $statusValues = [];
    foreach ($stRows as $r) { $statusLabels[] = $r['status']; $statusValues[] = (int)($r['cnt'] ?? 0); }

    // New vs Returning customers (timeframe)
    $newCustomers = 0; $returningCustomers = 0;
    // Distinct customers in window
    $winUsers = [];
    try { $winUsers = Database::queryAll("SELECT DISTINCT userId as uid FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)", [$tf]); } catch (Throwable $e) { $winUsers = []; }
    $uids = array_values(array_filter(array_map(function($r){ return isset($r['uid']) ? $r['uid'] : null; }, $winUsers)));
    if ($uids) {
        // Returning: had an order before start
        $placeholders = implode(',', array_fill(0, count($uids), '?'));
        $params = $uids; $params[] = $tf; // append timeframe param at end for DATE_SUB
        try { $retRows = Database::queryAll("SELECT COUNT(DISTINCT userId) as cnt FROM orders WHERE userId IN ($placeholders) AND `date` < DATE_SUB(CURDATE(), INTERVAL ? DAY)", $params); }
        catch (Throwable $e) { $retRows = [['cnt' => 0]]; }
        $returningCustomers = (int)($retRows[0]['cnt'] ?? 0);
        $newCustomers = max(0, count($uids) - $returningCustomers);
    }

    // Shipping method distribution (same timeframe)
    try { $shipRows = Database::queryAll("SELECT COALESCE(shippingMethod, 'Other') as method, COUNT(*) as cnt FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY COALESCE(shippingMethod, 'Other') ORDER BY cnt DESC", [$tf]); }
    catch (Throwable $e) { $shipRows = []; }
    $shipLabels = []; $shipValues = [];
    foreach ($shipRows as $sr) { $shipLabels[] = $sr['method']; $shipValues[] = (int)($sr['cnt'] ?? 0); }

    // AOV trend (daily within timeframe)
    $aovMap = [];
    foreach ($labels as $dLabel) { $aovMap[$dLabel] = 0.0; }
    try { $aovRows = Database::queryAll("SELECT DATE(`date`) as day, SUM(total)/NULLIF(COUNT(*),0) as aov FROM orders WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(`date`) ORDER BY day ASC", [$tf-1]); }
    catch (Throwable $e) { $aovRows = []; }
    foreach ($aovRows as $ar) { $day = $ar['day'] ?? null; $val = (float)($ar['aov'] ?? 0); if ($day && isset($aovMap[$day])) $aovMap[$day] = round($val,2); }
    $aovTrend = [ 'labels' => array_values($labels), 'values' => array_values($aovMap) ];

    // Attribution: Top Channels (sessions) and Channel Revenue (conversion_value)
    $chLabels = []; $chValues = [];
    $revChLabels = []; $revChValues = [];
    try {
        $chRows = Database::queryAll(
            "SELECT 
                CASE 
                  WHEN COALESCE(utm_source,'') <> '' THEN LOWER(utm_source)
                  WHEN COALESCE(referrer,'') <> '' THEN 
                    CASE 
                      WHEN LOCATE('://', referrer) > 0 THEN 
                        CASE 
                          WHEN LEFT(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,'/',3),'//',-1),4) = 'www.' THEN SUBSTRING(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,'/',3),'//',-1),5)
                          ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,'/',3),'//',-1)
                        END
                      ELSE 
                        CASE 
                          WHEN LEFT(SUBSTRING_INDEX(referrer,'/',1),4) = 'www.' THEN SUBSTRING(SUBSTRING_INDEX(referrer,'/',1),5)
                          ELSE SUBSTRING_INDEX(referrer,'/',1)
                        END
                    END
                  ELSE 'direct'
                END as channel,
                COUNT(*) as cnt
             FROM analytics_sessions
             WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY channel
             ORDER BY cnt DESC
             LIMIT 6", [$tf]
        );
        foreach ($chRows as $r) { $chLabels[] = $r['channel']; $chValues[] = (int)($r['cnt'] ?? 0); }
    } catch (Throwable $e) { /* ignore if table missing */ }

    try {
        $revRows = Database::queryAll(
            "SELECT 
                CASE 
                  WHEN COALESCE(utm_source,'') <> '' THEN LOWER(utm_source)
                  WHEN COALESCE(referrer,'') <> '' THEN 
                    CASE 
                      WHEN LOCATE('://', referrer) > 0 THEN 
                        CASE 
                          WHEN LEFT(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,'/',3),'//',-1),4) = 'www.' THEN SUBSTRING(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,'/',3),'//',-1),5)
                          ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,'/',3),'//',-1)
                        END
                      ELSE 
                        CASE 
                          WHEN LEFT(SUBSTRING_INDEX(referrer,'/',1),4) = 'www.' THEN SUBSTRING(SUBSTRING_INDEX(referrer,'/',1),5)
                          ELSE SUBSTRING_INDEX(referrer,'/',1)
                        END
                    END
                  ELSE 'direct'
                END as channel,
                SUM(conversion_value) as revenue
             FROM analytics_sessions
             WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND converted = 1
             GROUP BY channel
             ORDER BY revenue DESC
             LIMIT 6", [$tf]
        );
        foreach ($revRows as $r) { $revChLabels[] = $r['channel']; $revChValues[] = round((float)($r['revenue'] ?? 0), 2); }
    } catch (Throwable $e) { /* ignore if table missing */ }

    $payload = [
        'success' => true,
        'timeframe' => $tf,
        'sales' => $sales,
        'payments' => $payments,
        'topCategories' => [ 'labels' => $topCatLabels, 'values' => $topCatValues ],
        'topProducts' => [ 'labels' => $topProdLabels, 'values' => $topProdValues ],
        'status' => [ 'labels' => $statusLabels, 'values' => $statusValues ],
        'kpis' => [ 'revenue' => $kpiRevenue, 'orders' => $kpiOrders, 'aov' => $kpiAov, 'customers' => $kpiCustomers ],
        'newReturning' => [ 'labels' => ['New','Returning'], 'values' => [ $newCustomers, $returningCustomers ] ],
        'shipping' => [ 'labels' => $shipLabels, 'values' => $shipValues ],
        'aovTrend' => $aovTrend,
        'channels' => [ 'labels' => $chLabels, 'values' => $chValues ],
        'channelRevenue' => [ 'labels' => $revChLabels, 'values' => $revChValues ],
    ];
    // Write cache best-effort
    @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    // Output selection: JSON (default) or CSV by dataset type
    if ($format === 'csv') {
        $allowed = ['sales','payments','topCategories','topProducts','status','newReturning','shipping','aovTrend','kpis','channels','channelRevenue'];
        if (!in_array($type, $allowed, true)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid csv type']);
            return;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="marketing_overview_' . $type . '_' . $tf . 'd.csv"');
        $out = fopen('php://output', 'w');
        $write = function($row) use ($out) { fputcsv($out, $row); };
        switch ($type) {
            case 'sales':
                $write(['date','total']);
                $ds = $payload['sales'];
                $labels = isset($ds['labels']) && is_array($ds['labels']) ? $ds['labels'] : [];
                $vals = isset($ds['values']) && is_array($ds['values']) ? $ds['values'] : [];
                $n = max(count($labels), count($vals));
                for ($i=0;$i<$n;$i++){ $write([ $labels[$i] ?? '', (string)($vals[$i] ?? 0) ]); }
                break;
            case 'aovTrend':
                $write(['date','aov']);
                $ds = $payload['aovTrend'];
                $labels = isset($ds['labels']) && is_array($ds['labels']) ? $ds['labels'] : [];
                $vals = isset($ds['values']) && is_array($ds['values']) ? $ds['values'] : [];
                $n = max(count($labels), count($vals));
                for ($i=0;$i<$n;$i++){ $write([ $labels[$i] ?? '', (string)($vals[$i] ?? 0) ]); }
                break;
            case 'kpis':
                $write(['metric','value']);
                foreach (($payload['kpis'] ?? []) as $k=>$v) { $write([ (string)$k, (string)$v ]); }
                break;
            default:
                // label,value structure
                $ds = $payload[$type];
                $write(['label','value']);
                $labels = isset($ds['labels']) && is_array($ds['labels']) ? $ds['labels'] : [];
                $vals = isset($ds['values']) && is_array($ds['values']) ? $ds['values'] : [];
                $n = max(count($labels), count($vals));
                for ($i=0;$i<$n;$i++){ $write([ (string)($labels[$i] ?? ''), (string)($vals[$i] ?? 0) ]); }
        }
        fclose($out);
        return;
    }

    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to build overview']);
}
