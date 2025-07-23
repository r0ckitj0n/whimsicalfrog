<?php
// Enable CORS for all API requests (development only)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include centralized database class
require_once __DIR__ . '/../includes/database.php';

// Set error reporting for development
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Always log errors to file so production issues are captured without exposing details
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Detect environment early so it can be referenced below without warnings
$isLocalhost = false;

// Check 1: CLI implies local
if (PHP_SAPI === 'cli') {
    $isLocalhost = true;
}
// Check 2: host headers contain localhost/127.0.0.1
if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    $isLocalhost = true;
}
// Check 3: server name header
if (isset($_SERVER['SERVER_NAME']) && (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false)) {
    $isLocalhost = true;
}
// Environment variable overrides
if (isset($_SERVER['WHF_ENV'])) {
    $isLocalhost = $_SERVER['WHF_ENV'] === 'local' ? true : ($_SERVER['WHF_ENV'] === 'prod' ? false : $isLocalhost);
}

// For API endpoints, don't display HTML errors - only log them
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    ini_set('display_errors', 0);
    ini_set('html_errors', 0);
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

// Improved environment detection with multiple checks
$isLocalhost = false;

// Check 1: Check if running from command line
if (PHP_SAPI === 'cli') {
    $isLocalhost = true;
}

// Check 2: Check HTTP_HOST for localhost indicators
if (isset($_SERVER['HTTP_HOST'])) {
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
        $isLocalhost = true;
    }
}

// Check 3: Check SERVER_NAME for localhost indicators
if (isset($_SERVER['SERVER_NAME'])) {
    if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
        strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
        $isLocalhost = true;
    }
}

// Optional override via environment variable (set WHF_ENV=prod on live server)
if (isset($_SERVER['WHF_ENV']) && $_SERVER['WHF_ENV'] === 'prod') {
    $isLocalhost = false;
}
// Likewise WHF_ENV=local can force local mode
if (isset($_SERVER['WHF_ENV']) && $_SERVER['WHF_ENV'] === 'local') {
    $isLocalhost = true;
}

// Force local environment for development if needed
// Uncomment the line below to force local environment
// $isLocalhost = true;

// Database configuration based on environment
if ($isLocalhost) {
    // Load local credentials from config/my.cnf
    $host   = 'localhost';
    $db     = 'whimsicalfrog';
    $user   = 'admin';
    $pass   = 'Palz2516!';
    $port   = null;
    $socket = null;
    $iniPath = __DIR__ . '/../config/my.cnf';
    if (file_exists($iniPath)) {
        $inClient = false;
        foreach (file($iniPath) as $line) {
            $line = trim($line);
            if (preg_match('/^\[client\]/i', $line)) {
                $inClient = true;
                continue;
            }
            if ($inClient) {
                // stop at next section
                if (preg_match('/^\[.*\]/', $line)) {
                    break;
                }
                if (strpos($line, '=') !== false) {
                    list($k, $v) = array_map('trim', explode('=', $line, 2));
                    switch (strtolower($k)) {
                        case 'user': $user = $v; break;
                        case 'password': $pass = $v; break;
                        case 'host': $host = $v; break;
                        case 'port': $port = $v; break;
                        case 'socket': $socket = $v; break;
                    }
                }
            }
        }
    }
} else {
    // Production database credentials - updated with actual IONOS values
    $host = 'db5017975223.hosting-data.io'; // Real IONOS database host
    $db   = 'dbs14295502';                  // Real IONOS database name
    $user = 'dbu2826619';                   // Real IONOS database user
    $pass = 'Palz2516!';                    // IONOS database password
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
