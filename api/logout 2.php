<?php
/**
 * api/logout.php
 * Endpoint to handle user logout by clearing sessions and cookies.
 */

// Use api_bootstrap for standard API headers and environment setup
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    // Consistency: guarantee session environment before destruction
    ensureSessionStarted();
    logoutUser();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
