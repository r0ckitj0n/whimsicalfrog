<?php

// Enable CORS only for true API requests and only if headers have not been sent yet
try {
    $__wf_req_path_early = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
} catch (Throwable $e) {
    $__wf_req_path_early = '/';
}
$__wf_script_early = $_SERVER['SCRIPT_NAME'] ?? '';
$__wf_is_api_context_early = (strpos($__wf_script_early, '/api/') !== false) || (strpos($__wf_req_path_early, '/api/') === 0);
if ($__wf_is_api_context_early && !headers_sent()) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $isLocalOrigin = is_string($origin) && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin);

    // Only reflect allowed dev origins or your real site origin
    if ($isLocalOrigin) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    } else {
        // In production, you can either disable CORS for cross-origin completely or reflect a known domain
        // header('Access-Control-Allow-Origin: https://whimsicalfrog.us');
        // header('Access-Control-Allow-Credentials: true');
        // header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    // Handle OPTIONS preflight quickly
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// For API context, hard-disable HTML error output so JSON isn't polluted
if ($__wf_is_api_context_early) {
    // Do this as early as possible
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
}

// Polyfills for PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') return true;
        $len = strlen($needle);
        if ($len === 0) return true;
        return substr($haystack, -$len) === $needle;
    }
}

// Improved environment detection with multiple checks
$isLocalhost = false;

// Check 1: Check if running from command line
if (PHP_SAPI === 'cli') {
    $isLocalhost = true;
}
// Check 2: Check server headers for localhost indicators
elseif (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    $isLocalhost = true;
}

// Optional override via environment variable (e.g., for Docker or specific server configs)
if (isset($_SERVER['WHF_ENV'])) {
    if ($_SERVER['WHF_ENV'] === 'prod') {
        $isLocalhost = false;
    } elseif ($_SERVER['WHF_ENV'] === 'local') {
        $isLocalhost = true;
    }
}

// Centralized includes for the entire application
require_once __DIR__ . '/../includes/logging_config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/logger.php';

require_once __DIR__ . '/../includes/error_logger.php';


// Simple .env loader (optional, no external dependency)
if (!function_exists('wf_load_env')) {
    function wf_load_env(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        foreach (file($path) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            list($k, $v) = array_map('trim', explode('=', $line, 2));
            // strip optional quotes
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}
if (is_readable(__DIR__ . '/../.env')) {
    wf_load_env(__DIR__ . '/../.env');
}

if (!function_exists('wf_env')) {
    function wf_env(string $key, $default = null)
    {
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
}

// Branding and site configuration (portable defaults)
if (!defined('SITE_NAME')) {
    define('SITE_NAME', wf_env('SITE_NAME', 'Your Site'));
}
if (!defined('APP_URL')) {
    define('APP_URL', wf_env('APP_URL', ''));
}
if (!defined('BRAND_LOGO_PATH')) {
    define('BRAND_LOGO_PATH', wf_env('BRAND_LOGO_PATH', '/images/logos/logo-whimsicalfrog.webp'));
}

// Set error reporting for development
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Always log errors to file so production issues are captured without exposing details
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Improved environment detection with multiple checks
$isLocalhost = false;

// Check 1: Check if running from command line
if (PHP_SAPI === 'cli') {
    $isLocalhost = true;
}
// Check 2: Check server headers for localhost indicators
elseif (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    $isLocalhost = true;
}

// Optional override via environment variable (e.g., for Docker or specific server configs)
if (isset($_SERVER['WHF_ENV'])) {
    if ($_SERVER['WHF_ENV'] === 'prod') {
        $isLocalhost = false;
    } elseif ($_SERVER['WHF_ENV'] === 'local') {
        $isLocalhost = true;
    }
}

// For API endpoints, don't display HTML errors - only log them (force off for any /api path)
// Ensure the flag is defined before use
if (!isset($__wf_is_api_context)) {
    $__wf_is_api_context = isset($__wf_is_api_context_early) ? $__wf_is_api_context_early : false;
}
if ($__wf_is_api_context) {
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
} else {
    // Only display errors in the browser while in local development
    ini_set('display_errors', $isLocalhost ? 1 : 0);
}

// Helper function to detect if this is an AJAX request
function isAjaxRequest()
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (isset($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
           (isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

// Database configuration based on environment
if ($isLocalhost) {
    // Load local credentials from config/my.cnf
    $host   = 'localhost';
    $db     = 'whimsicalfrog';
    $user   = 'root';
    $pass   = 'Palz2516!';
    $port   = 3306;
    $socket = null; // Force TCP connection instead of socket
    $iniPath = __DIR__ . '/../config/my.cnf';

    if (file_exists($iniPath)) {
        $inClient = false;
        foreach (file($iniPath) as $line) {
            $line = trim($line);
            // Corrected regex to find [client] section
            if (preg_match('/^\[client\]/i', $line)) {
                $inClient = true;
                continue;
            }
            if ($inClient) {
                // Stop at the next section
                if (preg_match('/^\[.*\]/', $line)) {
                    break;
                }
                if (strpos($line, '=') !== false) {
                    list($k, $v) = array_map('trim', explode('=', $line, 2));
                    switch (strtolower($k)) {
                        case 'user': $user = $v;
                            break;
                        case 'password': $pass = $v;
                            break;
                        case 'host': $host = $v;
                            break;
                        case 'port': $port = $v;
                            break;
                        case 'socket': $socket = $v;
                            break;
                    }
                }
            }
        }
    }

    // Allow environment overrides for local DB
    $host   = wf_env('WF_DB_LOCAL_HOST', $host ?: 'localhost');
    $db     = wf_env('WF_DB_LOCAL_NAME', $db);
    $user   = wf_env('WF_DB_LOCAL_USER', $user);
    $pass   = wf_env('WF_DB_LOCAL_PASS', $pass);
    $port   = (int) wf_env('WF_DB_LOCAL_PORT', $port);
    $socket = wf_env('WF_DB_LOCAL_SOCKET', $socket);
} else {
    // Production database credentials (defaults, can be overridden by env)
    $host = wf_env('WF_DB_LIVE_HOST', 'db5017975223.hosting-data.io');
    $db   = wf_env('WF_DB_LIVE_NAME', 'dbs14295502');
    $user = wf_env('WF_DB_LIVE_USER', 'dbu2826619');
    $pass = wf_env('WF_DB_LIVE_PASS', 'Ruok2drvacar?');
    $port = (int) wf_env('WF_DB_LIVE_PORT', 3306);
    $socket = wf_env('WF_DB_LIVE_SOCKET', null);
}

// Common database settings
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
if (!empty($port)) {
    $dsn .= ";port={$port}";
}
if (!empty($socket)) {
    $dsn .= ";unix_socket={$socket}";
}
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// For debugging - only show if not an AJAX request and debug parameter is set
if ($isLocalhost && isset($_GET['debug']) && !isAjaxRequest()) {
    echo "Environment: " . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "<br>";
    echo "Database: $host/$db<br>";
}

// Initialize loggers that depend on database credentials only in API context
// or when explicitly enabled, to avoid heavy DB work on normal page loads.
$__wf_req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$__wf_script = $_SERVER['SCRIPT_NAME'] ?? '';
$__wf_is_api_context = (strpos($__wf_script, '/api/') !== false) || (strpos($__wf_req_path, '/api/') === 0);
$__wf_enable_db_loggers = getenv('WF_ENABLE_DB_LOGGERS') === '1';
// IMPORTANT: Only enable heavy DB loggers when explicitly requested via env.
// Loading these on every API request can introduce large cold-start TTFB.
if ($__wf_enable_db_loggers) {
    require_once __DIR__ . '/../includes/database_logger.php';
    require_once __DIR__ . '/../includes/admin_logger.php';
}

// -------------------------------------------------------------------
// Centralized environment DB configurations for admin/CLI tools
// -------------------------------------------------------------------
// Always expose both 'local' and 'live' configs so tools can switch
// without hardcoding credentials in multiple places.

// Build local config (read from my.cnf when available)
$__wf_local_host   = 'localhost';
$__wf_local_db     = 'whimsicalfrog';
$__wf_local_user   = 'root';
$__wf_local_pass   = 'Palz2516!';
$__wf_local_port   = 3306;
$__wf_local_socket = null; // force TCP
$__wf_local_ini    = __DIR__ . '/../config/my.cnf';
if (file_exists($__wf_local_ini)) {
    $inClient = false;
    foreach (file($__wf_local_ini) as $line) {
        $line = trim($line);
        if (preg_match('/^\[client\]/i', $line)) {
            $inClient = true;
            continue;
        }
        if ($inClient) {
            if (preg_match('/^\[.*\]/', $line)) {
                break;
            }
            if (strpos($line, '=') !== false) {
                list($k, $v) = array_map('trim', explode('=', $line, 2));
                switch (strtolower($k)) {
                    case 'user': $__wf_local_user = $v;
                        break;
                    case 'password': $__wf_local_pass = $v;
                        break;
                    case 'host': $__wf_local_host = $v;
                        break;
                    case 'port': $__wf_local_port = $v;
                        break;
                    case 'socket': $__wf_local_socket = $v;
                        break;
                }
            }
        }
    }
}
$__wf_local_socket = null; // normalize to TCP

// Environment overrides for central local config
$__wf_local_host   = wf_env('WF_DB_LOCAL_HOST', $__wf_local_host);
$__wf_local_db     = wf_env('WF_DB_LOCAL_NAME', $__wf_local_db);
$__wf_local_user   = wf_env('WF_DB_LOCAL_USER', $__wf_local_user);
$__wf_local_pass   = wf_env('WF_DB_LOCAL_PASS', $__wf_local_pass);
$__wf_local_port   = (int) wf_env('WF_DB_LOCAL_PORT', $__wf_local_port);
$__wf_local_socket = wf_env('WF_DB_LOCAL_SOCKET', $__wf_local_socket);

// Build live config (mirror production values)
$__wf_live_host = wf_env('WF_DB_LIVE_HOST', 'db5017975223.hosting-data.io');
$__wf_live_db   = wf_env('WF_DB_LIVE_NAME', 'dbs14295502');
$__wf_live_user = wf_env('WF_DB_LIVE_USER', 'dbu2826619');
$__wf_live_pass = wf_env('WF_DB_LIVE_PASS', 'Ruok2drvacar?');
$__wf_live_port = (int) wf_env('WF_DB_LIVE_PORT', 3306);
$__wf_live_socket = wf_env('WF_DB_LIVE_SOCKET', null);

// Expose an array and a helper
$WF_DB_CONFIGS = [
    'local' => [
        'host' => $__wf_local_host,
        'db'   => $__wf_local_db,
        'user' => $__wf_local_user,
        'pass' => $__wf_local_pass,
        'port' => $__wf_local_port,
        'socket' => $__wf_local_socket,
    ],
    'live' => [
        'host' => $__wf_live_host,
        'db'   => $__wf_live_db,
        'user' => $__wf_live_user,
        'pass' => $__wf_live_pass,
        'port' => $__wf_live_port,
        'socket' => $__wf_live_socket,
    ],
    'current' => [
        'host' => $host,
        'db'   => $db,
        'user' => $user,
        'pass' => $pass,
        'port' => $port ?? 3306,
        'socket' => $socket ?? null,
    ],
];

if (!function_exists('wf_get_db_config')) {
    function wf_get_db_config(string $env = 'current'): array
    {
        global $WF_DB_CONFIGS;
        return $WF_DB_CONFIGS[$env] ?? $WF_DB_CONFIGS['current'];
    }
}
