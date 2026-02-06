<?php
/**
 * api/marketing_overview.php
 * Marketing Dashboard API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers/MarketingOverviewHelper.php';

try {
    $tf = isset($_GET['timeframe']) ? (int) $_GET['timeframe'] : 7;
    if (!in_array($tf, [7, 30, 90, 365], true))
        $tf = 7;

    $cacheDir = sys_get_temp_dir() . '/wf_cache';
    if (!is_dir($cacheDir))
        @mkdir($cacheDir, 0777, true);
    $cacheFile = "$cacheDir/marketing_overview_$tf.json";

    // Check cache (5 min TTL)
    if (is_file($cacheFile) && (time() - @filemtime($cacheFile) < 300)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if ($cached) {
            Response::json($cached);
            exit;
        }
    }

    // Build day labels
    $labels = [];
    $start = new DateTime('-' . ($tf - 1) . ' days');
    $period = new DatePeriod($start, new DateInterval('P1D'), (new DateTime('today'))->modify('+1 day'));
    foreach ($period as $d)
        $labels[] = $d->format('Y-m-d');

    $payload = [
        'success' => true,
        'timeframe' => $tf,
        'sales' => MarketingOverviewHelper::getSalesData($tf, $labels),
        'kpis' => MarketingOverviewHelper::getKPIs($tf),
        'payment_methods' => MarketingOverviewHelper::getPaymentMethodData($tf),
        'top_categories' => MarketingOverviewHelper::getTopCategoriesData($tf),
        'status' => MarketingOverviewHelper::getOrderStatusData($tf),
        'new_returning' => MarketingOverviewHelper::getNewVsReturningData($tf),
        'shipping_methods' => MarketingOverviewHelper::getShippingMethodData($tf),
        'aov_trend' => MarketingOverviewHelper::getAOVTrendData($tf, $labels),
        '__wf_cache_ts' => time()
    ];

    // More datasets could be added here using the helper...

    @file_put_contents($cacheFile, json_encode($payload));
    Response::json($payload);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
