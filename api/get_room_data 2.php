<?php
/**
 * API endpoint to get dynamic room data for JavaScript
 */

header('Content-Type: application/json; charset=utf-8');
// CORS for local dev previews
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// Suppress PHP warnings/notices from leaking into output and breaking JSON
ini_set('display_errors', 0);
set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again later.'
    ]);
    error_log("API error in get_room_data.php: $message in $file on line $line");
    exit;
});
ob_start();
require_once __DIR__ . '/room_helpers.php';

try {
    $roomData = [

        'displayTypeMapping' => getRoomDisplayTypeMapping(),
        'validRooms' => getAllValidRooms(),
        'itemRooms' => getActiveItemRooms(),
        'coreRooms' => getCoreRooms(),
        'roomDoors' => getRoomDoorsData(),
        'roomTypeMapping' => getRoomTypeMapping()
    ];

    // Discard any accidental output captured earlier
    if (ob_get_length() !== false) {
        ob_end_clean();
    }
    echo json_encode([
        'success' => true,
        'data' => $roomData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error getting room data: ' . $e->getMessage()
    ]);
}
