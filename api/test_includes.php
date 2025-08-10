<?php
// Targeted test to isolate which include is failing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing includes individually...\n";

try {
    echo "1. Loading api_bootstrap.php...\n";
    require_once __DIR__ . '/api_bootstrap.php';
    echo "   ✓ api_bootstrap.php loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ ERROR in api_bootstrap.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    echo "2. Loading functions.php...\n";
    require_once __DIR__ . '/../includes/functions.php';
    echo "   ✓ functions.php loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ ERROR in functions.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    echo "3. Loading room_helpers.php...\n";
    require_once __DIR__ . '/room_helpers.php';
    echo "   ✓ room_helpers.php loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ ERROR in room_helpers.php: " . $e->getMessage() . "\n";
    exit;
}

echo "All includes loaded successfully!\n";
