<?php
// Set error reporting for maximum debugging information
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

// Output server information
echo "<h2>Server Information</h2>";
echo "<p>Server: " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Load config file
echo "<h2>Loading Configuration</h2>";
try {
    require_once 'api/config.php';
    echo "<p style='color:green;'>✓ Config file loaded successfully</p>";
    
    // Display environment detection
    echo "<p>Environment detected as: <strong>" . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "</strong></p>";
    
    // Display database configuration (masked for security)
    echo "<h2>Database Configuration</h2>";
    echo "<p>Host: " . $host . "</p>";
    echo "<p>Database: " . $db . "</p>";
    echo "<p>User: " . $user . "</p>";
    echo "<p>Password: " . (strlen($pass) > 0 ? str_repeat('*', strlen($pass)) : 'Empty password') . "</p>";
    echo "<p>DSN: " . preg_replace('/password=([^;]*)/', 'password=******', $dsn) . "</p>";
    
    // Test database connection
    echo "<h2>Connection Test</h2>";
    try {
        $startTime = microtime(true);
        $pdo = new PDO($dsn, $user, $pass, $options);
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        
        echo "<p style='color:green;'>✓ Database connection successful!</p>";
        echo "<p>Connection established in " . $connectionTime . " ms</p>";
        
        // Test a simple query
        echo "<h2>Query Test</h2>";
        try {
            $startTime = microtime(true);
            $stmt = $pdo->query("SELECT 1 AS test");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $endTime = microtime(true);
            $queryTime = round(($endTime - $startTime) * 1000, 2);
            
            echo "<p style='color:green;'>✓ Query executed successfully in " . $queryTime . " ms</p>";
            echo "<p>Result: " . print_r($result, true) . "</p>";
            
            // Test a more complex query to check table access
            echo "<h3>Testing Table Access</h3>";
            try {
                // Try to get tables list
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo "<p style='color:green;'>✓ Tables retrieved successfully</p>";
                echo "<p>Found " . count($tables) . " tables:</p>";
                echo "<ul>";
                foreach ($tables as $table) {
                    echo "<li>" . htmlspecialchars($table) . "</li>";
                }
                echo "</ul>";
                
            } catch (PDOException $e) {
                echo "<p style='color:orange;'>⚠ Could not retrieve tables: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color:red;'>✗ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color:red;'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        
        // Additional connection debugging
        echo "<h3>Connection Debugging</h3>";
        
        // Check if we can resolve the hostname
        if (function_exists('gethostbyname')) {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                echo "<p style='color:red;'>✗ Could not resolve hostname: " . htmlspecialchars($host) . "</p>";
            } else {
                echo "<p>Hostname " . htmlspecialchars($host) . " resolves to IP: " . htmlspecialchars($ip) . "</p>";
            }
        }
        
        // Check if we can connect to the port
        $port = 3306; // Default MySQL port
        echo "<p>Attempting to connect to " . htmlspecialchars($host) . ":" . $port . "...</p>";
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$socket) {
            echo "<p style='color:red;'>✗ Could not connect to port: " . htmlspecialchars($errstr) . " (error " . $errno . ")</p>";
        } else {
            echo "<p style='color:green;'>✓ Successfully connected to port " . $port . "</p>";
            fclose($socket);
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error loading config: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Display PDO drivers
echo "<h2>Available PDO Drivers</h2>";
$drivers = PDO::getAvailableDrivers();
if (empty($drivers)) {
    echo "<p style='color:orange;'>⚠ No PDO drivers available</p>";
} else {
    echo "<ul>";
    foreach ($drivers as $driver) {
        echo "<li>" . htmlspecialchars($driver) . "</li>";
    }
    echo "</ul>";
}

// Check for MySQL/MySQLi extension
echo "<h2>MySQL Extensions</h2>";
if (function_exists('mysqli_connect')) {
    echo "<p style='color:green;'>✓ MySQLi extension is available</p>";
} else {
    echo "<p style='color:orange;'>⚠ MySQLi extension is not available</p>";
}

if (function_exists('mysql_connect')) {
    echo "<p style='color:orange;'>⚠ Deprecated MySQL extension is available</p>";
} else {
    echo "<p>Deprecated MySQL extension is not available (this is good)</p>";
}

echo "<hr>";
echo "<p><em>Test completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>
