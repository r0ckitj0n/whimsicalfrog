<?php
// Database diagnostic script for WhimsicalFrog live server
// This will help identify the correct database configuration

echo "<h2>🔍 Database Configuration Diagnostic</h2>\n";

// Check if we can find existing database connections in other files
echo "<h3>📋 Checking Existing Database Connections</h3>\n";

$filesToCheck = [
    'config.php',
    'db_config.php',
    'database.php',
    'connection.php',
    'api/get_products.php',
    'api/upload_image.php',
    'process_multi_image_upload.php'
];

$foundConnections = [];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Look for database connection patterns
        if (preg_match('/new PDO\(["\']mysql:host=([^;]+);.*["\'],\s*["\']([^"\']+)["\'],\s*["\']([^"\']+)["\']/', $content, $matches)) {
            $foundConnections[] = [
                'file' => $file,
                'host' => $matches[1],
                'username' => $matches[2],
                'password' => $matches[3]
            ];
            echo "<p>✅ Found PDO connection in: <strong>$file</strong></p>\n";
            echo "<p>&nbsp;&nbsp;&nbsp;Host: {$matches[1]}</p>\n";
            echo "<p>&nbsp;&nbsp;&nbsp;Username: {$matches[2]}</p>\n";
            echo "<p>&nbsp;&nbsp;&nbsp;Password: " . str_repeat('*', strlen($matches[3])) . "</p>\n";
        }
        
        // Look for mysqli connections
        if (preg_match('/mysqli_connect\(["\']([^"\']+)["\'],\s*["\']([^"\']+)["\'],\s*["\']([^"\']+)["\']/', $content, $matches)) {
            $foundConnections[] = [
                'file' => $file,
                'host' => $matches[1],
                'username' => $matches[2],
                'password' => $matches[3]
            ];
            echo "<p>✅ Found mysqli connection in: <strong>$file</strong></p>\n";
            echo "<p>&nbsp;&nbsp;&nbsp;Host: {$matches[1]}</p>\n";
            echo "<p>&nbsp;&nbsp;&nbsp;Username: {$matches[2]}</p>\n";
            echo "<p>&nbsp;&nbsp;&nbsp;Password: " . str_repeat('*', strlen($matches[3])) . "</p>\n";
        }
    }
}

if (empty($foundConnections)) {
    echo "<p>⚠️ No existing database connections found in common files</p>\n";
}

echo "<hr>\n";
echo "<h3>🧪 Testing Database Connections</h3>\n";

// If we found connections, test them
if (!empty($foundConnections)) {
    foreach ($foundConnections as $index => $conn) {
        echo "<h4>Testing connection from {$conn['file']}:</h4>\n";
        
        try {
            $dsn = "mysql:host={$conn['host']};charset=utf8mb4";
            $pdo = new PDO($dsn, $conn['username'], $conn['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<p>✅ Connection successful!</p>\n";
            
            // Try to list databases
            try {
                $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
                echo "<p>📊 Available databases:</p>\n";
                echo "<ul>\n";
                foreach ($databases as $db) {
                    echo "<li>$db</li>\n";
                }
                echo "</ul>\n";
                
                // Check if whimsicalfrog database exists
                if (in_array('whimsicalfrog', $databases)) {
                    echo "<p>✅ 'whimsicalfrog' database found!</p>\n";
                    
                    // Connect to the specific database
                    $dsn = "mysql:host={$conn['host']};dbname=whimsicalfrog;charset=utf8mb4";
                    $pdo = new PDO($dsn, $conn['username'], $conn['password']);
                    
                    // Check existing tables
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    echo "<p>📋 Existing tables in whimsicalfrog:</p>\n";
                    echo "<ul>\n";
                    foreach ($tables as $table) {
                        echo "<li>$table</li>\n";
                    }
                    echo "</ul>\n";
                    
                    // Check if our tables already exist
                    $hasCategories = in_array('categories', $tables);
                    $hasAssignments = in_array('room_category_assignments', $tables);
                    
                    if ($hasCategories && $hasAssignments) {
                        echo "<p>🎉 Room category tables already exist!</p>\n";
                        
                        $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                        $assignmentCount = $pdo->query("SELECT COUNT(*) FROM room_category_assignments")->fetchColumn();
                        
                        echo "<p>📊 Categories: $categoryCount</p>\n";
                        echo "<p>📊 Assignments: $assignmentCount</p>\n";
                        
                        if ($categoryCount > 0 && $assignmentCount > 0) {
                            echo "<p>✅ <strong>System is already set up and ready to use!</strong></p>\n";
                        } else {
                            echo "<p>⚠️ Tables exist but are empty - need to populate with data</p>\n";
                        }
                    } else {
                        echo "<p>⚠️ Room category tables need to be created</p>\n";
                        echo "<p>Missing: ";
                        if (!$hasCategories) echo "categories ";
                        if (!$hasAssignments) echo "room_category_assignments ";
                        echo "</p>\n";
                    }
                    
                } else {
                    echo "<p>❌ 'whimsicalfrog' database not found</p>\n";
                }
                
            } catch (PDOException $e) {
                echo "<p>⚠️ Could not list databases: " . $e->getMessage() . "</p>\n";
            }
            
        } catch (PDOException $e) {
            echo "<p>❌ Connection failed: " . $e->getMessage() . "</p>\n";
        }
        
        echo "<hr>\n";
    }
} else {
    echo "<p>No existing connections found to test.</p>\n";
}

echo "<h3>🔧 Server Information</h3>\n";
echo "<ul>\n";
echo "<li>PHP Version: " . phpversion() . "</li>\n";
echo "<li>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>\n";
echo "<li>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</li>\n";
echo "<li>Script Path: " . __FILE__ . "</li>\n";
echo "<li>Current Directory: " . getcwd() . "</li>\n";
echo "</ul>\n";

echo "<h3>📝 Next Steps</h3>\n";
echo "<ul>\n";
echo "<li>If a working connection was found above, we can proceed with setup</li>\n";
echo "<li>If no working connection found, check with your hosting provider</li>\n";
echo "<li>Look for database configuration in your hosting control panel</li>\n";
echo "<li>Check if MySQL service is running on your server</li>\n";
echo "</ul>\n";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3, h4 { color: #333; }
p { margin: 5px 0; }
ul { margin: 10px 0; }
hr { margin: 20px 0; }
li { margin: 2px 0; }
</style> 