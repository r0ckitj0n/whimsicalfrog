<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory API Test</title>
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
            max-height: 300px;
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
        .warning {
            color: orange;
            font-weight: bold;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .button {
            background-color: #87ac3a;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .button:hover {
            background-color: #6B8E23;
        }
    </style>
</head>
<body>
    <h1>Inventory API Test</h1>
    
    <div class="section">
        <h2>Test Configuration</h2>
        <p>This script tests the inventory API endpoint to ensure it's returning data in the expected format.</p>
        <p>API Endpoint: <code>process_inventory_get.php</code></p>
        <p>Expected Format: An array of inventory objects with properties like id, name, category, etc.</p>
        
        <button id="testButton" class="button">Run API Test</button>
    </div>
    
    <div id="results" class="section" style="display: none;">
        <h2>Test Results</h2>
        <div id="status"></div>
        
        <h3>Raw Response</h3>
        <pre id="rawResponse">Loading...</pre>
        
        <h3>Parsed Data</h3>
        <div id="parsedData">Loading...</div>
        
        <h3>Data Structure Analysis</h3>
        <div id="dataAnalysis">Loading...</div>
    </div>
    
    <div class="section">
        <h2>Expected JavaScript Usage</h2>
        <p>The admin_inventory.php page uses this code to fetch inventory:</p>
        <pre>
fetch('process_inventory_get.php?' + queryParams.toString())
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        displayInventory(data);
        updateStats(data);
        populateCategories(data);
    })
    .catch(error => {
        console.error('Error fetching inventory:', error);
        showError('Failed to load inventory. Please try again.');
    })
        </pre>
        <p>The JavaScript expects the response to be a direct array of inventory items, not wrapped in a success/data object.</p>
    </div>
    
    <script>
        document.getElementById('testButton').addEventListener('click', function() {
            document.getElementById('results').style.display = 'block';
            document.getElementById('status').innerHTML = '<p>Running test...</p>';
            document.getElementById('rawResponse').textContent = 'Loading...';
            document.getElementById('parsedData').innerHTML = 'Loading...';
            document.getElementById('dataAnalysis').innerHTML = 'Loading...';
            
            // Make API request
            fetch('process_inventory_get.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    // Display raw response
                    document.getElementById('rawResponse').textContent = text;
                    
                    try {
                        // Parse JSON
                        const data = JSON.parse(text);
                        
                        // Check if data is an array (expected format)
                        const isArray = Array.isArray(data);
                        
                        if (isArray) {
                            document.getElementById('status').innerHTML = 
                                '<p class="success">✅ SUCCESS: API returned data in the expected format (array).</p>';
                        } else if (data.success && Array.isArray(data.data)) {
                            document.getElementById('status').innerHTML = 
                                '<p class="warning">⚠️ WARNING: API returned data wrapped in a success/data object. ' +
                                'The JavaScript expects a direct array.</p>';
                        } else if (data.error) {
                            document.getElementById('status').innerHTML = 
                                `<p class="error">❌ ERROR: API returned an error: ${data.error}</p>`;
                        } else {
                            document.getElementById('status').innerHTML = 
                                '<p class="error">❌ ERROR: API returned data in an unexpected format.</p>';
                        }
                        
                        // Display parsed data in a table
                        const items = isArray ? data : (data.data || []);
                        if (items.length > 0) {
                            // Get all unique keys from all items
                            const allKeys = new Set();
                            items.forEach(item => {
                                Object.keys(item).forEach(key => allKeys.add(key));
                            });
                            
                            // Create table
                            let tableHtml = '<table><thead><tr>';
                            allKeys.forEach(key => {
                                tableHtml += `<th>${key}</th>`;
                            });
                            tableHtml += '</tr></thead><tbody>';
                            
                            items.forEach(item => {
                                tableHtml += '<tr>';
                                allKeys.forEach(key => {
                                    const value = item[key] !== undefined ? item[key] : '';
                                    tableHtml += `<td>${value}</td>`;
                                });
                                tableHtml += '</tr>';
                            });
                            
                            tableHtml += '</tbody></table>';
                            document.getElementById('parsedData').innerHTML = tableHtml;
                        } else {
                            document.getElementById('parsedData').innerHTML = '<p>No inventory items found.</p>';
                        }
                        
                        // Analyze data structure
                        let analysisHtml = '<ul>';
                        if (isArray) {
                            analysisHtml += `<li>Response is an array with ${items.length} items</li>`;
                            if (items.length > 0) {
                                const firstItem = items[0];
                                analysisHtml += '<li>First item properties:</li><ul>';
                                Object.entries(firstItem).forEach(([key, value]) => {
                                    analysisHtml += `<li><strong>${key}</strong>: ${typeof value} (${value})</li>`;
                                });
                                analysisHtml += '</ul>';
                            }
                        } else {
                            analysisHtml += '<li>Response is an object with these properties:</li><ul>';
                            Object.entries(data).forEach(([key, value]) => {
                                if (key === 'data' && Array.isArray(value)) {
                                    analysisHtml += `<li><strong>${key}</strong>: Array with ${value.length} items</li>`;
                                } else {
                                    analysisHtml += `<li><strong>${key}</strong>: ${typeof value}</li>`;
                                }
                            });
                            analysisHtml += '</ul>';
                        }
                        analysisHtml += '</ul>';
                        document.getElementById('dataAnalysis').innerHTML = analysisHtml;
                        
                    } catch (e) {
                        document.getElementById('status').innerHTML = 
                            `<p class="error">❌ ERROR: Failed to parse JSON response: ${e.message}</p>`;
                        document.getElementById('parsedData').innerHTML = '<p>Could not parse data.</p>';
                        document.getElementById('dataAnalysis').innerHTML = '<p>Analysis not available.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('status').innerHTML = 
                        `<p class="error">❌ ERROR: ${error.message}</p>`;
                    document.getElementById('rawResponse').textContent = 'Failed to fetch data.';
                    document.getElementById('parsedData').innerHTML = '<p>No data available.</p>';
                    document.getElementById('dataAnalysis').innerHTML = '<p>Analysis not available.</p>';
                });
        });
    </script>
</body>
</html>
