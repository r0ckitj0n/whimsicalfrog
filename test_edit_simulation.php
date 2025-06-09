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
    <title>Edit Item Simulation Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            color: #333;
            background-color: #f9fafb;
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
        .debug-section {
            background-color: #fffbea;
            border: 1px solid #f0b429;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .error {
            color: #e53e3e;
            font-weight: bold;
        }
        .success {
            color: #38a169;
            font-weight: bold;
        }
        
        /* Cost Breakdown Styles from admin_inventory.php */
        .cost-breakdown {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            border: 1px solid #e2e8f0;
        }
        .cost-breakdown h3 {
            color: #4a5568;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .cost-breakdown-section {
            margin-bottom: 16px;
        }
        .cost-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .cost-item:last-child {
            border-bottom: none;
        }
        .cost-item-name {
            font-weight: 500;
        }
        .cost-item-value {
            font-weight: 600;
            color: #4a5568;
        }
        .cost-totals {
            background-color: #edf2f7;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
        }
        .cost-total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .suggested-cost {
            color: #805ad5;
            font-weight: 600;
            margin-left: 8px;
        }
        .cost-label {
            font-size: 14px;
            color: #718096;
        }
        
        /* Modal Simulation */
        .modal-simulation {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            max-width: 800px;
            margin: 20px auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: bold;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <h1>Edit Item Simulation Test</h1>
    <p>This script simulates clicking the edit button for inventory item I001 and displays the cost breakdown section.</p>";

// Debug section to show what we're testing
echo "<div class='debug-section'>
    <h2>Test Configuration</h2>
    <p>Simulating <code>\$_GET['edit'] = 'I001'</code> to test cost breakdown display</p>
</div>";

// Simulate the edit parameter
$_GET['edit'] = 'I001';
$editItemId = $_GET['edit'];

echo "<div class='debug-section'>
    <h2>Simulated Parameters</h2>
    <p>Edit Item ID: {$editItemId}</p>
</div>";

// Include database configuration
try {
    require_once __DIR__ . '/api/config.php';
    echo "<p class='success'>✅ Database configuration loaded successfully</p>";
    echo "<p>Environment: " . ($isLocalhost ? "LOCAL" : "PRODUCTION") . "</p>";
    echo "<p>Database: $host/$db</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error loading database configuration: " . $e->getMessage() . "</p>";
    exit;
}

// Connect to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p class='success'>✅ Database connection successful</p>";
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Get item details - using the exact code from admin_inventory.php
try {
    // Get item details
    $itemStmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $itemStmt->execute([$editItemId]);
    $editItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$editItem) {
        echo "<p class='error'>❌ No inventory item found with ID: {$editItemId}</p>";
        exit;
    }
    
    echo "<p class='success'>✅ Found inventory item: {$editItem['name']} (ID: {$editItem['id']})</p>";
    
    // Get materials costs
    $materialStmt = $pdo->prepare("SELECT * FROM inventory_materials WHERE inventoryId = ?");
    $materialStmt->execute([$editItemId]);
    $materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get labor costs
    $laborStmt = $pdo->prepare("SELECT * FROM inventory_labor WHERE inventoryId = ?");
    $laborStmt->execute([$editItemId]);
    $labor = $laborStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get energy costs
    $energyStmt = $pdo->prepare("SELECT * FROM inventory_energy WHERE inventoryId = ?");
    $energyStmt->execute([$editItemId]);
    $energy = $energyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $materialTotal = 0;
    foreach ($materials as $material) {
        $materialTotal += floatval($material['cost']);
    }
    
    $laborTotal = 0;
    foreach ($labor as $laborItem) {
        $laborTotal += floatval($laborItem['cost']);
    }
    
    $energyTotal = 0;
    foreach ($energy as $energyItem) {
        $energyTotal += floatval($energyItem['cost']);
    }
    
    $suggestedCost = $materialTotal + $laborTotal + $energyTotal;
    
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
    
    echo "<p class='success'>✅ Cost breakdown data retrieved successfully</p>";
    
    // Debug the cost breakdown array structure
    echo "<div class='debug-section'>
        <h2>Cost Breakdown Array Structure</h2>
        <pre>" . htmlspecialchars(print_r($costBreakdown, true)) . "</pre>
    </div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
    exit;
}

// Simulate the edit modal with cost breakdown section
echo "<div class='modal-simulation'>
    <div class='modal-header'>
        <div class='modal-title'>Edit Item: {$editItem['name']}</div>
        <a href='#' class='close-btn'>×</a>
    </div>
    
    <form method='POST' action='' class='space-y-4'>
        <!-- Basic item fields would go here in the real form -->
        <div style='margin-bottom: 20px;'>
            <p><strong>Item ID:</strong> {$editItem['id']}</p>
            <p><strong>Name:</strong> {$editItem['name']}</p>
            <p><strong>Category:</strong> {$editItem['category']}</p>
            <p><strong>Current Cost Price:</strong> \${$editItem['costPrice']}</p>
            <p><strong>Current Retail Price:</strong> \${$editItem['retailPrice']}</p>
        </div>";

// This is the exact cost breakdown section from admin_inventory.php
if (isset($costBreakdown)) {
    echo "<div class='cost-breakdown'>
        <h3>Cost Breakdown</h3>
        
        <!-- Materials Section -->
        <div class='cost-breakdown-section'>
            <h4 class='font-semibold text-gray-700 mb-2'>Materials</h4>
            <div class='mb-2'>";
            if (!empty($costBreakdown['materials'])) {
                foreach ($costBreakdown['materials'] as $material) {
                    echo "<div class='cost-item'>
                        <span class='cost-item-name'>" . htmlspecialchars($material['name']) . "</span>
                        <span class='cost-item-value'>$" . number_format(floatval($material['cost']), 2) . "</span>
                    </div>";
                }
            } else {
                echo "<div class='text-gray-500 text-sm italic'>No materials data available</div>";
            }
    echo "</div>
        </div>
        
        <!-- Labor Section -->
        <div class='cost-breakdown-section'>
            <h4 class='font-semibold text-gray-700 mb-2'>Labor</h4>
            <div class='mb-2'>";
            if (!empty($costBreakdown['labor'])) {
                foreach ($costBreakdown['labor'] as $laborItem) {
                    echo "<div class='cost-item'>
                        <span class='cost-item-name'>" . htmlspecialchars($laborItem['description']) . "</span>
                        <span class='cost-item-value'>$" . number_format(floatval($laborItem['cost']), 2) . "</span>
                    </div>";
                }
            } else {
                echo "<div class='text-gray-500 text-sm italic'>No labor data available</div>";
            }
    echo "</div>
        </div>
        
        <!-- Energy Section -->
        <div class='cost-breakdown-section'>
            <h4 class='font-semibold text-gray-700 mb-2'>Energy</h4>
            <div class='mb-2'>";
            if (!empty($costBreakdown['energy'])) {
                foreach ($costBreakdown['energy'] as $energyItem) {
                    echo "<div class='cost-item'>
                        <span class='cost-item-name'>" . htmlspecialchars($energyItem['description']) . "</span>
                        <span class='cost-item-value'>$" . number_format(floatval($energyItem['cost']), 2) . "</span>
                    </div>";
                }
            } else {
                echo "<div class='text-gray-500 text-sm italic'>No energy data available</div>";
            }
    echo "</div>
        </div>
        
        <!-- Totals Section -->
        <div class='cost-totals'>
            <div class='cost-total-row'>
                <span class='cost-label'>Materials Total:</span>
                <span class='cost-item-value'>$" . number_format($costBreakdown['totals']['materialTotal'], 2) . "</span>
            </div>
            <div class='cost-total-row'>
                <span class='cost-label'>Labor Total:</span>
                <span class='cost-item-value'>$" . number_format($costBreakdown['totals']['laborTotal'], 2) . "</span>
            </div>
            <div class='cost-total-row'>
                <span class='cost-label'>Energy Total:</span>
                <span class='cost-item-value'>$" . number_format($costBreakdown['totals']['energyTotal'], 2) . "</span>
            </div>
            <div class='cost-total-row border-t border-gray-300 pt-2 mt-2'>
                <span class='font-semibold'>Suggested Cost:</span>
                <span class='font-bold text-purple-700'>$" . number_format($costBreakdown['totals']['suggestedCost'], 2) . "</span>
            </div>
            <div class='mt-2 text-sm text-gray-600'>
                <button type='button' onclick=\"alert('This would update the cost price field in the real form')\" class='text-blue-600 hover:text-blue-800 underline'>Use suggested cost</button>
            </div>
        </div>
    </div>";
} else {
    echo "<div class='error'>❌ Cost breakdown data is not available</div>";
}

echo "
        <div class='flex justify-end space-x-3' style='margin-top: 20px;'>
            <button type='button' class='px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400'>Cancel</button>
            <button type='button' class='px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700'>Save Item</button>
        </div>
    </form>
</div>";

// Conclusion
echo "<div class='debug-section'>
    <h2>Test Results</h2>";

if (isset($costBreakdown) && !empty($costBreakdown['materials']) || !empty($costBreakdown['labor']) || !empty($costBreakdown['energy'])) {
    echo "<p class='success'>✅ Cost breakdown data is available and displayed correctly</p>";
    echo "<p>The cost breakdown section is rendering properly with the following data:</p>
    <ul>
        <li>Materials: " . count($costBreakdown['materials']) . " items</li>
        <li>Labor: " . count($costBreakdown['labor']) . " items</li>
        <li>Energy: " . count($costBreakdown['energy']) . " items</li>
        <li>Suggested Cost: $" . number_format($costBreakdown['totals']['suggestedCost'], 2) . "</li>
    </ul>";
} else {
    echo "<p class='error'>❌ There was an issue with the cost breakdown data</p>";
    if (!isset($costBreakdown)) {
        echo "<p>The costBreakdown variable was not created.</p>";
    } else if (empty($costBreakdown['materials']) && empty($costBreakdown['labor']) && empty($costBreakdown['energy'])) {
        echo "<p>No cost items were found for this inventory item.</p>";
    }
}

echo "</div>

<div class='debug-section'>
    <h2>Possible Issues</h2>
    <p>If you're not seeing the cost breakdown in the actual admin interface, check for:</p>
    <ol>
        <li>JavaScript errors in the browser console that might prevent the modal from loading properly</li>
        <li>CSS issues that might be hiding the cost breakdown section</li>
        <li>Conditional checks in the PHP code that might be preventing the cost breakdown section from being included</li>
        <li>URL parameter issues - make sure the edit parameter is being passed correctly</li>
        <li>Database connection differences between development and production environments</li>
    </ol>
</div>

</body>
</html>";
?>
