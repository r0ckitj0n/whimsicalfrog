<?php
/**
 * create_missing_tables.php
 * 
 * Script to create missing cost breakdown tables for the Whimsical Frog inventory system
 * This script should be run once on the live server and then deleted for security
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'api/config.php';

// HTML header for better output formatting
echo '<!DOCTYPE html>
<html>
<head>
    <title>Create Missing Tables</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .container { max-width: 800px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 5px; }
        h1 { color: #556B2F; }
        .table-status { margin-bottom: 10px; padding: 10px; background: #fff; border-left: 4px solid #ddd; }
        .delete-warning { background: #fff3cd; padding: 15px; margin-top: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Whimsical Frog - Database Table Creation Tool</h1>';

// Function to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to create a table if it doesn't exist
function createTable($pdo, $tableName, $createSql) {
    echo "<div class='table-status'>";
    echo "<h3>Table: {$tableName}</h3>";
    
    if (tableExists($pdo, $tableName)) {
        echo "<p class='warning'>Table already exists. Skipping creation.</p>";
        return true;
    }
    
    try {
        $pdo->exec($createSql);
        echo "<p class='success'>Table created successfully!</p>";
        return true;
    } catch (PDOException $e) {
        echo "<p class='error'>Error creating table: " . htmlspecialchars($e->getMessage()) . "</p>";
        return false;
    }
}

// Function to insert sample data if table is empty
function insertSampleData($pdo, $tableName, $sampleData) {
    try {
        // Check if table is empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tableName}`");
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "<p>Table has {$count} records. Skipping sample data insertion.</p>";
            return true;
        }
        
        // Insert sample data
        $pdo->beginTransaction();
        foreach ($sampleData as $sql) {
            $pdo->exec($sql);
        }
        $pdo->commit();
        
        echo "<p class='success'>Sample data inserted successfully!</p>";
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<p class='error'>Error inserting sample data: " . htmlspecialchars($e->getMessage()) . "</p>";
        return false;
    }
}

try {
    // Create PDO connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p class='success'>Database connection established successfully.</p>";
    
    // Define table creation SQL statements
    $createTableSql = [
        'inventory_materials' => "CREATE TABLE `inventory_materials` (
            `id` int NOT NULL AUTO_INCREMENT,
            `inventoryId` varchar(16) DEFAULT NULL,
            `name` varchar(128) DEFAULT NULL,
            `cost` decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `inventoryId` (`inventoryId`),
            CONSTRAINT `inventory_materials_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'inventory_labor' => "CREATE TABLE `inventory_labor` (
            `id` int NOT NULL AUTO_INCREMENT,
            `inventoryId` varchar(16) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `cost` decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `inventoryId` (`inventoryId`),
            CONSTRAINT `inventory_labor_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'inventory_energy' => "CREATE TABLE `inventory_energy` (
            `id` int NOT NULL AUTO_INCREMENT,
            `inventoryId` varchar(16) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `cost` decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `inventoryId` (`inventoryId`),
            CONSTRAINT `inventory_energy_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'inventory_equipment' => "CREATE TABLE `inventory_equipment` (
            `id` int NOT NULL AUTO_INCREMENT,
            `inventoryId` varchar(16) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `cost` decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_inventoryId` (`inventoryId`),
            CONSTRAINT `inventory_equipment_ibfk_1` FOREIGN KEY (`inventoryId`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    // Create tables
    $allTablesCreated = true;
    foreach ($createTableSql as $tableName => $sql) {
        $result = createTable($pdo, $tableName, $sql);
        if (!$result) {
            $allTablesCreated = false;
        }
    }
    
    // Define sample data for each table if they're empty
    if ($allTablesCreated) {
        // Get existing inventory IDs
        $stmt = $pdo->query("SELECT id FROM inventory LIMIT 3");
        $inventoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($inventoryIds) > 0) {
            $firstId = $inventoryIds[0];
            $secondId = isset($inventoryIds[1]) ? $inventoryIds[1] : $firstId;
            $thirdId = isset($inventoryIds[2]) ? $inventoryIds[2] : $firstId;
            
            // Sample data SQL statements
            $sampleData = [
                'inventory_materials' => [
                    "INSERT INTO inventory_materials (inventoryId, name, cost) VALUES ('{$firstId}', 'Base Material', 5.00)",
                    "INSERT INTO inventory_materials (inventoryId, name, cost) VALUES ('{$firstId}', 'Decorative Elements', 1.50)",
                    "INSERT INTO inventory_materials (inventoryId, name, cost) VALUES ('{$secondId}', 'Primary Material', 3.75)"
                ],
                'inventory_labor' => [
                    "INSERT INTO inventory_labor (inventoryId, description, cost) VALUES ('{$firstId}', 'Assembly Time', 2.00)",
                    "INSERT INTO inventory_labor (inventoryId, description, cost) VALUES ('{$secondId}', 'Finishing Work', 1.50)"
                ],
                'inventory_energy' => [
                    "INSERT INTO inventory_energy (inventoryId, description, cost) VALUES ('{$firstId}', 'Machine Operation', 0.75)",
                    "INSERT INTO inventory_energy (inventoryId, description, cost) VALUES ('{$secondId}', 'Heating Process', 1.25)"
                ],
                'inventory_equipment' => [
                    "INSERT INTO inventory_equipment (inventoryId, description, cost) VALUES ('{$firstId}', 'Tool Usage', 0.50)"
                ]
            ];
            
            // Insert sample data
            echo "<h2>Inserting Sample Data</h2>";
            foreach ($sampleData as $tableName => $data) {
                insertSampleData($pdo, $tableName, $data);
            }
        } else {
            echo "<p class='warning'>No inventory items found. Skipping sample data insertion.</p>";
        }
    }
    
    // Final status
    if ($allTablesCreated) {
        echo "<h2 class='success'>All tables have been created successfully!</h2>";
    } else {
        echo "<h2 class='error'>There were errors creating some tables. Please check the details above.</h2>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Security reminder
echo '<div class="delete-warning">
    <h3>⚠️ IMPORTANT SECURITY NOTICE</h3>
    <p>This script has completed its task and should be <strong>deleted immediately</strong> to prevent security risks.</p>
    <p>Please delete this file from your server now.</p>
</div>';

echo '</div></body></html>';
?>
