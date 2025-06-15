<?php
// Database Import Script for Live Server
// Upload this file to your live server and access it via: https://whimsicalfrog.us/import_to_live_server.php
// IMPORTANT: Delete this file after use for security!

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security check - only allow from specific IP or with password
$IMPORT_PASSWORD = 'Palz2516Import'; // Change this to something secure
if (!isset($_GET['password']) || $_GET['password'] !== $IMPORT_PASSWORD) {
    die('Access denied. Use: ?password=' . $IMPORT_PASSWORD);
}

echo "<h2>Database Import for Live Server</h2>";

// Include config to get live database credentials
require_once 'api/config.php';

echo "<h3>Step 1: Connection Test</h3>";
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "✅ Connected to live database successfully<br>";
    echo "Database: $db on $host<br>";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

echo "<h3>Step 2: Create room_maps Table</h3>";
try {
    // Check if table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'room_maps'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "⚠️ room_maps table already exists<br>";
        if (isset($_GET['force']) && $_GET['force'] === 'yes') {
            echo "🔄 Dropping existing table (force mode)...<br>";
            $pdo->exec("DROP TABLE room_maps");
        } else {
            echo "Use ?force=yes to recreate the table<br>";
            echo "<a href='?password=$IMPORT_PASSWORD&force=yes'>Click here to force recreate table</a><br>";
            exit;
        }
    }
    
    // Create the table
    $createTableSQL = "
    CREATE TABLE `room_maps` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `room_type` varchar(50) NOT NULL,
      `map_name` varchar(100) NOT NULL,
      `coordinates` text NOT NULL,
      `is_active` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_room_type` (`room_type`),
      KEY `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($createTableSQL);
    echo "✅ room_maps table created successfully<br>";
    
} catch (PDOException $e) {
    die("❌ Table creation failed: " . $e->getMessage());
}

echo "<h3>Step 3: Test Table Access</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM room_maps");
    $result = $stmt->fetch();
    echo "✅ Table access test passed. Current records: " . $result['count'] . "<br>";
} catch (PDOException $e) {
    die("❌ Table access test failed: " . $e->getMessage());
}

echo "<h3>Step 4: Test API Endpoints</h3>";
echo "Testing room mapping API endpoints:<br>";

// Test get_room_coordinates.php
$testUrl = 'api/get_room_coordinates.php?room_type=room_tshirts';
echo "Testing: <a href='$testUrl' target='_blank'>$testUrl</a><br>";

// Test room_maps.php
$testUrl2 = 'api/room_maps.php?room_type=landing';
echo "Testing: <a href='$testUrl2' target='_blank'>$testUrl2</a><br>";

echo "<h3>✅ Import Complete!</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>✅ room_maps table has been created on your live server</li>";
echo "<li>✅ Your Room Mapper should now work without 500 errors</li>";
echo "<li>🔧 Go to your admin settings and test the Room Mapper</li>";
echo "<li>🗑️ <strong>IMPORTANT:</strong> Delete this import file for security!</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Security Notice:</strong> Please delete this file (import_to_live_server.php) after use!</p>";
?> 