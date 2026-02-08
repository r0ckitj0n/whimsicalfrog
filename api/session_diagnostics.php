<?php
// API endpoint for session diagnostics - admin only
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

// Require admin
if (!function_exists('isAdmin') || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $recentSessions = [];
        try {
            $recentSessions = Database::queryAll(
                "SELECT session_id, user_id, ip_address, user_agent, landing_page, referrer, started_at, last_activity, total_page_views, converted, conversion_value
                 FROM analytics_sessions
                 ORDER BY last_activity DESC
                 LIMIT 100"
            );
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to load analytics sessions',
                'details' => $e->getMessage(),
            ]);
            exit;
        }

        // Mask sensitive values in $_SERVER
        $serverData = $_SERVER;
        $sensitiveKeys = ['PHP_AUTH_PW', 'HTTP_AUTHORIZATION', 'HTTP_COOKIE'];
        foreach ($sensitiveKeys as $key) {
            if (isset($serverData[$key])) {
                $serverData[$key] = '***MASKED***';
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'session' => $_SESSION ?? [],
                'cookies' => $_COOKIE ?? [],
                'server' => $serverData,
                'session_id' => session_id(),
                'session_status' => session_status(),
                'php_version' => PHP_VERSION,
                'recent_sessions' => $recentSessions,
            ]
        ]);
        break;

    case 'clear_session':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            exit;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Session cleared']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
