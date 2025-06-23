<?php
/**
 * Web-accessible migration runner
 * Run this once on the live server to add AI processing columns
 */

// Simple security check
$allowed_ips = ['127.0.0.1', '::1']; // localhost only
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// For development, you can temporarily add your IP or remove this check
// if ($client_ip !== '127.0.0.1' && $client_ip !== '::1') {
//     die('Access denied');
// }

echo "<h2>AI Processing Database Migration</h2>\n";
echo "<pre>\n";

try {
    require_once __DIR__ . '/database_migrations/add_ai_processing_columns.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
echo "<p><strong>Migration completed!</strong> You can now delete this file for security.</p>\n";
?> 