<?php
// WhimsicalFrog API bootstrap – ensures every endpoint returns clean JSON even on PHP warnings/notices
// Include this file at the top of every API script *before* any output.

header('Content-Type: application/json; charset=utf-8');
// Enable CORS for local development previews
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// Clear any stray output generated before this point
if (ob_get_length()) {
    ob_clean();
}

// Never show errors to the client; log them instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('html_errors', 0);

// Make sure error log path is set (relative to project root)
if (!ini_get('log_errors')) {
    ini_set('log_errors', 1);
}
if (!ini_get('error_log')) {
    ini_set('error_log', __DIR__ . '/../logs/php_error.log');
}

// Global error handler that converts any PHP warning/notice/fatal into a clean JSON error payload
set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again later.'
    ]);
    error_log("API error: $message in $file on line $line");
    exit;
});

// Ensure we capture any accidental output (e.g., echo/print/var_dump)
ob_start();
?>
