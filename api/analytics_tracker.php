<?php
/**
 * Analytics Tracker API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/analytics/initializer.php';
require_once __DIR__ . '/../includes/analytics/tracker.php';
require_once __DIR__ . '/../includes/analytics/reporter.php';

try {
    Database::getInstance();
    initializeAnalyticsTables();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'track_visit':
            trackVisit();
            break;
        case 'track_page_view':
            trackPageView();
            break;
        case 'track_interaction':
            // Logic for interaction tracking
            Response::success();
            break;
        case 'get_analytics_report':
            getAnalyticsReport();
            break;
        case 'get_optimization_suggestions':
            getOptimizationSuggestions();
            break;
        case 'track_item_view':
            // Logic for item view tracking
            Response::success();
            break;
        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    error_log("Analytics error: " . $e->getMessage());
    Response::error('Analytics unavailable', ['details' => $e->getMessage()], 500);
}
