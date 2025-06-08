<?php
// Set error reporting for maximum debugging information
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>MySQLi Database Connection Test</h1>";

// Output server information
echo "<h2>Server Information</h2>";
echo "<p>Server: " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// IONOS Database Credentials
$host_name = 'db5017975223.hosting-data.io';
$database = 'dbs14295502';
$user_name = 'dbu2826619';
$password = 'Palz2516!';

// Display database configuration (masked for security)
echo "<h2>Database Configuration</h2>";
echo "<p>Host: " . $host_name . "</p>";
echo "<p>Database: " . $database . "</p>";
echo "<p>User: " . $user_name . "</p>";
echo "<p>Password: " . (strlen($password) > 0 ? str_repeat('*', strlen($password)) : 'Empty password') . "</p>";

// Test MySQLi connection
echo "<h2>MySQLi Connection Test</h2>";
try {
    $startTime = microtime(true);
    
    // Create connection using MySQLi as shown in IONOS sample
    $link = new mysqli($host_name, $user_name, $password, $database);
    
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    // Check connection
    if ($link->connect_error) {
        echo "<p style='color:red;'>✗ Connection failed: " . htmlspecialchars($link->connect_error) . "</p>";
        
        // Additional connection debugging
        echo "<h3>Connection Debugging</h3>";
        
        // Check if we can resolve the hostname
        if (function_exists('gethostbyname')) {
            $ip = gethostbyname($host_name);
            if ($ip === $host_name) {
                echo "<p style='color:red;'>✗ Could not resolve hostname: " . htmlspecialchars($host_name) . "</p>";
            } else {
                echo "<p>Hostname " . htmlspecialchars($host_name) . " resolves to IP: " . htmlspecialchars($ip) . "</p>";
            }
        }
        
        // Check if we can connect to the port
        $port = 3306; // Default MySQL port
        echo "<p>Attempting to connect to " . htmlspecialchars($host_name) . ":" . $port . "...</p>";
        $socket = @fsockopen($host_name, $port, $errno, $errstr, 5);
        if (!$socket) {
            echo "<p style='color:red;'>✗ Could not connect to port: " . htmlspecialchars($errstr) . " (error " . $errno . ")</p>";
        } else {
            echo "<p style='color:green;'>✓ Successfully connected to port " . $port . "</p>";
            fclose($socket);
        }
    } else {
        echo "<p style='color:green;'>✓ MySQLi connection successful!</p>";
        echo "<p>Connection established in " . $connectionTime . " ms</p>";
        
        // Test a simple query
        echo "<h2>Query Test</h2>";
        try {
            $startTime = microtime(true);
            $result = $link->query("SELECT 1 AS test");
            if ($result) {
                $row = $result->fetch_assoc();
                $endTime = microtime(true);
                $queryTime = round(($endTime - $startTime) * 1000, 2);
                
                echo "<p style='color:green;'>✓ Query executed successfully in " . $queryTime . " ms</p>";
                echo "<p>Result: " . print_r($row, true) . "</p>";
                
                // Test a more complex query to check table access
                echo "<h3>Testing Table Access</h3>";
                try {
                    // Try to get tables list
                    $result = $link->query("SHOW TABLES");
                    if ($result) {
                        $tables = [];
                        while ($row = $result->fetch_array()) {
                            $tables[] = $row[0];
                        }
                        
                        echo "<p style='color:green;'>✓ Tables retrieved successfully</p>";
                        echo "<p>Found " . count($tables) . " tables:</p>";
                        echo "<ul>";
                        foreach ($tables as $table) {
                            echo "<li>" . htmlspecialchars($table) . "</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<p style='color:orange;'>⚠ Could not retrieve tables: " . htmlspecialchars($link->error) . "</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color:orange;'>⚠ Error retrieving tables: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            } else {
                echo "<p style='color:red;'>✗ Query failed: " . htmlspecialchars($link->error) . "</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✗ Query error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        // Close connection
        $link->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
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
