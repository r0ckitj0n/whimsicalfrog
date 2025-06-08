<?php
// Set error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Detect environment
$isLocalhost = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
               strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;

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

// For debugging
if ($isLocalhost && isset($_GET['debug'])) {
    echo "Environment: " . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "<br>";
    echo "Database: $host/$db<br>";
}
