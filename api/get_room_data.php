<?php
/**
 * API endpoint to get dynamic room data for JavaScript
 */

header('Content-Type: application/json');
require_once __DIR__ . '/room_helpers.php';

try {
    $roomData = [

        'displayTypeMapping' => getRoomDisplayTypeMapping(),
        'validRooms' => getAllValidRooms(),
        'productRooms' => getActiveProductRooms(),
        'coreRooms' => getCoreRooms(),
        'roomDoors' => getRoomDoorsData(),
        'roomTypeMapping' => getRoomTypeMapping()
    ];

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
?> 