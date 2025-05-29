<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load configuration and environment variables
require_once __DIR__ . '/../config.php';

// Include Google API client (you'll need to install this via Composer)
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Configuration
    $spreadsheetId = getenv('SPREADSHEET_ID');
    $credentialsPath = __DIR__ . '/../credentials.json';
    
    if (!$spreadsheetId) {
        throw new Exception('SPREADSHEET_ID environment variable not set');
    }
    
    if (!file_exists($credentialsPath)) {
        throw new Exception('credentials.json file not found');
    }
    
    // Initialize Google Sheets client
    $client = new Google_Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);
    
    $service = new Google_Service_Sheets($client);
    
    // Fetch data from Inventory sheet
    $range = 'Inventory!A1:Z1000';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    
    if (empty($values)) {
        echo json_encode([]);
    } else {
        echo json_encode($values);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch data from Google Sheets',
        'details' => $e->getMessage()
    ]);
}
?> 