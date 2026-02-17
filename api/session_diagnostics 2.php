<?php
// API endpoint for session diagnostics - admin only
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

function wf_resolve_session_save_dir(string $savePath): string
{
    // PHP save_path may be "N;MODE;/path" - use the last segment as the directory.
    if (strpos($savePath, ';') !== false) {
        $parts = explode(';', $savePath);
        $candidate = trim((string) end($parts));
        return $candidate !== '' ? $candidate : $savePath;
    }
    return $savePath;
}

function wf_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function wf_admin_from_auth_cookie(): bool
{
    if (!function_exists('wf_auth_cookie_name') || !function_exists('wf_auth_parse_cookie')) {
        return false;
    }
    $cookieVal = $_COOKIE[wf_auth_cookie_name()] ?? '';
    $parsed = wf_auth_parse_cookie(is_string($cookieVal) ? $cookieVal : '');
    if (!is_array($parsed) || empty($parsed['user_id'])) {
        return false;
    }

    try {
        $user = Database::queryOne(
            "SELECT id, username, email, role, first_name, last_name, phone_number FROM users WHERE id = ? LIMIT 1",
            [$parsed['user_id']]
        );
        if (!$user) {
            return false;
        }
        $role = strtolower((string) ($user['role'] ?? ''));
        if ($role !== WF_Constants::ROLE_ADMIN) {
            return false;
        }
        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('ensureSessionStarted')) {
                ensureSessionStarted();
            } else {
                @session_start();
            }
        }
        $_SESSION['user'] = [
            'user_id' => $user['id'],
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? WF_Constants::ROLE_ADMIN,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone_number' => $user['phone_number'] ?? null,
        ];
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function wf_list_php_sessions(string $currentSessionId, int $limit = 100, ?string &$scanError = null): array
{
    $savePathRaw = (string) ini_get('session.save_path');
    $saveDir = wf_resolve_session_save_dir($savePathRaw);
    if ($saveDir === '' || !is_dir($saveDir)) {
        $scanError = 'session.save_path directory not found';
        return [];
    }
    if (!is_readable($saveDir)) {
        $scanError = 'session.save_path is not readable by PHP process';
        return [];
    }

    $rows = [];
    $entries = @scandir($saveDir);
    if (!is_array($entries)) {
        $scanError = 'Unable to scan session.save_path directory';
        return [];
    }
    foreach ($entries as $entry) {
        if (!wf_starts_with($entry, 'sess_')) {
            continue;
        }
        $fullPath = $saveDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($fullPath)) {
            continue;
        }
        $sid = substr($entry, 5);
        $mtime = @filemtime($fullPath);
        $size = @filesize($fullPath);
        $rows[] = [
            'session_id' => $sid,
            'last_modified' => $mtime ? date('Y-m-d H:i:s', $mtime) : '',
            'file_path' => $fullPath,
            'bytes' => $size !== false ? (int) $size : 0,
            'is_current' => $sid === $currentSessionId,
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) $b['last_modified'], (string) $a['last_modified']);
    });

    if (count($rows) > $limit) {
        return array_slice($rows, 0, $limit);
    }
    return $rows;
}

// Require admin (attempt auth-cookie reconstruction fallback first).
$isAdminUser = function_exists('isAdmin') ? isAdmin() : false;
if (!$isAdminUser && class_exists('AuthSessionHelper')) {
    try {
        if (function_exists('ensureSessionStarted')) {
            ensureSessionStarted();
        } elseif (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        AuthSessionHelper::reconstructSessionFromCookie();
        $isAdminUser = function_exists('isAdmin') ? isAdmin() : false;
    } catch (Throwable $e) {
        // Ignore reconstruction errors; fall through to access check.
    }
}
if (!$isAdminUser) {
    $isAdminUser = wf_admin_from_auth_cookie();
}

$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            if (function_exists('ensureSessionStarted')) {
                ensureSessionStarted();
            } else {
                @session_start();
            }
        }

        $recentSessions = [];
        $analyticsQueryError = null;
        try {
            $recentSessions = Database::queryAll(
                "SELECT session_id, user_id, ip_address, user_agent, landing_page, referrer, started_at, last_activity, total_page_views, converted, conversion_value
                 FROM analytics_sessions
                 ORDER BY last_activity DESC
                 LIMIT 100"
            );
        } catch (Throwable $e) {
            $analyticsQueryError = $e->getMessage();
            $recentSessions = [];
        }

        $cookieSessionName = session_name();
        $currentSessionId = session_id();
        if ($currentSessionId === '' && $cookieSessionName !== '' && isset($_COOKIE[$cookieSessionName])) {
            $currentSessionId = (string) $_COOKIE[$cookieSessionName];
        }
        $phpSessionScanError = null;
        $phpSessions = wf_list_php_sessions($currentSessionId, 100, $phpSessionScanError);
        {
            $hasCurrent = false;
            foreach ($phpSessions as $row) {
                if (($row['session_id'] ?? '') === $currentSessionId) {
                    $hasCurrent = true;
                    break;
                }
            }
            if (!$hasCurrent) {
                array_unshift($phpSessions, [
                    'session_id' => $currentSessionId !== '' ? $currentSessionId : '(missing)',
                    'last_modified' => date('Y-m-d H:i:s'),
                    'file_path' => '',
                    'bytes' => 0,
                    'is_current' => true,
                ]);
            }
        }

        // Mask sensitive values in $_SERVER
        $serverData = $_SERVER;
        $sensitiveKeys = ['PHP_AUTH_PW', 'HTTP_AUTHORIZATION', 'HTTP_COOKIE'];
        foreach ($sensitiveKeys as $key) {
            if (isset($serverData[$key])) {
                $serverData[$key] = '***MASKED***';
            }
        }

        if (!$isAdminUser) {
            // Non-admin fallback: return safe current-session diagnostics only.
            $safeSession = [];
            if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
                $safeSession['user'] = [
                    'user_id' => $_SESSION['user']['user_id'] ?? null,
                    'role' => $_SESSION['user']['role'] ?? null,
                    'username' => $_SESSION['user']['username'] ?? null,
                ];
            }
            echo json_encode([
                'success' => true,
                'data' => [
                    'session' => $safeSession,
                    'cookies' => [],
                    'server' => [],
                    'session_id' => $currentSessionId,
                    'session_status' => session_status(),
                    'php_version' => PHP_VERSION,
                    'recent_sessions' => [],
                    'php_sessions' => $phpSessions,
                    'php_session_save_path' => wf_resolve_session_save_dir((string) ini_get('session.save_path')),
                    'php_session_scan_error' => $phpSessionScanError,
                    'analytics_query_error' => 'Admin access required for analytics session list',
                ],
            ]);
            break;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'session' => $_SESSION ?? [],
                'cookies' => $_COOKIE ?? [],
                'server' => $serverData,
                'session_id' => $currentSessionId,
                'session_status' => session_status(),
                'php_version' => PHP_VERSION,
                'recent_sessions' => $recentSessions,
                'php_sessions' => $phpSessions,
                'php_session_save_path' => wf_resolve_session_save_dir((string) ini_get('session.save_path')),
                'php_session_scan_error' => $phpSessionScanError,
                'analytics_query_error' => $analyticsQueryError,
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
