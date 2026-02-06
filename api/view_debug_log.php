<?php
/**
 * api/view_debug_log.php
 * Temporarily view auth debug logs (REMOVE BEFORE PRODUCTION)
 */

require_once __DIR__ . '/../includes/auth.php';

// Only allow admin users
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$logFile = __DIR__ . '/../logs/auth_debug.log';
if (file_exists($logFile)) {
    header('Content-Type: text/plain');
    echo file_get_contents($logFile);
} else {
    http_response_code(404);
    echo "Log file not found";
}
