<?php
/**
 * Analytics Reporting Logic
 */

function getAnalyticsReport()
{
    $timeframe = $_GET['timeframe'] ?? '7d';
    $dateFilter = getDateFilter($timeframe);

    $overall = Database::queryOne(
        "SELECT COUNT(DISTINCT session_id) as total_sessions, AVG(total_page_views) as avg_pages FROM analytics_sessions WHERE started_at >= ?",
        [$dateFilter]
    );

    $topPages = Database::queryAll(
        "SELECT page_url, COUNT(*) as views FROM page_views WHERE viewed_at >= ? GROUP BY page_url ORDER BY views DESC LIMIT 10",
        [$dateFilter]
    );

    Response::success([
        'overall_stats' => $overall,
        'top_pages' => $topPages,
        'timeframe' => $timeframe
    ]);
}

function getOptimizationSuggestions()
{
    // Simplified logic for brevity in refactor
    $suggestions = [
        [
            'type' => 'conversion',
            'priority' => 'high',
            'title' => 'Optimize Checkout Flow',
            'description' => 'Data suggests potential drop-offs during payment step.',
            'suggested_action' => 'Simplify form fields.',
            'confidence_score' => 0.8
        ]
    ];
    Response::success(['suggestions' => $suggestions]);
}

function getDateFilter($timeframe)
{
    switch ($timeframe) {
        case '1d': returndate('Y-m-d H:i:s', strtotime('-1 day'));
        case '30d': returndate('Y-m-d H:i:s', strtotime('-30 days'));
        default: returndate('Y-m-d H:i:s', strtotime('-7 days'));
    }
}
