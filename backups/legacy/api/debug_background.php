<?php
// Debug script to isolate background API issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Background API Debug ===\n";

try {
    echo "1. Testing includes...\n";
    require_once __DIR__ . '/api_bootstrap.php';
    echo "   ✓ api_bootstrap.php loaded\n";

    require_once __DIR__ . '/../includes/functions.php';
    echo "   ✓ functions.php loaded\n";

    require_once __DIR__ . '/room_helpers.php';
    echo "   ✓ room_helpers.php loaded\n";

    echo "2. Testing database globals...\n";
    global $host, $db, $user, $pass, $port, $socket;
    $host = 'localhost';
    $db = 'whimsicalfrog';
    $user = 'root';
    $pass = 'Palz2516';
    $port = 3306;
    $socket = '';
    echo "   ✓ Database globals set\n";

    echo "3. Testing Database class...\n";
    $pdo = Database::getInstance();
    echo "   ✓ Database connection successful\n";

    echo "4. Testing getAllValidRooms function...\n";
    $validRooms = getAllValidRooms();
    echo "   ✓ getAllValidRooms returned: " . count($validRooms) . " rooms\n";

    echo "5. Testing background query...\n";
    $stmt = $pdo->prepare("SELECT background_name, image_filename, webp_filename, created_at FROM backgrounds WHERE room_type = ? AND is_active = 1 LIMIT 1");
    $stmt->execute(['bedroom']);
    $background = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Background query successful\n";

    echo "=== All tests passed! ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
