<?php
/**
 * Inventory Price Columns Migration Script
 * 
 * This script adds costPrice and retailPrice columns to the inventory table
 * and populates them with sample data.
 * 
 * IMPORTANT: Run this script only once on your production server.
 */

// Set error reporting for debugging but don't display errors to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Basic security: Check for a secret key or admin session
session_start();
$isAuthorized = false;

// Check if user is logged in as admin or provides the correct key
if (isset($_SESSION['user'])) {
    // Handle both JSON string and array formats for backward compatibility
    if (is_string($_SESSION['user'])) {
        $userData = json_decode($_SESSION['user'], true);
    } else {
        $userData = $_SESSION['user'];
    }
    
    $isAdmin = isset($userData['role']) && $userData['role'] === 'Admin';
    if ($isAdmin) {
        $isAuthorized = true;
    }
}

// Alternative: Allow access with a secret key in the URL
$secretKey = 'whimsicalfrog2025'; // Change this to a secure random string
if (isset($_GET['key']) && $_GET['key'] === $secretKey) {
    $isAuthorized = true;
}

// Define log file
$logFile = __DIR__ . '/migration_log.txt';

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Sample price data for inventory items
$samplePrices = [
    'I001' => ['costPrice' => 8.50, 'retailPrice' => 19.99], // T-Shirt
    'I002' => ['costPrice' => 6.25, 'retailPrice' => 14.99], // Tumbler 20oz
    'I003' => ['costPrice' => 3.75, 'retailPrice' => 12.99], // Artwork Canvas
    'I004' => ['costPrice' => 45.00, 'retailPrice' => 89.99], // Window Wrap
    'I005' => ['costPrice' => 7.50, 'retailPrice' => 16.99], // Tumbler 24oz
    'I006' => ['costPrice' => 4.25, 'retailPrice' => 9.99],  // New Item
    'I007' => ['costPrice' => 8.00, 'retailPrice' => 18.99], // Tumbler 30oz
    'I008' => ['costPrice' => 2.50, 'retailPrice' => 7.99]   // Test item
];

// Default values for items not in the sample data
$defaultPrices = ['costPrice' => 5.00, 'retailPrice' => 12.99];

// Initialize result array
$results = [
    'success' => false,
    'messages' => [],
    'errors' => []
];

// Process migration if authorized and form submitted
if ($isAuthorized && isset($_POST['run_migration'])) {
    try {
        // Include database configuration
        require_once __DIR__ . '/api/config.php';
        
        // Connect to database
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Check if columns already exist
        $stmt = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'costPrice'");
        $costPriceExists = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'retailPrice'");
        $retailPriceExists = $stmt->rowCount() > 0;
        
        // Add columns if they don't exist
        if (!$costPriceExists && !$retailPriceExists) {
            $pdo->exec("ALTER TABLE inventory 
                       ADD COLUMN costPrice DECIMAL(10,2) DEFAULT 0.00 AFTER reorderPoint, 
                       ADD COLUMN retailPrice DECIMAL(10,2) DEFAULT 0.00 AFTER costPrice");
            
            $results['messages'][] = "✅ Added costPrice and retailPrice columns to inventory table.";
            logMessage("Added costPrice and retailPrice columns to inventory table.");
        } else if (!$costPriceExists) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN costPrice DECIMAL(10,2) DEFAULT 0.00 AFTER reorderPoint");
            $results['messages'][] = "✅ Added costPrice column to inventory table.";
            logMessage("Added costPrice column to inventory table.");
        } else if (!$retailPriceExists) {
            $pdo->exec("ALTER TABLE inventory ADD COLUMN retailPrice DECIMAL(10,2) DEFAULT 0.00 AFTER costPrice");
            $results['messages'][] = "✅ Added retailPrice column to inventory table.";
            logMessage("Added retailPrice column to inventory table.");
        } else {
            $results['messages'][] = "ℹ️ Both price columns already exist in the inventory table.";
            logMessage("Both price columns already exist in the inventory table.");
        }
        
        // Get all inventory items
        $stmt = $pdo->query("SELECT id FROM inventory");
        $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update each item with sample prices
        $updateStmt = $pdo->prepare("UPDATE inventory SET costPrice = ?, retailPrice = ? WHERE id = ?");
        $updatedCount = 0;
        
        foreach ($inventoryItems as $item) {
            $id = $item['id'];
            
            // Use sample prices if available, otherwise use defaults
            $prices = $samplePrices[$id] ?? $defaultPrices;
            
            $updateStmt->execute([
                $prices['costPrice'],
                $prices['retailPrice'],
                $id
            ]);
            
            if ($updateStmt->rowCount() > 0) {
                $updatedCount++;
            }
        }
        
        $results['messages'][] = "✅ Updated prices for $updatedCount inventory items.";
        logMessage("Updated prices for $updatedCount inventory items.");
        
        // Set success flag
        $results['success'] = true;
        
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
        $results['errors'][] = $errorMessage;
        logMessage($errorMessage);
    } catch (Exception $e) {
        $errorMessage = "General error: " . $e->getMessage();
        $results['errors'][] = $errorMessage;
        logMessage($errorMessage);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whimsical Frog - Database Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        h1 {
            color: #87ac3a;
            border-bottom: 2px solid #87ac3a;
            padding-bottom: 10px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .message {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .button {
            background-color: #87ac3a;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .button:hover {
            background-color: #76953a;
        }
        .unauthorized {
            text-align: center;
            padding: 50px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #87ac3a;
            color: white;
        }
        code {
            background-color: #f5f5f5;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Whimsical Frog - Inventory Price Migration</h1>
        
        <?php if (!$isAuthorized): ?>
            <div class="unauthorized">
                <div class="message warning">
                    <p>⚠️ <strong>Authorization Required</strong></p>
                    <p>You must be logged in as an administrator or provide the correct secret key to run this migration.</p>
                    <p>If you are an administrator, please <a href="/?page=login">login</a> first.</p>
                    <p>Alternatively, you can access this page with the secret key: <code>?key=YOUR_SECRET_KEY</code></p>
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($_POST['run_migration'])): ?>
                <div class="results">
                    <h2>Migration Results</h2>
                    
                    <?php if ($results['success']): ?>
                        <div class="message success">
                            <p>✅ <strong>Migration completed successfully!</strong></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($results['messages'])): ?>
                        <div class="message info">
                            <h3>Messages:</h3>
                            <ul>
                                <?php foreach ($results['messages'] as $message): ?>
                                    <li><?php echo htmlspecialchars($message); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($results['errors'])): ?>
                        <div class="message error">
                            <h3>Errors:</h3>
                            <ul>
                                <?php foreach ($results['errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <h3>Sample Price Data</h3>
                    <table>
                        <tr>
                            <th>Item ID</th>
                            <th>Cost Price</th>
                            <th>Retail Price</th>
                        </tr>
                        <?php foreach ($samplePrices as $id => $prices): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($id); ?></td>
                                <td>$<?php echo number_format($prices['costPrice'], 2); ?></td>
                                <td>$<?php echo number_format($prices['retailPrice'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <p>
                        <a href="/?page=admin&section=inventory" class="button">Go to Inventory Management</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="migration-form">
                    <div class="message warning">
                        <p>⚠️ <strong>Warning:</strong> This script will modify your database structure. Please make sure you have a backup before proceeding.</p>
                    </div>
                    
                    <h2>Migration Details</h2>
                    <p>This script will:</p>
                    <ol>
                        <li>Add <code>costPrice</code> and <code>retailPrice</code> columns to the inventory table (if they don't exist)</li>
                        <li>Populate these columns with sample price data for existing inventory items</li>
                    </ol>
                    
                    <h3>Sample Price Data</h3>
                    <table>
                        <tr>
                            <th>Item ID</th>
                            <th>Cost Price</th>
                            <th>Retail Price</th>
                        </tr>
                        <?php foreach ($samplePrices as $id => $prices): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($id); ?></td>
                                <td>$<?php echo number_format($prices['costPrice'], 2); ?></td>
                                <td>$<?php echo number_format($prices['retailPrice'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['key']) ? '?key=' . htmlspecialchars($_GET['key']) : '')); ?>">
                        <p>
                            <input type="submit" name="run_migration" value="Run Migration" class="button" onclick="return confirm('Are you sure you want to run this migration?');">
                        </p>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
