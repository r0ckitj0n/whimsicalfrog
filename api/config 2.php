<?php
/**
 * Global Configuration API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/../includes/config_helper.php';
require_once __DIR__ . '/../includes/database/DatabaseEnv.php';
DatabaseEnv::ensureEnvLoaded();
date_default_timezone_set('UTC');

$isLocalhost = wf_detect_environment();
$dbCfg = wf_load_db_config($isLocalhost);

// Use the environment-loaded config, allowing for server-level overrides if necessary but prioritizing .env
$host = $dbCfg['host'];
$db = $dbCfg['db'];
$user = $dbCfg['user'];
$pass = $dbCfg['pass'];
$port = $dbCfg['port'];
$socket = $dbCfg['socket'] ?? null;

// CORS handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && preg_match('#^https?://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+)(:\d+)?$#i', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WF-Dev-Admin');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS')
    exit;

// Polyfills
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n)
    {
        return $n === '' || strpos($h, $n) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($h, $n)
    {
        return $n === '' || substr($h, -strlen($n)) === $n;
    }
}

// Includes
$wfIncludes = [
    __DIR__ . '/../includes/logging_config.php',
    __DIR__ . '/../includes/database.php',
    __DIR__ . '/../includes/logger.php',
    __DIR__ . '/../includes/error_logger.php',
];

foreach ($wfIncludes as $wfInc) {
    // Some deployments may omit optional logging files; the site should still run.
    if (!file_exists($wfInc)) {
        error_log('[api/config] Missing include: ' . $wfInc);
        continue;
    }
    require_once $wfInc;
}

// Environment already loaded via DatabaseEnv above


// Database constants
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
if ($port)
    $dsn .= ";port=$port";
if ($socket)
    $dsn .= ";unix_socket=$socket";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

define('SITE_NAME', getenv('SITE_NAME') ?: 'Whimsical Frog');
