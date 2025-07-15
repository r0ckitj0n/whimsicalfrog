<?php
// Include centralized database class
require_once __DIR__ . '/../includes/database.php';

// Set error reporting for development
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// For API endpoints, don't display HTML errors - only log them
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    ini_set('display_errors', 0);
    ini_set('html_errors', 0);
} else {
    ini_set('display_errors', 1);
}

// Helper function to detect if this is an AJAX request
function isAjaxRequest() {
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
    // Local database credentials
    $host = 'localhost';
    $db   = 'whimsicalfrog';
    $user = 'root';
    $pass = 'Palz2516';
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
