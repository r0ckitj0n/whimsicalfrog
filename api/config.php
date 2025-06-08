<?php
/**
 * Database Configuration File
 * 
 * This file detects the environment and sets the appropriate database credentials.
 * It also provides a function to get a PDO connection that can be used by all API files.
 */

// Environment detection
function isLocalEnvironment() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return strpos($host, 'localhost') !== false || 
           strpos($host, '127.0.0.1') !== false;
}

/**
 * Get database connection
 * 
 * @return PDO Database connection
 * @throws PDOException If connection fails
 */
function getConnection() {
    // Set database credentials based on environment
    if (isLocalEnvironment()) {
        // Local development environment
        $host = 'localhost';
        $dbname = 'whimsicalfrog';
        $username = 'root';
        $password = 'Palz2516';
    } else {
        // Live server environment (IONOS)
        // Update these with your IONOS database credentials
        $host = 'db1234567.hosting-data.io'; // Update with actual IONOS database host
        $dbname = 'db1234567'; // Update with actual IONOS database name
        $username = 'dbu1234567'; // Update with actual IONOS database username
        $password = 'your-ionos-password'; // Update with actual IONOS database password
    }
    
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Return connection
    return $pdo;
}

/**
 * Helper function to set common API response headers
 */
function setApiHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Helper function to calculate sum of costs
 * 
 * @param array $items Array of cost items
 * @return float Total cost
 */
function sumCost($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['cost'] ?? 0);
    }
    return $total;
}
?>