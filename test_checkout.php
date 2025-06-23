<?php
// Test script to debug checkout issue
require_once 'api/config.php';

// Simulate the exact data from the frontend
$testData = [
    'customerId' => 'U00001',
    'itemIds' => ['WF-TS-001', 'WF-TU-001'], // These should be SKUs
    'quantities' => [1, 1],
    'paymentMethod' => 'Cash',
    'shippingMethod' => 'Customer Pickup',
    'subtotal' => 59.98,
    'salesTax' => 4.80,
    'total' => 64.78,
    'status' => 'Pending',
    'date' => '2025-01-22'
];

echo "Testing checkout with data:\n";
print_r($testData);

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if the items exist
    echo "\nChecking if items exist in database:\n";
    $stmt = $pdo->prepare("SELECT sku, name, retailPrice FROM items WHERE sku IN (?, ?)");
    $stmt->execute(['WF-TS-001', 'WF-TU-001']);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo "ERROR: No items found in database!\n";
        exit;
    }
    
    foreach ($items as $item) {
        echo "Found item: {$item['sku']} - {$item['name']} - \${$item['retailPrice']}\n";
    }
    
    // Test the exact API call
    echo "\nTesting API call...\n";
    
    $apiUrl = 'http://localhost:8000/api/add-order.php';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Response Code: $httpCode\n";
    echo "Response: $response\n";
    
    if ($httpCode !== 200) {
        echo "ERROR: API call failed!\n";
    } else {
        $responseData = json_decode($response, true);
        if ($responseData['success']) {
            echo "SUCCESS: Order created with ID: {$responseData['orderId']}\n";
        } else {
            echo "ERROR: {$responseData['error']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?> 