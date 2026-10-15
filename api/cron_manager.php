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

function generate_cron_token(): string
{
    return bin2hex(random_bytes(24));
}

function cron_token_is_hash(string $stored): bool
{
    $info = password_get_info($stored);
    return isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$baseUrl = (defined('WF_PUBLIC_BASE') && WF_PUBLIC_BASE)
    ? ('https://whimsicalfrog.us' . WF_PUBLIC_BASE)
    : 'https://whimsicalfrog.us';

$curlExample = "curl -sS -X POST '{$baseUrl}/api/maintenance.php?action=prune_sessions' \\\n"
    . "  -H 'X-Maintenance-Token: $(cat ~/.config/whimsicalfrog/prune-token)' \\\n"
    . "  -d 'days=2'";

switch ($action) {
    case 'get':
        $current = secret_get($tokenKey);
        $configured = is_string($current) && $current !== '';
        $hashed = $configured && cron_token_is_hash($current);

        echo json_encode([
            'success' => true,
            'data' => [
                'configured' => $configured,
                'stored_as_hash' => $hashed,
                'token_masked' => $hashed
                    ? '(hash-only; plaintext not retained server-side)'
                    : ($configured ? (substr($current, 0, 4) . '•••' . substr($current, -4)) : null),
                'base_url' => $baseUrl,
                'auth' => 'POST + X-Maintenance-Token header (or admin_token in POST body)',
                'curl_example' => $curlExample,
                // Deprecated: do not put tokens in URLs
                'web_cron_url' => null,
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
        $hash = password_hash($newToken, PASSWORD_DEFAULT);
        if ($hash === false || !secret_set($tokenKey, $hash)) {
            echo json_encode(['success' => false, 'error' => 'Failed to rotate token']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Token rotated. Store the plaintext once; only a hash is kept server-side.',
            'data' => [
                // One-time plaintext for the operator to save locally (not logged).
                'token' => $newToken,
                'token_masked' => substr($newToken, 0, 4) . '•••' . substr($newToken, -4),
                'stored_as_hash' => true,
                'curl_example' => $curlExample,
                'web_cron_url' => null,
            ]
        ]);
        break;

    case 'run_now':
        // Trigger the maintenance endpoint via internal include
        $current = secret_get($tokenKey);
        if (!$current) {
            echo json_encode(['success' => false, 'error' => 'No token configured']);
            exit;
        }

        // Internal call cannot password_verify without plaintext. Prefer HTTP loopback with
        // a short-lived session is complex; invoke prune logic by POSTing locally is better.
        // Fall back: require the caller to use the external script. Keep best-effort include
        // only when legacy plaintext is still stored.
        if (cron_token_is_hash($current)) {
            echo json_encode([
                'success' => false,
                'error' => 'Token is hash-only. Run prune via POST + X-Maintenance-Token (see curl_example).',
                'curl_example' => $curlExample,
            ]);
            exit;
        }

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['action'] = 'prune_sessions';
        $_POST['days'] = '2';
        $_POST['admin_token'] = $current;
        $_SERVER['HTTP_X_MAINTENANCE_TOKEN'] = $current;

        ob_start();
        include dirname(__DIR__) . '/api/maintenance.php';
        $output = ob_get_clean();

        $result = json_decode($output, true);
        echo json_encode([
            'success' => true,
            'maintenance_result' => $result
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
