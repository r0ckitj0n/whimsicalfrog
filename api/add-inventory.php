<?php
// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit();
}

// Determine if we're in production or development
$isProduction = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'whimsicalfrog.us');

// Set the Node.js server URL based on environment
$nodeServerUrl = $isProduction 
    ? 'http://localhost:3000/api/add-inventory'  // Production: Node.js runs on the same server
    : 'http://localhost:3000/api/add-inventory'; // Development: Node.js runs locally

// Get the raw POST data
$jsonInput = file_get_contents('php://input');

// Initialize cURL session
$ch = curl_init($nodeServerUrl);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonInput);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonInput)
]);

// Execute the cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to connect to inventory addition server',
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
