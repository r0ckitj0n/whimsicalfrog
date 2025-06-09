<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set page title
$pageTitle = "Direct Edit Test";

// Debug information
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$pageTitle}</title>
    <link href='https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css' rel='stylesheet'>
    <style>
        .debug-panel {
            position: fixed;
            top: 0;
            right: 0;
            background: #ffe8cc;
            border-left: 4px solid #ed8936;
            padding: 10px;
            max-width: 300px;
            z-index: 9999;
            font-size: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            max-height: 100vh;
        }
        .debug-panel h3 {
            margin-top: 0;
            color: #c05621;
            border-bottom: 1px solid #ed8936;
            padding-bottom: 5px;
        }
        .debug-panel pre {
            white-space: pre-wrap;
            font-size: 11px;
            background: #fff;
            padding: 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body class='bg-gray-100'>
    <div class='debug-panel'>
        <h3>Debug Information</h3>
        <p><strong>Test File:</strong> test_edit_direct.php</p>
        <p><strong>Purpose:</strong> Direct testing of admin_inventory.php with edit parameter</p>
        <p><strong>Edit ID:</strong> I001</p>
        <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
        <hr>
        <p><strong>GET Parameters:</strong></p>
        <pre>" . print_r($_GET, true) . "</pre>
    </div>";

// Simulate the required GET parameters for admin_inventory.php
$_GET['page'] = 'admin';
$_GET['section'] = 'inventory';
$_GET['edit'] = 'I001'; // Use I001 as it has cost breakdown data

echo "<div class='container mx-auto px-4 py-8 mt-12'>
    <div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h1 class='text-2xl font-bold text-green-700 mb-4'>Admin Inventory Direct Edit Test</h1>
        <p class='mb-4'>This page directly loads the admin_inventory.php file with edit=I001 parameter to test cost breakdown display.</p>
        <div class='bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4'>
            <p><strong>Testing:</strong> Simulating URL parameters ?page=admin&section=inventory&edit=I001</p>
            <p><strong>Expected:</strong> The edit modal should appear with cost breakdown section showing materials, labor, and energy costs.</p>
        </div>
    </div>";

// Capture output to check for errors
ob_start();
try {
    // Include the admin inventory file directly
    include_once __DIR__ . '/sections/admin_inventory.php';
} catch (Exception $e) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4'>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>
    </div>";
}
$output = ob_get_clean();

// Check if the cost breakdown section exists in the output
$hasCostBreakdown = strpos($output, 'Cost Breakdown') !== false;
$hasMaterials = strpos($output, 'cotton sheet') !== false;
$hasLabor = strpos($output, 'sewing a dress') !== false;
$hasEnergy = strpos($output, 'sewing machine power') !== false;

echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
    <h2 class='text-xl font-bold text-green-700 mb-4'>Test Results</h2>
    <ul class='list-disc pl-5 mb-4'>
        <li>Cost Breakdown Section: <span class='" . ($hasCostBreakdown ? 'text-green-600 font-bold' : 'text-red-600 font-bold') . "'>" . ($hasCostBreakdown ? 'FOUND' : 'NOT FOUND') . "</span></li>
        <li>Materials (cotton sheet): <span class='" . ($hasMaterials ? 'text-green-600 font-bold' : 'text-red-600 font-bold') . "'>" . ($hasMaterials ? 'FOUND' : 'NOT FOUND') . "</span></li>
        <li>Labor (sewing a dress): <span class='" . ($hasLabor ? 'text-green-600 font-bold' : 'text-red-600 font-bold') . "'>" . ($hasLabor ? 'FOUND' : 'NOT FOUND') . "</span></li>
        <li>Energy (sewing machine power): <span class='" . ($hasEnergy ? 'text-green-600 font-bold' : 'text-red-600 font-bold') . "'>" . ($hasEnergy ? 'FOUND' : 'NOT FOUND') . "</span></li>
    </ul>";

if (!$hasCostBreakdown || !$hasMaterials || !$hasLabor || !$hasEnergy) {
    echo "<div class='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4'>
        <p><strong>Troubleshooting:</strong></p>
        <ol class='list-decimal pl-5'>
            <li>Check if the cost breakdown section in admin_inventory.php is properly included in the output</li>
            <li>Verify that the database connection is working correctly</li>
            <li>Confirm that the inventory_materials, inventory_labor, and inventory_energy tables contain data for item I001</li>
            <li>Inspect any JavaScript errors in the browser console</li>
            <li>Check for CSS issues that might be hiding the cost breakdown section</li>
        </ol>
    </div>";
}

echo "</div>";

// Output the captured content
echo $output;

echo "</div></body></html>";
?>
