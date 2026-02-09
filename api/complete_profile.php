<?php

require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/user_meta.php';
require_once dirname(__DIR__) . '/includes/helpers/UserUpdateHelper.php';
require_once dirname(__DIR__) . '/includes/helpers/ProfileCompletionHelper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    ensureSessionStarted();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated'
        ]);
        exit;
    }

    $userId = $sessionUser['user_id'] ?? ($sessionUser['id'] ?? null);
    if ($userId === null || $userId === '') {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Missing authenticated user id'
        ]);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON payload'
        ]);
        exit;
    }

    $firstName = trim((string) ($payload['first_name'] ?? ''));
    $lastName = trim((string) ($payload['last_name'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $phoneNumber = trim((string) ($payload['phone_number'] ?? ''));
    $addressLine1 = trim((string) ($payload['address_line_1'] ?? ''));
    $city = trim((string) ($payload['city'] ?? ''));
    $state = trim((string) ($payload['state'] ?? ''));
    $zipCode = trim((string) ($payload['zip_code'] ?? ''));

    $profilePayload = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'address_line_1' => $addressLine1,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zipCode,
    ];
    $missingProfileFields = wf_profile_missing_fields($profilePayload);
    if (count($missingProfileFields) > 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Please complete all required profile fields before continuing'
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid email format'
        ]);
        exit;
    }

    UserUpdateHelper::update($userId, [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone_number' => $phoneNumber,
        'address_line_1' => $addressLine1,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zipCode
    ]);

    set_user_meta_many($userId, [
        'profile_completion_required' => '0',
        'profile_completed_at' => gmdate('c')
    ]);

    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user']['profile_completion_required'] = false;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile completed successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
