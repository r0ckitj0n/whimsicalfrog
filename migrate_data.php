<?php
// migrate_data.php - Migrates data from Google Sheets API to MySQL database
// This script fetches product and inventory data from the Render service API
// and inserts it into the MySQL database tables

// Set maximum execution time to 5 minutes (300 seconds)
ini_set('max_execution_time', 300);
set_time_limit(300);

// Database connection configuration
$host_name = 'db5017975223.hosting-data.io';
$database = 'dbs14295502';
$user_name = 'dbu2826619';
$password = 'Palz2516!';

// API endpoints
$productsApiUrl = 'https://whimsicalfrog.onrender.com/api/products';
$inventoryApiUrl = 'https://whimsicalfrog.onrender.com/api/inventory';

// HTML header for better output formatting
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Migration - Google Sheets to MySQL</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .container { max-width: 800px; margin: 0 auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .progress { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Data Migration: Google Sheets to MySQL</h1>';

// Create database connection
$conn = new mysqli($host_name, $user_name, $password, $database);

// Check connection
if ($conn->connect_error) {
    die('<p class="error">Failed to connect to MySQL: ' . $conn->connect_error . '</p></div></body></html>');
}
echo '<p class="success">Connected successfully to MySQL database!</p>';

// Function to fetch data from API
function fetchApiData($url) {
    echo "<p>Fetching data from: $url</p>";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30  // 30 second timeout
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo '<p class="error">Failed to fetch data from API. Error: ' . error_get_last()['message'] . '</p>';
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || !is_array($data)) {
        echo '<p class="error">Invalid data format received from API</p>';
        return null;
    }
    
    return $data;
}

// STEP 1: Fetch Products Data
echo '<h2>Step 1: Fetching Products Data</h2>';
$productsData = fetchApiData($productsApiUrl);

if (!$productsData) {
    die('<p class="error">Failed to fetch products data. Migration aborted.</p></div></body></html>');
}

echo '<p class="success">Successfully fetched products data!</p>';
echo '<p>Total products: ' . (count($productsData) - 1) . ' (excluding header row)</p>';

// STEP 2: Fetch Inventory Data
echo '<h2>Step 2: Fetching Inventory Data</h2>';
$inventoryData = fetchApiData($inventoryApiUrl);

if (!$inventoryData) {
    die('<p class="error">Failed to fetch inventory data. Migration aborted.</p></div></body></html>');
}

echo '<p class="success">Successfully fetched inventory data!</p>';
echo '<p>Total inventory items: ' . (count($inventoryData) - 1) . ' (excluding header row)</p>';

// STEP 3: Clear existing data (optional, with confirmation)
echo '<h2>Step 3: Preparing Database</h2>';

if (isset($_GET['clear']) && $_GET['clear'] === 'yes') {
    // Disable foreign key checks temporarily
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    
    // Truncate tables
    $truncateInventory = $conn->query('TRUNCATE TABLE inventory');
    $truncateProducts = $conn->query('TRUNCATE TABLE products');
    
    // Re-enable foreign key checks
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    
    if ($truncateInventory && $truncateProducts) {
        echo '<p class="success">Existing data cleared successfully!</p>';
    } else {
        echo '<p class="error">Error clearing existing data: ' . $conn->error . '</p>';
    }
} else {
    echo '<p class="info">Skipping data clearing. <a href="?clear=yes">Click here</a> to clear existing data before migration.</p>';
}

// STEP 4: Insert Products Data
echo '<h2>Step 4: Migrating Products Data</h2>';

// Extract headers from first row
$productHeaders = $productsData[0];

// Find column indexes
$productIdIndex = array_search('ProductID', $productHeaders);
$productNameIndex = array_search('ProductName', $productHeaders);
$productTypeIndex = array_search('ProductType', $productHeaders);
$basePriceIndex = array_search('BasePrice', $productHeaders);
$descriptionIndex = array_search('Description', $productHeaders);
$defaultSkuIndex = array_search('DefaultSKU_Base', $productHeaders);
$supplierIndex = array_search('Supplier', $productHeaders);
$notesIndex = array_search('Notes', $productHeaders);
$imageIndex = array_search('Image', $productHeaders);

// Prepare insert statement
$productInsertStmt = $conn->prepare("INSERT IGNORE INTO products 
    (ProductID, ProductName, ProductType, BasePrice, Description, DefaultSKU_Base, Supplier, Notes, Image) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$productInsertStmt) {
    die('<p class="error">Error preparing product insert statement: ' . $conn->error . '</p></div></body></html>');
}

// Bind parameters
$productInsertStmt->bind_param(
    'sssdsssss', 
    $productId, $productName, $productType, $basePrice, $description, 
    $defaultSku, $supplier, $notes, $image
);

// Insert products (skip header row)
$productSuccessCount = 0;
$productErrorCount = 0;

echo '<div class="progress">Processing products: <span id="product-progress">0</span>/' . (count($productsData) - 1) . '</div>';

for ($i = 1; $i < count($productsData); $i++) {
    $row = $productsData[$i];
    
    // Skip if no product ID
    if (empty($row[$productIdIndex])) {
        continue;
    }
    
    // Extract data
    $productId = $row[$productIdIndex];
    $productName = $row[$productNameIndex] ?? '';
    $productType = $row[$productTypeIndex] ?? '';
    $basePrice = !empty($row[$basePriceIndex]) ? floatval($row[$basePriceIndex]) : 0;
    $description = $row[$descriptionIndex] ?? '';
    $defaultSku = $row[$defaultSkuIndex] ?? '';
    $supplier = $row[$supplierIndex] ?? '';
    $notes = $row[$notesIndex] ?? '';
    $image = $row[$imageIndex] ?? '';
    
    // Execute insert
    if ($productInsertStmt->execute()) {
        $productSuccessCount++;
    } else {
        $productErrorCount++;
        echo '<p class="error">Error inserting product ' . htmlspecialchars($productId) . ': ' . $productInsertStmt->error . '</p>';
    }
    
    // Update progress
    echo '<script>document.getElementById("product-progress").textContent = "' . $productSuccessCount . '";</script>';
    ob_flush();
    flush();
}

$productInsertStmt->close();
echo '<p class="success">Products migration completed! Inserted: ' . $productSuccessCount . ', Errors: ' . $productErrorCount . '</p>';

// STEP 5: Insert Inventory Data
echo '<h2>Step 5: Migrating Inventory Data</h2>';

// Extract headers from first row
$inventoryHeaders = $inventoryData[0];

// Find column indexes
$inventoryIdIndex = array_search('InventoryID', $inventoryHeaders);
$invProductIdIndex = array_search('ProductID', $inventoryHeaders);
$invProductNameIndex = array_search('ProductName', $inventoryHeaders);
$categoryIndex = array_search('Category', $inventoryHeaders);
$invDescriptionIndex = array_search('Description', $inventoryHeaders);
$skuIndex = array_search('SKU', $inventoryHeaders);
$stockLevelIndex = array_search('StockLevel', $inventoryHeaders);
$reorderPointIndex = array_search('ReorderPoint', $inventoryHeaders);
$imageUrlIndex = array_search('ImageURL', $inventoryHeaders);

// Prepare insert statement
$inventoryInsertStmt = $conn->prepare("INSERT IGNORE INTO inventory 
    (InventoryID, ProductID, ProductName, Category, Description, SKU, StockLevel, ReorderPoint, ImageURL) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$inventoryInsertStmt) {
    die('<p class="error">Error preparing inventory insert statement: ' . $conn->error . '</p></div></body></html>');
}

// Bind parameters
$inventoryInsertStmt->bind_param(
    'ssssssiss', 
    $inventoryId, $invProductId, $invProductName, $category, $invDescription, 
    $sku, $stockLevel, $reorderPoint, $imageUrl
);

// Insert inventory (skip header row)
$inventorySuccessCount = 0;
$inventoryErrorCount = 0;

echo '<div class="progress">Processing inventory items: <span id="inventory-progress">0</span>/' . (count($inventoryData) - 1) . '</div>';

for ($i = 1; $i < count($inventoryData); $i++) {
    $row = $inventoryData[$i];
    
    // Skip if no inventory ID
    if (empty($row[$inventoryIdIndex])) {
        continue;
    }
    
    // Extract data
    $inventoryId = $row[$inventoryIdIndex];
    $invProductId = $row[$invProductIdIndex] ?? '';
    $invProductName = $row[$invProductNameIndex] ?? '';
    $category = $row[$categoryIndex] ?? '';
    $invDescription = $row[$invDescriptionIndex] ?? '';
    $sku = $row[$skuIndex] ?? '';
    $stockLevel = !empty($row[$stockLevelIndex]) ? intval($row[$stockLevelIndex]) : 0;
    $reorderPoint = !empty($row[$reorderPointIndex]) ? intval($row[$reorderPointIndex]) : 5;
    $imageUrl = $row[$imageUrlIndex] ?? '';
    
    // Execute insert
    if ($inventoryInsertStmt->execute()) {
        $inventorySuccessCount++;
    } else {
        $inventoryErrorCount++;
        echo '<p class="error">Error inserting inventory ' . htmlspecialchars($inventoryId) . ': ' . $inventoryInsertStmt->error . '</p>';
        
        // If error is due to foreign key constraint, show more details
        if (strpos($inventoryInsertStmt->error, 'foreign key constraint') !== false) {
            echo '<p class="warning">This error is likely because the product ID "' . htmlspecialchars($invProductId) . 
                 '" does not exist in the products table. Check that all inventory items reference valid products.</p>';
        }
    }
    
    // Update progress
    echo '<script>document.getElementById("inventory-progress").textContent = "' . $inventorySuccessCount . '";</script>';
    ob_flush();
    flush();
}

$inventoryInsertStmt->close();
echo '<p class="success">Inventory migration completed! Inserted: ' . $inventorySuccessCount . ', Errors: ' . $inventoryErrorCount . '</p>';

// STEP 6: Verify Migration
echo '<h2>Step 6: Verifying Migration</h2>';

// Count records in products table
$productCountResult = $conn->query("SELECT COUNT(*) AS count FROM products");
$productCount = $productCountResult->fetch_assoc()['count'];

// Count records in inventory table
$inventoryCountResult = $conn->query("SELECT COUNT(*) AS count FROM inventory");
$inventoryCount = $inventoryCountResult->fetch_assoc()['count'];

echo '<p>Products in database: ' . $productCount . ' (Expected: ' . $productSuccessCount . ')</p>';
echo '<p>Inventory items in database: ' . $inventoryCount . ' (Expected: ' . $inventorySuccessCount . ')</p>';

if ($productCount == $productSuccessCount && $inventoryCount == $inventorySuccessCount) {
    echo '<p class="success">Migration verification successful! All data migrated correctly.</p>';
} else {
    echo '<p class="warning">Migration verification warning: The number of records in the database does not match the number of records processed. This could be due to duplicate keys or other issues.</p>';
}

// Close connection
$conn->close();
echo '<p>Database connection closed.</p>';
echo '<h2 class="success">Data Migration Completed!</h2>';

// Add button to update server.js
echo '<div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">
    <h3>Next Steps</h3>
    <p>Now that your data is migrated to MySQL, you need to update your Node.js server to use MySQL instead of Google Sheets.</p>
    <p><a href="update_server.php" class="button" style="display: inline-block; padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">Update server.js to use MySQL</a></p>
</div>';

echo '</div></body></html>';
?>
