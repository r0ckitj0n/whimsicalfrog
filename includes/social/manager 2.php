<?php
/**
 * Social Accounts Manager Logic
 * Provides CRUD operations and connection verification for social media accounts.
 */

/**
 * Social platform configuration with API endpoints and colors
 */
function get_social_providers(): array
{
    return [
        'facebook' => [
            'name' => 'Facebook',
            'icon' => '📘',
            'color' => '#1877f2',
            'api_base' => 'https://graph.facebook.com/v18.0',
            'verify_endpoint' => '/me'
        ],
        'instagram' => [
            'name' => 'Instagram',
            'icon' => '📷',
            'color' => '#e4405f',
            'api_base' => 'https://graph.instagram.com',
            'verify_endpoint' => '/me'
        ],
        'twitter' => [
            'name' => 'Twitter/X',
            'icon' => '🐦',
            'color' => '#1da1f2',
            'api_base' => 'https://api.twitter.com/2',
            'verify_endpoint' => '/users/me'
        ],
        'linkedin' => [
            'name' => 'LinkedIn',
            'icon' => '💼',
            'color' => '#0077b5',
            'api_base' => 'https://api.linkedin.com/v2',
            'verify_endpoint' => '/me'
        ],
        'pinterest' => [
            'name' => 'Pinterest',
            'icon' => '📌',
            'color' => '#bd081c',
            'api_base' => 'https://api.pinterest.com/v5',
            'verify_endpoint' => '/user_account'
        ],
        'tiktok' => [
            'name' => 'TikTok',
            'icon' => '🎵',
            'color' => '#010101',
            'api_base' => 'https://open.tiktokapis.com/v2',
            'verify_endpoint' => '/user/info/'
        ],
        'youtube' => [
            'name' => 'YouTube',
            'icon' => '📺',
            'color' => '#ff0000',
            'api_base' => 'https://www.googleapis.com/youtube/v3',
            'verify_endpoint' => '/channels?part=snippet&mine=true'
        ]
    ];
}

function fetch_social_account(int $id): ?array
{
    $row = Database::queryOne('SELECT * FROM social_accounts WHERE id = ? LIMIT 1', [$id]);
    return $row ? hydrate_account_row($row) : null;
}

function fetch_social_accounts(): array
{
    $rows = Database::queryAll('SELECT * FROM social_accounts ORDER BY platform, account_name');
    $accounts = array_map('hydrate_account_row', $rows ?: []);
    $providers = get_social_providers();

    foreach ($accounts as &$account) {
        $id = (int) ($account['id'] ?? 0);
        $hasToken = $id > 0 && secret_has('social_account_token_' . $id);
        $account['has_token'] = $hasToken;

        // Determine connection status
        if (!$hasToken) {
            $account['connection_status'] = 'disconnected';
        } elseif (!empty($account['token_expires_at']) && strtotime($account['token_expires_at']) < time()) {
            $account['connection_status'] = 'expired';
        } elseif ($account['is_active']) {
            $account['connection_status'] = 'connected';
        } else {
            $account['connection_status'] = 'pending';
        }

        // Add provider metadata
        $platform = strtolower($account['platform']);
        if (isset($providers[$platform])) {
            $account['provider_icon'] = $providers[$platform]['icon'];
            $account['provider_color'] = $providers[$platform]['color'];
            $account['provider_name'] = $providers[$platform]['name'];
        }
    }
    return $accounts;
}

function hydrate_account_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'platform' => (string) ($row['platform'] ?? ''),
        'account_name' => (string) ($row['account_name'] ?? ''),
        'account_id' => (string) ($row['account_id'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        'last_sync' => $row['last_sync'] ?? null,
        'last_verified' => $row['last_verified'] ?? null,
        'token_expires_at' => $row['token_expires_at'] ?? null,
        'profile_url' => $row['profile_url'] ?? null,
        'profile_image' => $row['profile_image'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null
    ];
}

function create_social_account(array $input): array
{
    $platform = strtolower(trim($input['platform'] ?? ''));
    $account_name = trim($input['account_name'] ?? '');
    $access_token = $input['access_token'] ?? '';
    $account_id = $input['account_id'] ?? '';
    $refresh_token = $input['refresh_token'] ?? null;
    $token_expires_at = $input['token_expires_at'] ?? null;

    $providers = get_social_providers();
    if (!isset($providers[$platform])) {
        throw new Exception('Invalid social platform');
    }
    if (empty($account_name)) {
        throw new Exception('Account name is required');
    }

    // Insert account record
    Database::execute(
        "INSERT INTO social_accounts (platform, account_name, account_id, is_active, created_at, updated_at) 
         VALUES (?, ?, ?, 1, NOW(), NOW())",
        [$platform, $account_name, $account_id]
    );

    $newId = Database::lastInsertId();

    // Store tokens securely
    if (!empty($access_token)) {
        secret_set('social_account_token_' . $newId, $access_token);
    }
    if (!empty($refresh_token)) {
        secret_set('social_account_refresh_' . $newId, $refresh_token);
    }

    // Update token expiry if provided
    if (!empty($token_expires_at)) {
        Database::execute(
            "UPDATE social_accounts SET token_expires_at = ? WHERE id = ?",
            [$token_expires_at, $newId]
        );
    }

    return ['success' => true, 'id' => $newId, 'message' => 'Account created successfully'];
}

function verify_social_connection(int $id): array
{
    $account = fetch_social_account($id);
    if (!$account) {
        return ['success' => false, 'status' => 'error', 'message' => 'Account not found'];
    }

    $token = secret_get('social_account_token_' . $id);
    if (empty($token)) {
        Database::execute("UPDATE social_accounts SET is_active = 0 WHERE id = ?", [$id]);
        return ['success' => false, 'status' => 'disconnected', 'message' => 'No access token found'];
    }

    $providers = get_social_providers();
    $platform = strtolower($account['platform']);

    if (!isset($providers[$platform])) {
        return ['success' => false, 'status' => 'error', 'message' => 'Unknown platform'];
    }

    $provider = $providers[$platform];

    // Attempt API verification
    $verifyResult = call_social_api($provider['api_base'] . $provider['verify_endpoint'], $token, $platform);

    if ($verifyResult['success']) {
        Database::execute(
            "UPDATE social_accounts SET is_active = 1, last_verified = NOW() WHERE id = ?",
            [$id]
        );
        return [
            'success' => true,
            'status' => 'connected',
            'message' => 'Connection verified successfully',
            'verified_at' => date('c'),
            'account_info' => $verifyResult['data'] ?? null
        ];
    } else {
        Database::execute("UPDATE social_accounts SET is_active = 0 WHERE id = ?", [$id]);
        return [
            'success' => false,
            'status' => 'error',
            'message' => $verifyResult['error'] ?? 'Verification failed'
        ];
    }
}

function call_social_api(string $url, string $token, string $platform): array
{
    // Build authorization header based on platform
    $headers = [];
    switch ($platform) {
        case 'twitter':
            $headers[] = 'Authorization: Bearer ' . $token;
            break;
        case 'tiktok':
            $headers[] = 'Authorization: Bearer ' . $token;
            break;
        default:
            // Most platforms use ?access_token query param or Bearer header
            if (strpos($url, '?') !== false) {
                $url .= '&access_token=' . urlencode($token);
            } else {
                $url .= '?access_token=' . urlencode($token);
            }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)

    if ($error) {
        return ['success' => false, 'error' => 'Connection failed: ' . $error];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        return ['success' => true, 'data' => $data];
    }

    $errorData = json_decode($response, true);
    $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'API returned status ' . $httpCode;
    return ['success' => false, 'error' => $errorMessage];
}

function handle_social_update($input)
{
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0)
        throw new Exception('ID required');

    $updates = [];
    $params = [];

    if (isset($input['account_name'])) {
        $updates[] = 'account_name = ?';
        $params[] = $input['account_name'];
    }

    if (isset($input['account_id'])) {
        $updates[] = 'account_id = ?';
        $params[] = $input['account_id'];
    }

    if (isset($input['profile_url'])) {
        $updates[] = 'profile_url = ?';
        $params[] = $input['profile_url'];
    }

    if (isset($input['is_active'])) {
        $updates[] = 'is_active = ?';
        $params[] = $input['is_active'] ? 1 : 0;
    }

    if (!empty($input['access_token'])) {
        secret_set('social_account_token_' . $id, $input['access_token']);
        $updates[] = 'is_active = 1';
    }

    if (!empty($input['app_secret'])) {
        secret_set('social_account_secret_' . $id, $input['app_secret']);
    }

    if (!empty($input['token_expires_at'])) {
        $updates[] = 'token_expires_at = ?';
        $params[] = $input['token_expires_at'];
    }

    if (!empty($updates)) {
        $updates[] = 'updated_at = NOW()';
        $params[] = $id;
        Database::execute(
            "UPDATE social_accounts SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );
    }

    return true;
}

function refresh_social_token(int $id): array
{
    $account = fetch_social_account($id);
    if (!$account) {
        return ['success' => false, 'message' => 'Account not found'];
    }

    $refreshToken = secret_get('social_account_refresh_' . $id);
    if (empty($refreshToken)) {
        return ['success' => false, 'message' => 'No refresh token available. Please reconnect the account.'];
    }

    // Token refresh logic would go here - implementation varies by provider
    // For now, return a message indicating manual reconnection is needed
    return [
        'success' => false,
        'message' => 'Token refresh not yet implemented. Please reconnect the account.',
        'requires_reauth' => true
    ];
}

