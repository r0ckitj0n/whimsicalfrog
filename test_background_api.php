<?php
// Simple test for background API response
echo "Testing get_room_coordinates.php API...\n\n";

// Include same setup as API
require_once __DIR__ . '/api/api_bootstrap.php';
require_once __DIR__ . '/includes/database.php';

try {
    // Capture the output of the API
    ob_start();
    include __DIR__ . '/api/get_room_coordinates.php';
    $output = ob_get_clean();
    
    echo "API Raw Output:\n";
    echo $output . "\n\n";
    
    // Try to decode as JSON
    $decoded = json_decode($output, true);
    if ($decoded) {
        echo "JSON Decoded Successfully:\n";
        echo "Has 'success' field: " . (isset($decoded['success']) ? 'YES' : 'NO') . "\n";
        echo "Has 'data' field: " . (isset($decoded['data']) ? 'YES' : 'NO') . "\n";
        if (isset($decoded['data'])) {
            echo "Has 'roomDoors' in data: " . (isset($decoded['data']['roomDoors']) ? 'YES' : 'NO') . "\n";
            echo "Has 'roomTypeMapping' in data: " . (isset($decoded['data']['roomTypeMapping']) ? 'YES' : 'NO') . "\n";
        }
    } else {
        echo "JSON Decode Failed - Response is not valid JSON\n";
        echo "JSON Last Error: " . json_last_error_msg() . "\n";
    }

} catch (Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
}
?>
