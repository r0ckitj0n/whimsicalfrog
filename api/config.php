<?php
// Enable CORS for all API requests (development only)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// Handle OPTIONS preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Improved environment detection with multiple checks
$isLocalhost = false;

// Check 1: Check if running from command line
if (PHP_SAPI === 'cli') {
    $isLocalhost = true;
} 
// Check 2: Check server headers for localhost indicators
else if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
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
else if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
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

// Database configuration based on environment
if ($isLocalhost) {
    // Load local credentials from config/my.cnf
    $host   = '127.0.0.1';
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
    
    // Force TCP connection by nullifying socket after config file read
    $socket = null;
} else {
    // Production database credentials - updated with actual IONOS values
    $host = 'db5017975223.hosting-data.io'; // Real IONOS database host
    $db   = 'dbs14295502';                  // Real IONOS database name
    $user = 'dbu2826619';                   // Real IONOS database user
    $pass = 'Palz2516';                    // IONOS database password
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

// Initialize loggers that depend on database credentials
require_once __DIR__ . '/../includes/database_logger.php';
require_once __DIR__ . '/../includes/admin_logger.php';
