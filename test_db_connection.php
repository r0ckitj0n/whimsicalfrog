<?php
/**
 * Database Connection Test Script for Whimsical Frog
 * 
 * This script tests database connectivity and shows what tables exist
 * to help debug data loading issues on the marketing, orders, and inventory pages.
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/api/config.php';

// HTML header for better readability
echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #556B2F; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #87ac3a; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Whimsical Frog Database Connection Test</h1>";

// Environment information
echo "<div class='section'>
    <h2>Environment Information</h2>
    <table>
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>Environment</td><td>" . ($isLocalhost ? '<span class="success">LOCAL</span>' : '<span class="warning">PRODUCTION</span>') . "</td></tr>
        <tr><td>Server Name</td><td>" . (isset($_SERVER['SERVER_NAME']) ? htmlspecialchars($_SERVER['SERVER_NAME']) : 'CLI Mode') . "</td></tr>
        <tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>
        <tr><td>Database Host</td><td>" . htmlspecialchars($host) . "</td></tr>
        <tr><td>Database Name</td><td>" . htmlspecialchars($db) . "</td></tr>
        <tr><td>Database User</td><td>" . htmlspecialchars($user) . "</td></tr>
        <tr><td>DSN</td><td>" . preg_replace('/password=([^;]*)/', 'password=***', $dsn) . "</td></tr>
        <tr><td>Execution Mode</td><td>" . (PHP_SAPI === 'cli' ? 'Command Line' : 'Web Server') . "</td></tr>
    </table>
</div>";

// Test database connection
echo "<div class='section'>
    <h2>Database Connection Test</h2>";

try {
    // Attempt to create a PDO connection
    $startTime = microtime(true);
    $pdo = new PDO($dsn, $user, $pass, $options);
    $endTime = microtime(true);
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "<p class='success'>✅ Connection successful! (Took {$connectionTime}ms)</p>";
    
    // Get server info
    echo "<h3>Database Server Information</h3>";
    $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    echo "<pre>" . ($serverInfo ? htmlspecialchars($serverInfo) : 'Not available') . "</pre>";
    echo "<p>Server Version: " . htmlspecialchars($pdo->getAttribute(PDO::ATTR_SERVER_VERSION)) . "</p>";
    echo "<p>Connection Status: " . htmlspecialchars($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) . "</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Error Details</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    
    // Show possible solutions
    echo "<h3>Possible Solutions</h3>
    <ul>
        <li>Check if the database server is running</li>
        <li>Verify database credentials in api/config.php</li>
        <li>Ensure the database '" . htmlspecialchars($db) . "' exists</li>
        <li>Check if the user '" . htmlspecialchars($user) . "' has proper permissions</li>
        <li>Verify network connectivity to the database server</li>
    </ul>";
    
    // End the script here if connection failed
    echo "</div></body></html>";
    exit;
}

// List all tables in the database
echo "<div class='section'>
    <h2>Database Tables</h2>";

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<p>Found " . count($tables) . " tables in the database:</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>⚠️ No tables found in the database!</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error listing tables: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Check specific tables related to the reported issues
$criticalTables = [
    'orders' => 'Orders page date field issue',
    'inventory' => 'Inventory admin page not loading',
    'products' => 'Product data on marketing page',
    'users' => 'Customer information'
];

echo "<div class='section'>
    <h2>Critical Tables Check</h2>
    <table>
        <tr>
            <th>Table</th>
            <th>Status</th>
            <th>Row Count</th>
            <th>Related Issue</th>
        </tr>";

foreach ($criticalTables as $table => $issue) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($table) . "</td>";
    
    // Check if table exists - Fixed SQL syntax by removing the quote() function
    $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "\'", $table) . "'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Get row count
        $countStmt = $pdo->query("SELECT COUNT(*) FROM " . $table);
        $rowCount = $countStmt->fetchColumn();
        
        $statusClass = ($rowCount > 0) ? 'success' : 'warning';
        $statusText = ($rowCount > 0) ? '✅ Exists with data' : '⚠️ Exists but empty';
        
        echo "<td class='{$statusClass}'>{$statusText}</td>";
        echo "<td>{$rowCount}</td>";
    } else {
        echo "<td class='error'>❌ Missing</td>";
        echo "<td>N/A</td>";
    }
    
    echo "<td>" . htmlspecialchars($issue) . "</td>";
    echo "</tr>";
}
echo "</table></div>";

// Sample data from critical tables
foreach ($criticalTables as $table => $issue) {
    if (in_array($table, $tables)) { // Only if table exists
        echo "<div class='section'>
            <h2>Sample Data: " . htmlspecialchars($table) . "</h2>";
        
        try {
            // Get table structure
            $structureStmt = $pdo->query("DESCRIBE " . $table);
            $structure = $structureStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Table Structure</h3>";
            echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($structure as $column) {
                echo "<tr>";
                foreach ($column as $key => $value) {
                    echo "<td>" . (is_null($value) ? "NULL" : htmlspecialchars($value)) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            
            // Get sample data (first 5 rows)
            $dataStmt = $pdo->query("SELECT * FROM " . $table . " LIMIT 5");
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($data) > 0) {
                echo "<h3>Sample Data (First 5 rows)</h3>";
                echo "<table><tr>";
                
                // Table headers from first row keys
                foreach (array_keys($data[0]) as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                // Table data
                foreach ($data as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . (is_null($value) ? "NULL" : htmlspecialchars($value)) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='warning'>⚠️ Table exists but contains no data</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Error retrieving data: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        echo "</div>";
    }
}

// Specific checks for reported issues
echo "<div class='section'>
    <h2>Specific Issue Checks</h2>";

// 1. Check orders table date field
if (in_array('orders', $tables)) {
    echo "<h3>Orders Table Date Field Check</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE orders");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $dateColumns = array_filter($columns, function($column) {
            return stripos($column, 'date') !== false || $column === 'date';
        });
        
        if (!empty($dateColumns)) {
            echo "<p class='success'>✅ Found date-related columns: " . implode(", ", $dateColumns) . "</p>";
            echo "<p>The admin_orders.php file uses \$order['date'] but your database might have: " . implode(" or ", $dateColumns) . "</p>";
        } else {
            echo "<p class='error'>❌ No date-related columns found in orders table!</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error checking orders table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// 2. Check marketing-related tables
echo "<h3>Marketing Tables Check</h3>";
$marketingTables = ['email_campaigns', 'discount_codes', 'social_accounts', 'social_posts'];
$missingMarketingTables = [];

foreach ($marketingTables as $table) {
    // Fixed SQL syntax by removing the quote() function
    $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "\'", $table) . "'");
    if ($stmt->rowCount() === 0) {
        $missingMarketingTables[] = $table;
    }
}

if (!empty($missingMarketingTables)) {
    echo "<p class='warning'>⚠️ Missing marketing tables: " . implode(", ", $missingMarketingTables) . "</p>";
    echo "<p>These tables are used by admin_marketing.php and might explain missing data.</p>";
} else {
    echo "<p class='success'>✅ All marketing tables exist</p>";
}

echo "</div>";

// Connection close
$pdo = null;

echo "<div class='section'>
    <h2>Recommendations</h2>
    <ul>
        <li>Compare table and column names with what your PHP code expects</li>
        <li>Check for empty tables that need data</li>
        <li>Verify that your environment detection is working correctly</li>
        <li>Make sure your database user has proper permissions</li>
        <li>Check for SQL syntax differences between your local and production environments</li>
    </ul>
</div>";

echo "</body></html>";
?>
