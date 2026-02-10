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
        case 'track_cart_action':
            // Keep analytics non-blocking; cart actions may be emitted from older/newer clients.
            Response::success();
            break;
        case 'track_conversion':
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
    $message = $e->getMessage();
    error_log("Analytics error: " . $message);

    // Analytics must never break primary UX flows (room creation, saves, etc.).
    // Session fingerprint mismatches can happen in mixed request contexts; degrade gracefully.
    $isSessionSecurityViolation = stripos($message, 'Session security violation detected') !== false;
    if ($isSessionSecurityViolation) {
        Response::json([
            'success' => true,
            'analytics_skipped' => true,
            'message' => 'Analytics skipped due to session context mismatch'
        ]);
        return;
    }

    // For all other analytics failures, return success with diagnostics instead of HTTP 500.
    Response::json([
        'success' => true,
        'analytics_skipped' => true,
        'message' => 'Analytics unavailable',
        'details' => ['details' => $message]
    ]);
}
