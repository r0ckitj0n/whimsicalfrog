<?php
// dynamic-styles.php: Serve dynamic CSS for room overlays
header('Content-Type: text/css; charset=UTF-8');
// Load DB and helper functions
require_once __DIR__ . '/../api/room_helpers.php';

// Get mapping of room numbers to room types
$mapping = getRoomTypeMapping();

foreach ($mapping as $roomNumber => $roomType) {
    // Output background-image rule for each room overlay
    echo ".room-overlay-wrapper.room-overlay-{$roomType} {\n";
    echo "    background-image: url('../images/background_${roomType}.webp?v=cb2');\n";
    echo "}\n\n";
}
