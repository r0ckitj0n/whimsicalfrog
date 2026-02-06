<?php
// API endpoint for cron/maintenance token management - admin only
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/secret_store.php';

header('Content-Type: application/json');

// Require admin
if (!function_exists('isAdmin') || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$tokenKey = 'maintenance_admin_token';

function generate_cron_token()
{
    return bin2hex(random_bytes(24));
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($action) {
    case 'get':
        $currentToken = secret_get($tokenKey);
        if (!$currentToken) {
            $currentToken = generate_cron_token();
            secret_set($tokenKey, $currentToken);
        }

        $baseUrl = (defined('WF_PUBLIC_BASE') && WF_PUBLIC_BASE)
            ? ('https://whimsicalfrog.us' . WF_PUBLIC_BASE)
            : 'https://whimsicalfrog.us';
        $webCronUrl = $baseUrl . '/api/maintenance.php?action=prune_sessions&days=2&admin_token=' . urlencode($currentToken);

        echo json_encode([
            'success' => true,
            'data' => [
                'token_masked' => substr($currentToken, 0, 4) . '•••' . substr($currentToken, -4),
                'web_cron_url' => $webCronUrl,
                'base_url' => $baseUrl,
            ]
        ]);
        break;

    case 'rotate_token':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST required']);
            exit;
        }

        $newToken = generate_cron_token();
        if (!secret_set($tokenKey, $newToken)) {
            echo json_encode(['success' => false, 'error' => 'Failed to rotate token']);
            exit;
        }

        $baseUrl = (defined('WF_PUBLIC_BASE') && WF_PUBLIC_BASE)
            ? ('https://whimsicalfrog.us' . WF_PUBLIC_BASE)
            : 'https://whimsicalfrog.us';
        $webCronUrl = $baseUrl . '/api/maintenance.php?action=prune_sessions&days=2&admin_token=' . urlencode($newToken);

        echo json_encode([
            'success' => true,
            'message' => 'Token rotated successfully',
            'data' => [
                'token_masked' => substr($newToken, 0, 4) . '•••' . substr($newToken, -4),
                'web_cron_url' => $webCronUrl,
            ]
        ]);
        break;

    case 'run_now':
        // Trigger the maintenance endpoint
        $currentToken = secret_get($tokenKey);
        if (!$currentToken) {
            echo json_encode(['success' => false, 'error' => 'No token configured']);
            exit;
        }

        // Make internal request to maintenance
        $maintenanceUrl = dirname(__DIR__) . '/api/maintenance.php';
        $_GET['action'] = 'prune_sessions';
        $_GET['days'] = '2';
        $_GET['admin_token'] = $currentToken;

        ob_start();
        include $maintenanceUrl;
        $output = ob_get_clean();

        $result = json_decode($output, true);
        echo json_encode([
            'success' => true,
            'maintenance_result' => $result
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
