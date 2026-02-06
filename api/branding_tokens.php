<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/branding_tokens_helper.php';

function current_admin_identifier(): string
{
    try {
        $user = AuthHelper::getCurrentUser();
        if (is_array($user)) {
            if (!empty($user['email'])) {
                return (string) $user['email'];
            }
            if (!empty($user['username'])) {
                return (string) $user['username'];
            }
            if (!empty($user['name'])) {
                return (string) $user['name'];
            }
        }
    } catch (Throwable $e) {
        // Ignore lookup failures
    }
    return WF_Constants::ROLE_ADMIN;
}

function handle_get(): void
{
    $tokens = BrandingTokens::getTokens();
    Response::success([
        'tokens' => $tokens,
        'meta' => [
            'updated_at' => BrandingTokens::getLastUpdatedAt(),
            'updated_by' => BrandingTokens::getLastUpdatedBy(),
        ],
    ]);
}

function handle_post(): void
{
    AuthHelper::requireAdmin();

    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);

    $action = $_GET['action'] ?? '';

    if ($action === 'create_backup') {
        BrandingTokens::createBackup(current_admin_identifier());
        Response::success(['message' => 'Backup created successfully']);
    }

    if ($action === 'reset_defaults') {
        BrandingTokens::resetToDefaults(current_admin_identifier());
        Response::success([
            'tokens' => BrandingTokens::getTokens(),
            'message' => 'Branding reset to defaults'
        ]);
    }

    if (!is_array($body)) {
        Response::error('Invalid JSON payload', null, 400);
    }

    $tokens = [];
    if (isset($body['tokens']) && is_array($body['tokens'])) {
        $tokens = $body['tokens'];
    } else {
        $tokens = $body;
    }

    if (isset($tokens['business_brand_palette']) && is_array($tokens['business_brand_palette'])) {
        $tokens['business_brand_palette'] = BrandingTokens::encodePaletteArray($tokens['business_brand_palette']);
    }

    $errors = BrandingTokens::validatePayload($tokens);
    if (!empty($errors)) {
        Response::error('Validation failed', ['fields' => $errors], 422);
    }

    BrandingTokens::saveTokens($tokens, current_admin_identifier());

    Response::success([
        'tokens' => BrandingTokens::getTokens(),
        'meta' => [
            'updated_at' => BrandingTokens::getLastUpdatedAt(),
            'updated_by' => BrandingTokens::getLastUpdatedBy(),
        ],
    ]);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        handle_get();
        return;
    }

    if ($method === 'POST' || $method === 'PUT') {
        handle_post();
        return;
    }

    Response::error('Unsupported method', null, 405);
} catch (Throwable $e) {
    Response::serverError('Branding tokens API failure: ' . $e->getMessage());
}
