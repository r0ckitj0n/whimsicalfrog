<?php
// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Determine if we're in production or development
$isLocalhost = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false;
$nodeServerUrl = $isLocalhost ? 'http://localhost:3000' : 'https://whimsicalfrog.us';

// Handle GET requests to fetch inventory costs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $inventoryId = $_GET['inventoryId'] ?? null;
    
    if (!$inventoryId) {
        http_response_code(400);
        echo json_encode(['error' => 'Inventory ID is required']);
        exit();
    }
    
    // Forward request to Node.js server
    $apiUrl = $nodeServerUrl . '/api/inventory-costs/' . urlencode($inventoryId);
    
    // Initialize cURL session
    $ch = curl_init($apiUrl);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to connect to inventory costs server',
            'details' => curl_error($ch)
        ]);
        curl_close($ch);
        exit();
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Set the HTTP status code from the Node.js response
    http_response_code($httpCode);
    
    // Output the response from the Node.js server
    echo $response;
    exit();
}

// Handle POST requests for add/update/delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? null;
    $type = $_GET['type'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if (!$action) {
        http_response_code(400);
        echo json_encode(['error' => 'Action parameter is required']);
        exit();
    }
    
    if (!in_array($action, ['add', 'update', 'delete'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Must be add, update, or delete']);
        exit();
    }
    
    if ($action !== 'add' && !$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID parameter is required for update and delete operations']);
        exit();
    }
    
    if (!$type && $action !== 'delete') {
        http_response_code(400);
        echo json_encode(['error' => 'Type parameter is required']);
        exit();
    }
    
    // Get the raw POST data
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);
    
    // Determine the API endpoint based on the action
    switch ($action) {
        case 'add':
            $apiUrl = $nodeServerUrl . '/api/add-cost';
            $postData = json_encode([
                'type' => $type,
                'inventoryId' => $data['inventoryId'] ?? null,
                'data' => $data
            ]);
            break;
            
        case 'update':
            $apiUrl = $nodeServerUrl . '/api/update-cost';
            $postData = json_encode([
                'type' => $type,
                'id' => $id,
                'data' => $data
            ]);
            break;
            
        case 'delete':
            $apiUrl = $nodeServerUrl . '/api/delete-cost';
            $postData = json_encode([
                'type' => $type,
                'id' => $id
            ]);
            break;
    }
    
    // Initialize cURL session
    $ch = curl_init($apiUrl);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    
    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to connect to inventory costs server',
            'details' => curl_error($ch)
        ]);
        curl_close($ch);
        exit();
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Set the HTTP status code from the Node.js response
    http_response_code($httpCode);
    
    // Output the response from the Node.js server
    echo $response;
    exit();
}

// If we get here, the request method is not supported
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
