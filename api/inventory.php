<?php
// api/inventory.php - Returns inventory data from MySQL in the same format as Google Sheets API

// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection configuration
$host_name = 'db5017975223.hosting-data.io';
$database = 'dbs14295502';
$user_name = 'dbu2826619';
$password = 'Palz2516!';
$port = 3306;

// Initialize response array
$response = [];

try {
    // Create database connection
    $conn = new mysqli($host_name, $user_name, $password, $database, $port);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Get column names to match the Google Sheets format
    $columnsQuery = "SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = '$database' 
                    AND TABLE_NAME = 'inventory'
                    AND COLUMN_NAME NOT IN ('CreatedAt', 'UpdatedAt')
                    ORDER BY ORDINAL_POSITION";
    
    $columnsResult = $conn->query($columnsQuery);
    
    if (!$columnsResult) {
        throw new Exception("Error fetching column names: " . $conn->error);
    }
    
    // Extract column names as array
    $headers = [];
    while ($column = $columnsResult->fetch_assoc()) {
        $headers[] = $column['COLUMN_NAME'];
    }
    
    // First row of response is headers
    $response[] = $headers;
    
    // Get all inventory items
    $inventoryQuery = "SELECT " . implode(", ", $headers) . " FROM inventory";
    $inventoryResult = $conn->query($inventoryQuery);
    
    if (!$inventoryResult) {
        throw new Exception("Error fetching inventory: " . $conn->error);
    }
    
    // Add inventory rows to response
    while ($row = $inventoryResult->fetch_assoc()) {
        $inventoryRow = [];
        foreach ($headers as $header) {
            // Convert null values to empty strings to match Google Sheets format
            $inventoryRow[] = $row[$header] !== null ? $row[$header] : '';
        }
        $response[] = $inventoryRow;
    }
    
    // Close connection
    $conn->close();
    
    // Return JSON response
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error (to server error log)
    error_log("Inventory API Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to fetch inventory",
        "message" => $e->getMessage()
    ]);
}
?>
