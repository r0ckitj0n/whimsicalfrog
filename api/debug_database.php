<?php
// Database Connection Diagnostic Script
// This will help identify what's wrong on the live server

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Database Connection Diagnostic</h2>";

// Test 1: Check if config file exists
echo "<h3>1. Config File Check</h3>";
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    echo "✅ Config file exists at: $configPath<br>";
} else {
    echo "❌ Config file NOT found at: $configPath<br>";
    echo "Current directory: " . __DIR__ . "<br>";
    echo "Files in directory: " . implode(', ', scandir(__DIR__)) . "<br>";
}

// Test 2: Try to load config
echo "<h3>2. Config Loading Test</h3>";
try {
    require_once 'config.php';
    echo "✅ Config file loaded successfully<br>";
    
    // Show environment detection
    echo "Environment detection:<br>";
    if (isset($_SERVER['HTTP_HOST'])) {
        echo "- HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "<br>";
    }
    if (isset($_SERVER['SERVER_NAME'])) {
        echo "- SERVER_NAME: " . $_SERVER['SERVER_NAME'] . "<br>";
    }
    if (isset($_SERVER['WHF_ENV'])) {
        echo "- WHF_ENV: " . $_SERVER['WHF_ENV'] . "<br>";
    }
    
    // Show detected database config (without passwords)
    echo "Detected database config:<br>";
    echo "- Host: " . (isset($host) ? $host : 'NOT SET') . "<br>";
    echo "- Database: " . (isset($db) ? $db : 'NOT SET') . "<br>";
    echo "- User: " . (isset($user) ? $user : 'NOT SET') . "<br>";
    echo "- DSN: " . (isset($dsn) ? $dsn : 'NOT SET') . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error loading config: " . $e->getMessage() . "<br>";
}

// Test 3: PDO Extension Check
echo "<h3>3. PDO Extension Check</h3>";
if (extension_loaded('pdo')) {
    echo "✅ PDO extension is loaded<br>";
    
    if (extension_loaded('pdo_mysql')) {
        echo "✅ PDO MySQL driver is loaded<br>";
    } else {
        echo "❌ PDO MySQL driver is NOT loaded<br>";
    }
} else {
    echo "❌ PDO extension is NOT loaded<br>";
}

// Test 4: Database Connection Test
echo "<h3>4. Database Connection Test</h3>";
if (isset($dsn) && isset($user) && isset($pass) && isset($options)) {
    try {
        try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
        echo "✅ Database connection successful!<br>";
        
        // Test basic query
        $result = $pdo->query("SELECT 1 as test");
        if ($result) {
            echo "✅ Basic query test passed<br>";
        } else {
            echo "❌ Basic query test failed<br>";
        }
        
        // Check if room_maps table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'room_maps'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            echo "✅ room_maps table already exists<br>";
        } else {
            echo "⚠️ room_maps table does not exist (this is expected for new setup)<br>";
        }
        
    } catch (PDOException $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
        echo "Error code: " . $e->getCode() . "<br>";
    }
} else {
    echo "❌ Database configuration variables not set<br>";
}

// Test 5: Try to create table
echo "<h3>5. Table Creation Test</h3>";
if (isset($pdo) && $pdo) {
    try {
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS room_maps_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            test_field VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createTableSQL);
        echo "✅ Test table creation successful<br>";
        
        // Clean up test table
        $pdo->exec("DROP TABLE IF EXISTS room_maps_test");
        echo "✅ Test table cleanup successful<br>";
        
    } catch (PDOException $e) {
        echo "❌ Table creation failed: " . $e->getMessage() . "<br>";
    }
}

echo "<h3>6. PHP Info</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown') . "<br>";

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If database connection failed, check your live server database credentials</li>";
echo "<li>If table creation failed, check database permissions</li>";
echo "<li>If config loading failed, check file paths and permissions</li>";
echo "</ul>";
?> 