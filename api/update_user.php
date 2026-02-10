<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/user_meta.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/helpers/AuthSessionHelper.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Standardize session initialization
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_init([
        'name' => 'PHPSESSID',
        'lifetime' => 0,
        'path' => '/',
        'domain' => AuthSessionHelper::getCookieDomain(),
        'secure' => AuthSessionHelper::isHttps(),
        'httponly' => true,
        'samesite' => 'None',
    ]);
}

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['user_id']) && !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }

    // Get the user ID (support both user_id and id fields for compatibility)
    $user_id = $data['user_id'] ?? $data['id'];
    $targetUserId = (string) $user_id;

    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $currentUserId = (string) ($currentUser['user_id'] ?? ($currentUser['id'] ?? ''));
    $currentIsAdmin = isAdmin();
    if (!$currentIsAdmin && $currentUserId !== $targetUserId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: users may only update their own account']);
        exit;
    }

    if (!$currentIsAdmin) {
        foreach ([
            'role',
            'status',
            'vip',
            'tax_exempt',
            'referral_source',
            'birthdate',
            'tags',
            'admin_notes',
            'password'
        ] as $restrictedKey) {
            unset($data[$restrictedKey]);
        }
    }

    require_once dirname(__DIR__) . '/includes/helpers/UserUpdateHelper.php';
    UserUpdateHelper::update($targetUserId, $data);

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'user' => $_SESSION['user'] ?? null
    ]);

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
