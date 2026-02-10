<?php

// Include the configuration file (absolute path)
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/response.php';

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
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    // Validate required fields
    if (!isset($data['user_id']) || !isset($data['email']) || !isset($data['currentPassword'])) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID, email, and current password are required']);
        exit;
    }

    // Extract data
    $user_id = $data['user_id'];
    $targetUserId = (string) $user_id;
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    $first_name = $data['first_name'] ?? '';
    $last_name = $data['last_name'] ?? '';
    $phone_number = $data['phone_number'] ?? '';
    $company = $data['company'] ?? '';
    $job_title = $data['job_title'] ?? '';
    $preferred_contact = $data['preferred_contact'] ?? '';
    $preferred_language = $data['preferred_language'] ?? '';
    $marketing_opt_in = $data['marketing_opt_in'] ?? null;
    $currentPassword = $data['currentPassword'];
    $newPassword = $data['newPassword'] ?? '';

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

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Verify the user exists and the current password is correct
    $user = Database::queryOne('SELECT * FROM users WHERE id = ?', [$user_id]);

    if (!$user || !password_verify((string) $currentPassword, (string) ($user['password'] ?? ''))) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid user ID or current password']);
        exit;
    }

    // First, delegate profile field updates (email, names, etc.) to the UserUpdateHelper
    // so we reuse the dynamic field mapping there and avoid hard-coding column names.
    $profilePayload = [
        'user_id' => $targetUserId,
        'email' => $email,
        'phone_number' => $phone_number,
        'company' => $company,
        'job_title' => $job_title,
        'preferred_contact' => $preferred_contact,
        'preferred_language' => $preferred_language,
    ];
    if ($marketing_opt_in !== null) {
        $profilePayload['marketing_opt_in'] = $marketing_opt_in;
    }
    if ($first_name !== '') {
        $profilePayload['first_name'] = $first_name;
    }
    if ($last_name !== '') {
        $profilePayload['last_name'] = $last_name;
    }

    require_once __DIR__ . '/../includes/helpers/UserUpdateHelper.php';
    UserUpdateHelper::update($targetUserId, $profilePayload);

    // If a new password is provided, update it directly here
    if (!empty($newPassword)) {
        if (strlen((string) $newPassword) < 8) {
            http_response_code(422);
            echo json_encode(['error' => 'New password must be at least 8 characters']);
            exit;
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $affected = Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hashedPassword, $targetUserId]);
        if ($affected === false) {
            throw new Exception('Failed to change password');
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Account updated successfully',
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
