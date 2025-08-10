<?php
// Debug script to test room_main.php actual output
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTING ROOM_MAIN.PHP OUTPUT ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Start output buffering to capture the actual PHP output
ob_start();

// Include the room_main.php file
include '/Users/jongraves/Documents/Websites/WhimsicalFrog/room_main.php';

// Get the buffered content
$output = ob_get_clean();

// Search for door-area elements and data attributes
echo "=== SEARCHING FOR DOOR ELEMENTS ===\n";
preg_match_all('/<div class="door-area[^>]*>/', $output, $matches);

if (!empty($matches[0])) {
    echo "Found " . count($matches[0]) . " door elements:\n";
    foreach ($matches[0] as $i => $match) {
        echo "Door " . ($i + 1) . ": " . $match . "\n";
    }
} else {
    echo "❌ NO DOOR ELEMENTS FOUND!\n";
}

echo "\n=== CHECKING FOR DATA ATTRIBUTES ===\n";
$hasDataRoom = strpos($output, 'data-room=') !== false;
$hasDataUrl = strpos($output, 'data-url=') !== false;
$hasDataCategory = strpos($output, 'data-category=') !== false;

echo "data-room found: " . ($hasDataRoom ? "✅ YES" : "❌ NO") . "\n";
echo "data-url found: " . ($hasDataUrl ? "✅ YES" : "❌ NO") . "\n";
echo "data-category found: " . ($hasDataCategory ? "✅ YES" : "❌ NO") . "\n";

echo "\n=== RAW OUTPUT LENGTH ===\n";
echo "Total output length: " . strlen($output) . " characters\n";
