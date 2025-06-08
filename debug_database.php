<?php
// Set error reporting for maximum debugging information
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Add basic styling for readability
echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Debug Information</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #556B2F; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .table-container { margin-bottom: 30px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .count { font-weight: bold; color: #6B8E23; }
    </style>
</head>
<body>
    <h1>Whimsical Frog Database Debug</h1>';

echo "<p>Generated at: " . date('Y-m-d H:i:s') . "</p>";

// Output server information
echo "<h2>Server Information</h2>";
echo "<p>Server: " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

// Load database configuration
try {
    require_once 'api/config.php';
    echo "<p class='success'>✓ Config file loaded successfully</p>";
    
    // Display environment detection
    echo "<p>Environment detected as: <strong>" . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "</strong></p>";
    
    // Display database configuration (masked for security)
    echo "<h2>Database Configuration</h2>";
    echo "<p>Host: " . $host . "</p>";
    echo "<p>Database: " . $db . "</p>";
    echo "<p>User: " . $user . "</p>";
    echo "<p>Password: " . (strlen($pass) > 0 ? str_repeat('*', strlen($pass)) : 'Empty password') . "</p>";
    
    // Connect to database
    echo "<h2>Database Connection</h2>";
    try {
        $startTime = microtime(true);
        $pdo = new PDO($dsn, $user, $pass, $options);
        $endTime = microtime(true);
        $connectionTime = round(($endTime - $startTime) * 1000, 2);
        
        echo "<p class='success'>✓ Database connection successful! ({$connectionTime}ms)</p>";
        
        // Get all tables
        echo "<h2>Database Tables</h2>";
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        if (empty($tables)) {
            echo "<p class='error'>No tables found in the database!</p>";
        } else {
            echo "<p>Found <span class='count'>" . count($tables) . "</span> tables:</p>";
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            echo "</ul>";
            
            // Examine each table structure and data
            foreach ($tables as $table) {
                echo "<div class='table-container'>";
                echo "<h3>Table: " . htmlspecialchars($table) . "</h3>";
                
                // Get table structure
                echo "<h4>Table Structure</h4>";
                $stmt = $pdo->query("DESCRIBE `{$table}`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<table>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                foreach ($columns as $column) {
                    echo "<tr>";
                    foreach ($column as $key => $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
                
                // Get row count
                $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                $count = $stmt->fetchColumn();
                
                echo "<h4>Data Preview (<span class='count'>{$count}</span> total rows)</h4>";
                
                if ($count == 0) {
                    echo "<p class='error'>No data in this table!</p>";
                } else {
                    // Get sample data (first 10 rows)
                    $stmt = $pdo->query("SELECT * FROM `{$table}` LIMIT 10");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        echo "<table>";
                        
                        // Table headers
                        echo "<tr>";
                        foreach (array_keys($rows[0]) as $header) {
                            echo "<th>" . htmlspecialchars($header) . "</th>";
                        }
                        echo "</tr>";
                        
                        // Table data
                        foreach ($rows as $row) {
                            echo "<tr>";
                            foreach ($row as $value) {
                                // Truncate long values
                                $displayValue = is_string($value) && strlen($value) > 100 ? 
                                    substr(htmlspecialchars($value), 0, 100) . '...' : 
                                    htmlspecialchars($value ?? 'NULL');
                                echo "<td>{$displayValue}</td>";
                            }
                            echo "</tr>";
                        }
                        
                        echo "</table>";
                        
                        if ($count > 10) {
                            echo "<p>Showing 10 of {$count} rows.</p>";
                        }
                    }
                }
                echo "</div>";
                
                // Special focus on products table
                if (strtolower($table) == 'products') {
                    echo "<h3>Products Table Analysis</h3>";
                    
                    // Check for required columns
                    $requiredColumns = ['productName', 'price', 'productType', 'description', 'imageUrl'];
                    $missingColumns = [];
                    
                    $columnNames = array_column($columns, 'Field');
                    foreach ($requiredColumns as $requiredColumn) {
                        if (!in_array($requiredColumn, $columnNames)) {
                            $missingColumns[] = $requiredColumn;
                        }
                    }
                    
                    if (!empty($missingColumns)) {
                        echo "<p class='error'>Missing required columns: " . implode(', ', $missingColumns) . "</p>";
                        echo "<p>The shop page expects these columns: " . implode(', ', $requiredColumns) . "</p>";
                    } else {
                        echo "<p class='success'>All required columns for shop display are present.</p>";
                    }
                    
                    // Check product types (categories)
                    if (in_array('productType', $columnNames)) {
                        $stmt = $pdo->query("SELECT DISTINCT productType FROM `{$table}`");
                        $productTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        echo "<h4>Product Categories Found:</h4>";
                        if (empty($productTypes)) {
                            echo "<p class='error'>No product categories found!</p>";
                        } else {
                            echo "<ul>";
                            foreach ($productTypes as $type) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE productType = ?");
                                $stmt->execute([$type]);
                                $typeCount = $stmt->fetchColumn();
                                
                                echo "<li>" . htmlspecialchars($type) . " (<span class='count'>{$typeCount}</span> products)</li>";
                            }
                            echo "</ul>";
                        }
                    }
                }
            }
        }
        
    } catch (PDOException $e) {
        echo "<p class='error'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Error loading config: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo '</body></html>';
?>
