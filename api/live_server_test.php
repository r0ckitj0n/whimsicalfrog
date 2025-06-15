<?php
// Live Server Database Connection Test
// Upload this file to your live server and access it via: https://whimsicalfrog.us/api/live_server_test.php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Live Server Database Test</h2>";
echo "<h3>Environment Detection</h3>";

// Show server variables
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "<br>";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "<br>";
echo "PHP_SAPI: " . PHP_SAPI . "<br>";
echo "WHF_ENV: " . ($_SERVER['WHF_ENV'] ?? 'Not set') . "<br>";

// Include config
echo "<h3>Config Loading</h3>";
try {
    require_once 'config.php';
    echo "✅ Config loaded successfully<br>";
    
    // Show detected environment
    echo "Environment detected as: " . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "<br>";
    
    // Show database config (without showing full password)
    echo "Database Host: " . $host . "<br>";
    echo "Database Name: " . $db . "<br>";
    echo "Database User: " . $user . "<br>";
    echo "Password Length: " . strlen($pass) . " characters<br>";
    echo "Password Ends With: " . substr($pass, -1) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Config loading failed: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>Database Connection Test</h3>";
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "✅ Database connection successful!<br>";
    
    // Test a simple query
    $stmt = $pdo->query('SELECT 1 as test');
    $result = $stmt->fetch();
    if ($result['test'] == 1) {
        echo "✅ Basic query test passed<br>";
    } else {
        echo "❌ Basic query test failed<br>";
    }
    
    // Check if room_maps table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'room_maps'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ room_maps table exists<br>";
        
        // Test table access
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM room_maps");
        $count = $stmt->fetch();
        echo "✅ room_maps table has " . $count['count'] . " records<br>";
    } else {
        echo "❌ room_maps table does not exist<br>";
        echo "This is likely the source of the 500 errors.<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
}

echo "<h3>Next Steps</h3>";
echo "1. If environment detection shows LOCAL instead of PRODUCTION, the server environment detection needs adjustment.<br>";
echo "2. If database connection fails, there may be a network or credential issue.<br>";
echo "3. If room_maps table doesn't exist, run the simple_init_room_maps.php script.<br>";
?> 