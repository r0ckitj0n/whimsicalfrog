<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Page title and styling
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Cost Breakdown Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            color: #333;
        }
        h1, h2, h3 {
            color: #556B2F;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow: auto;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #87ac3a;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .cost-totals {
            background-color: #edf2f7;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <h1>Cost Breakdown Test</h1>";

// Include database configuration
echo "<div class='section'>
    <h2>1. Database Configuration</h2>";

try {
    require_once __DIR__ . '/api/config.php';
    echo "<p class='success'>✅ Database configuration loaded successfully</p>";
    echo "<p>Environment: " . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "</p>";
    echo "<p>Database: $host/$db</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error loading database configuration: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Test database connection
echo "<div class='section'>
    <h2>2. Database Connection Test</h2>";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p class='success'>✅ Database connection successful</p>";
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Test inventory item existence
echo "<div class='section'>
    <h2>3. Inventory Item Test</h2>";

// Define the inventory ID to test
$testItemId = 'I001';

try {
    // Get item details - using the exact query from admin_inventory.php
    $itemStmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $itemStmt->execute([$testItemId]);
    $editItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($editItem) {
        echo "<p class='success'>✅ Found inventory item with ID: $testItemId</p>";
        echo "<table>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>";
        foreach ($editItem as $key => $value) {
            echo "<tr>
                <td>$key</td>
                <td>$value</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No inventory item found with ID: $testItemId</p>";
        echo "<p>Please choose a different inventory ID to test.</p>";
        exit;
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error fetching inventory item: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Test materials costs
echo "<div class='section'>
    <h2>4. Materials Cost Data</h2>";

try {
    // Get materials costs - using the exact query from admin_inventory.php
    $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
    $materialStmt->execute([$testItemId]);
    $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($materials)) {
        echo "<p class='success'>✅ Found " . count($materials) . " material cost entries</p>";
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Inventory ID</th>
                <th>Material Name</th>
                <th>Cost</th>
            </tr>";
        foreach ($materials as $material) {
            echo "<tr>
                <td>{$material['id']}</td>
                <td>{$material['inventoryId']}</td>
                <td>{$material['name']}</td>
                <td>\${$material['cost']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No material cost data found for item ID: $testItemId</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error fetching material costs: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test labor costs
echo "<div class='section'>
    <h2>5. Labor Cost Data</h2>";

try {
    // Get labor costs - using the exact query from admin_inventory.php
    $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
    $laborStmt->execute([$testItemId]);
    $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($labor)) {
        echo "<p class='success'>✅ Found " . count($labor) . " labor cost entries</p>";
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Inventory ID</th>
                <th>Description</th>
                <th>Cost</th>
            </tr>";
        foreach ($labor as $laborItem) {
            echo "<tr>
                <td>{$laborItem['id']}</td>
                <td>{$laborItem['inventoryId']}</td>
                <td>{$laborItem['description']}</td>
                <td>\${$laborItem['cost']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No labor cost data found for item ID: $testItemId</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error fetching labor costs: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test energy costs
echo "<div class='section'>
    <h2>6. Energy Cost Data</h2>";

try {
    // Get energy costs - using the exact query from admin_inventory.php
    $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
    $energyStmt->execute([$testItemId]);
    $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($energy)) {
        echo "<p class='success'>✅ Found " . count($energy) . " energy cost entries</p>";
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Inventory ID</th>
                <th>Description</th>
                <th>Cost</th>
            </tr>";
        foreach ($energy as $energyItem) {
            echo "<tr>
                <td>{$energyItem['id']}</td>
                <td>{$energyItem['inventoryId']}</td>
                <td>{$energyItem['description']}</td>
                <td>\${$energyItem['cost']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ No energy cost data found for item ID: $testItemId</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error fetching energy costs: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Calculate totals - using the exact code from admin_inventory.php
echo "<div class='section'>
    <h2>7. Cost Totals Calculation</h2>";

try {
    // Calculate material total
    $materialTotal = 0;
    foreach ($materials as $material) {
        $materialTotal += floatval($material['cost']);
    }
    
    // Calculate labor total
    $laborTotal = 0;
    foreach ($labor as $laborItem) {
        $laborTotal += floatval($laborItem['cost']);
    }
    
    // Calculate energy total
    $energyTotal = 0;
    foreach ($energy as $energyItem) {
        $energyTotal += floatval($energyItem['cost']);
    }
    
    // Calculate suggested cost
    $suggestedCost = $materialTotal + $laborTotal + $energyTotal;
    
    echo "<div class='cost-totals'>
        <p><strong>Materials Total:</strong> \$" . number_format($materialTotal, 2) . "</p>
        <p><strong>Labor Total:</strong> \$" . number_format($laborTotal, 2) . "</p>
        <p><strong>Energy Total:</strong> \$" . number_format($energyTotal, 2) . "</p>
        <p><strong>Suggested Cost:</strong> \$" . number_format($suggestedCost, 2) . "</p>
    </div>";
    
    // Compare with inventory cost price
    if (isset($editItem['costPrice'])) {
        $costPrice = floatval($editItem['costPrice']);
        $difference = $costPrice - $suggestedCost;
        $percentDiff = ($suggestedCost > 0) ? ($difference / $suggestedCost) * 100 : 0;
        
        echo "<div class='cost-totals' style='margin-top: 10px;'>
            <p><strong>Current Cost Price:</strong> \$" . number_format($costPrice, 2) . "</p>
            <p><strong>Difference:</strong> \$" . number_format($difference, 2) . " (" . number_format($percentDiff, 2) . "%)</p>
        </div>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error calculating totals: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test creating the costBreakdown array - using the exact code from admin_inventory.php
echo "<div class='section'>
    <h2>8. Cost Breakdown Array Structure</h2>";

try {
    $costBreakdown = [
        'materials' => $materials,
        'labor' => $labor,
        'energy' => $energy,
        'totals' => [
            'materialTotal' => $materialTotal,
            'laborTotal' => $laborTotal,
            'energyTotal' => $energyTotal,
            'suggestedCost' => $suggestedCost
        ]
    ];
    
    echo "<p class='success'>✅ Cost breakdown array created successfully</p>";
    echo "<pre>" . htmlspecialchars(print_r($costBreakdown, true)) . "</pre>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error creating cost breakdown array: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Conclusion
echo "<div class='section'>
    <h2>9. Conclusion</h2>";

if (!empty($materials) || !empty($labor) || !empty($energy)) {
    echo "<p class='success'>✅ Cost breakdown data is available in the database</p>";
    echo "<p>The queries used in admin_inventory.php are working correctly and returning data.</p>";
    echo "<p>If the cost breakdown is not showing in the admin interface, the issue might be:</p>
    <ul>
        <li>JavaScript not properly displaying the data</li>
        <li>A conditional check preventing the cost breakdown section from showing</li>
        <li>HTML/CSS issues hiding the cost breakdown section</li>
    </ul>";
} else {
    echo "<p class='error'>❌ No cost breakdown data found for this inventory item</p>";
    echo "<p>Consider adding test data to the inventory_materials, inventory_labor, and inventory_energy tables.</p>";
}
echo "</div>";

echo "</body></html>";
?>
