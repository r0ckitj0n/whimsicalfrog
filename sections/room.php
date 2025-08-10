<?php
/**
 * Generic Room Template
 * This template is used for all individual room pages (room2, room3, etc.)
 * It dynamically loads room data based on the 'room_number' passed in the URL.
 */

// Ensure this file is included from index.php
if (!defined('INCLUDED_FROM_INDEX')) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

require_once __DIR__ . '/../includes/room_helper.php';

// Get room number from the router in index.php
$roomNumber = $_GET['room_number'] ?? null;

if (!$roomNumber) {
    echo "<p>Error: Room number not specified.</p>";
    return;
}

// Initialize Room Helper
$roomHelper = new RoomHelper($roomNumber);
$roomHelper->loadRoomData($categories); // $categories is loaded in index.php

// Render the page components using the helper
// Log the data being sent to the frontend for debugging
$roomDataForJs = ['roomItems' => $roomHelper->getRoomItems(), 'roomNumber' => $roomHelper->getRoomNumber(), 'roomType' => $roomHelper->getRoomType(), 'baseAreas' => $roomHelper->getRoomCoordinates()];
error_log('Room Data for JS: ' . print_r($roomDataForJs, true));

// Pass room data to the frontend via a data attribute
$roomDataJson = htmlspecialchars($roomHelper->getRoomDataAsJson(), ENT_QUOTES, 'UTF-8');
echo "<div id='room-data-container' data-room-data='{$roomDataJson}'></div>";

echo $roomHelper->renderRoomHeader();
echo $roomHelper->renderProductIcons();

?>
