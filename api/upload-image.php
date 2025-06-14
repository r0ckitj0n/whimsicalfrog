<?php
// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

// Check if this is a multipart/form-data request
if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image file uploaded or invalid upload']);
    exit();
}

// Determine if we're in production or development
$isProduction = (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'whimsicalfrog.us');

// Set the Node.js server URL based on environment
$nodeServerUrl = $isProduction 
    ? 'http://localhost:3000/api/upload-image'  // Production: Node.js runs on the same server
    : 'http://localhost:3000/api/upload-image'; // Development: Node.js runs locally

// Initialize cURL session
$ch = curl_init($nodeServerUrl);

// Create a CURLFile object from the uploaded file
$cfile = new CURLFile(
    $_FILES['image']['tmp_name'],
    $_FILES['image']['type'],
    $_FILES['image']['name']
);

// Prepare the form data with both the file and all other POST fields
$postFields = $_POST;
$postFields['image'] = $cfile;

// Set cURL options for multipart/form-data
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increase timeout for file uploads

// Execute the cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to connect to image upload server',
        'details' => curl_error($ch)
    ]);
    curl_close($ch);
    exit();
}

// Close cURL session
curl_close($ch);

// Set the HTTP status code from the Node.js response
http_response_code($httpCode);

// Set the content type to JSON for the response
header('Content-Type: application/json');

// Output the response from the Node.js server
echo $response;
