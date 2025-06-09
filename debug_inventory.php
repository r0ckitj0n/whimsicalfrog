<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/config.php'; // Defines $dsn, $user, $pass, $options

$debug_messages = [];
$test_inventory_id = 'I001'; // Define a test inventory ID
$test_item_details = null;
$db_cost_breakdown = ['materials' => [], 'labor' => [], 'energy' => [], 'equipment' => []];
$php_to_js_cost_breakdown_data = ['materials' => [], 'labor' => [], 'energy' => [], 'equipment' => [], 'totals' => ['materialTotal' => 0, 'laborTotal' => 0, 'energyTotal' => 0, 'equipmentTotal' => 0, 'suggestedCost' => 0]];

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $debug_messages[] = "Successfully connected to the database.";

    // 1. Fetch test item details
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$test_inventory_id]);
    $test_item_details = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($test_item_details) {
        $debug_messages[] = "Fetched details for test item ID: " . $test_inventory_id;
    } else {
        $debug_messages[] = "Could not find test item ID: " . $test_inventory_id;
    }

    // 2. Fetch actual cost breakdown data from DB for the test item
    if ($test_item_details) {
        $cost_types_db = ['materials', 'labor', 'energy', 'equipment'];
        foreach ($cost_types_db as $type) {
            $table_name = "inventory_" . $type;
            // Check if table exists before querying
            $check_table_stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
            if ($check_table_stmt->rowCount() > 0) {
                $cost_sql = "SELECT * FROM $table_name WHERE inventoryId = ?";
                $cost_stmt = $pdo->prepare($cost_sql);
                $cost_stmt->execute([$test_inventory_id]);
                $db_cost_breakdown[$type] = $cost_stmt->fetchAll(PDO::FETCH_ASSOC);
                $debug_messages[] = "Fetched " . count($db_cost_breakdown[$type]) . " '$type' items from DB for " . $test_inventory_id;

                // Populate php_to_js_cost_breakdown_data as admin_inventory.php would
                $php_to_js_cost_breakdown_data[$type] = $db_cost_breakdown[$type];
                foreach ($db_cost_breakdown[$type] as $row) {
                    $php_to_js_cost_breakdown_data['totals'][lcfirst(ucfirst($type)) . 'Total'] += floatval($row['cost']);
                }
            } else {
                $debug_messages[] = "Table '$table_name' does not exist. Skipping '$type' costs.";
                $php_to_js_cost_breakdown_data[$type] = []; // Ensure it's an empty array
            }
        }
        $php_to_js_cost_breakdown_data['totals']['suggestedCost'] = 
            ($php_to_js_cost_breakdown_data['totals']['materialTotal'] ?? 0) +
            ($php_to_js_cost_breakdown_data['totals']['laborTotal'] ?? 0) +
            ($php_to_js_cost_breakdown_data['totals']['energyTotal'] ?? 0) +
            ($php_to_js_cost_breakdown_data['totals']['equipmentTotal'] ?? 0);
    }

} catch (PDOException $e) {
    $debug_messages[] = "Database Error: " . $e->getMessage();
} finally {
    $pdo = null;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Debug Page</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f4; }
        .section { background-color: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { font-size: 1.5em; margin-bottom: 10px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        pre { background-color: #2d2d2d; color: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .editable { cursor: pointer; background-color: #e6f7ff; }
        .editing input { width: 100%; box-sizing: border-box; }
        .toast-notification { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 4px; color: white; font-weight: 500; z-index: 9999; opacity: 0; transform: translateY(-20px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: opacity 0.3s, transform 0.3s; }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
        .toast-notification.success { background-color: #48bb78; }
        .toast-notification.error { background-color: #f56565; }
        .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #333; animation: spin 1s ease-in-out infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h1 class="text-2xl font-bold mb-6 text-center">Inventory System Debug Page</h1>

    <div class="section">
        <h2>PHP Startup & Database Connection Messages</h2>
        <?php if (!empty($debug_messages)): ?>
            <ul>
                <?php foreach ($debug_messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No startup messages.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>1. JavaScript Loading Test</h2>
        <div id="js-test-output" class="p-2 bg-yellow-100 text-yellow-700">JavaScript has not run yet.</div>
    </div>

    <div class="section">
        <h2>2. PHP Data Passed to JavaScript (costBreakdown variable)</h2>
        <p>This is what PHP prepares for the `costBreakdown` JavaScript variable. Check console for the JS object.</p>
        <pre id="php-to-js-data"><?php echo htmlspecialchars(json_encode($php_to_js_cost_breakdown_data, JSON_PRETTY_PRINT)); ?></pre>
    </div>

    <div class="section">
        <h2>3. Actual Cost Breakdown Data from Database (for item <?php echo htmlspecialchars($test_inventory_id); ?>)</h2>
        <?php if ($test_item_details): ?>
            <p><strong>Item:</strong> <?php echo htmlspecialchars($test_item_details['name']); ?></p>
            <?php foreach ($db_cost_breakdown as $type => $items): ?>
                <h3 class="text-lg font-semibold mt-2"><?php echo ucfirst($type); ?></h3>
                <?php if (!empty($items)): ?>
                    <table>
                        <thead><tr><th>ID</th><th>Name/Description</th><th>Cost</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($type === 'materials' ? $item['name'] : $item['description']); ?></td>
                                    <td>$<?php echo number_format(floatval($item['cost']), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No <?php echo $type; ?> costs found in DB for this item.</p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Test item <?php echo htmlspecialchars($test_inventory_id); ?> not found, so cannot display DB costs.</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>4. Isolated Editable Table Test</h2>
        <p>Click on a cell in the 'Value' column to edit. Saves to console only.</p>
        <table id="debug-editable-table">
            <thead><tr><th>Field</th><th>Value</th></tr></thead>
            <tbody>
                <tr data-id="debug1">
                    <td>Sample Text</td>
                    <td class="editable" data-field="sampleText">Initial Text</td>
                </tr>
                <tr data-id="debug2">
                    <td>Sample Number</td>
                    <td class="editable" data-field="sampleNumber">123</td>
                </tr>
                 <tr data-id="debug3">
                    <td>Sample Price</td>
                    <td class="editable" data-field="samplePrice">$10.50</td>
                </tr>
            </tbody>
        </table>
    </div>

<script>
    // Toast Notification Function
    function showToast(message, type = 'info') {
        console.log(`Toast: [${type}] ${message}`);
        const existingToast = document.querySelector('.toast-notification.show');
        if (existingToast) { existingToast.remove(); }
        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + type;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => { toast.classList.add('show'); }, 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { toast.remove(); }, 300);
        }, 3000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Debug Page: DOMContentLoaded event fired.');

        // Test 1: JavaScript Loading
        const jsTestOutput = document.getElementById('js-test-output');
        if (jsTestOutput) {
            jsTestOutput.textContent = 'JavaScript has successfully run!';
            jsTestOutput.classList.remove('bg-yellow-100', 'text-yellow-700');
            jsTestOutput.classList.add('bg-green-100', 'text-green-700');
            console.log('Debug Page: JavaScript Loading Test PASSED.');
        } else {
            console.error('Debug Page: JavaScript Loading Test FAILED - #js-test-output element not found.');
        }

        // Test 2: PHP Data to JS
        let costBreakdownDataFromPHP;
        try {
            const jsonDataElement = document.getElementById('php-to-js-data');
            if (jsonDataElement) {
                costBreakdownDataFromPHP = JSON.parse(jsonDataElement.textContent);
                console.log('Debug Page: costBreakdownDataFromPHP successfully parsed:', costBreakdownDataFromPHP);
            } else {
                console.error('Debug Page: Element #php-to-js-data not found. Cannot parse costBreakdownDataFromPHP.');
                costBreakdownDataFromPHP = { materials: [], labor: [], energy: [], equipment: [], totals: {} }; // Fallback
            }
        } catch (e) {
            console.error('Debug Page: Error parsing costBreakdownDataFromPHP from JSON:', e);
            costBreakdownDataFromPHP = { materials: [], labor: [], energy: [], equipment: [], totals: {} }; // Fallback
        }
        
        // Test 4: Isolated Editable Table
        const debugEditableTable = document.getElementById('debug-editable-table');
        if (debugEditableTable) {
            console.log('Debug Page: Initializing isolated editable table.');
            debugEditableTable.addEventListener('click', function(e) {
                const cell = e.target.closest('.editable');
                if (!cell || cell.classList.contains('editing-cell')) { // Prevent re-entry if already editing
                    return;
                }
                console.log('Debug Page: Editable cell clicked:', cell);

                const field = cell.dataset.field;
                const itemId = cell.parentNode.dataset.id;
                const originalValue = cell.innerText.trim();
                let valueForInput = originalValue;

                if (field === 'samplePrice') {
                    valueForInput = parseFloat(originalValue.replace('$', '')) || 0;
                } else if (field === 'sampleNumber') {
                     valueForInput = parseInt(originalValue, 10) || 0;
                }


                cell.classList.add('editing-cell'); // Mark as editing
                cell.dataset.originalContent = cell.innerHTML; // Store original HTML
                
                let inputElement;
                if (field === 'samplePrice') {
                    inputElement = document.createElement('input'); inputElement.type = 'number'; inputElement.step = '0.01';
                } else if (field === 'sampleNumber') {
                    inputElement = document.createElement('input'); inputElement.type = 'number';
                } else {
                    inputElement = document.createElement('input'); inputElement.type = 'text';
                }
                inputElement.value = valueForInput;
                inputElement.className = 'w-full p-1 border border-blue-500 rounded';

                cell.innerHTML = ''; // Clear the cell
                cell.appendChild(inputElement);
                inputElement.focus();
                inputElement.select();

                const saveDebugEdit = () => {
                    const newValue = inputElement.value;
                    console.log(`Debug Page: Save clicked for item ${itemId}, field ${field}. New value: ${newValue}`);
                    
                    let displayValue = newValue;
                     if (field === 'samplePrice') {
                        displayValue = '$' + parseFloat(newValue).toFixed(2);
                    }
                    cell.innerHTML = displayValue; // Display new value
                    cell.classList.remove('editing-cell');
                    showToast(`Debug: Saved ${field} for ${itemId} as ${newValue}`, 'success');
                };

                const cancelDebugEdit = () => {
                    console.log(`Debug Page: Cancel clicked for item ${itemId}, field ${field}.`);
                    cell.innerHTML = cell.dataset.originalContent; // Restore original HTML
                    cell.classList.remove('editing-cell');
                };

                inputElement.addEventListener('blur', function(e) {
                    // If the blur is due to clicking a save/cancel button, don't auto-save/cancel here
                    if (e.relatedTarget && (e.relatedTarget.classList.contains('debug-save-btn') || e.relatedTarget.classList.contains('debug-cancel-btn'))) {
                        return;
                    }
                    // Default to save on blur if no buttons are used, or handle as needed
                    // For this debug, let's just revert if not saved by button
                    if (cell.classList.contains('editing-cell')) { // Check if still in editing mode (i.e. buttons not clicked)
                       // cancelDebugEdit(); // Or save: saveDebugEdit();
                    }
                });

                inputElement.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveDebugEdit();
                    } else if (e.key === 'Escape') {
                        cancelDebugEdit();
                    }
                });
            });
        } else {
            console.error('Debug Page: #debug-editable-table not found.');
        }
        
        console.log('Debug Page: All JavaScript initializations complete.');
        showToast('Debug page loaded and JS initialized.', 'success');
    });
</script>

</body>
</html>
