<?php

/**
 * Get Available AI Models API
 * Returns available models for each AI provider
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';
require_once __DIR__ . '/../includes/response.php';

// Ensure clean JSON
ini_set('display_errors', 0);

// Centralized admin check
AuthHelper::requireAdmin();

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $provider = $_GET['provider'] ?? 'all';

        $aiProviders = getAIProviders();

        if ($provider === 'all') {
            // Return models for all providers
            $allModels = [
                'jons_ai' => $aiProviders->getAvailableModels('jons_ai'),
                'openai' => $aiProviders->getAvailableModels('openai'),
                'anthropic' => $aiProviders->getAvailableModels('anthropic'),
                'google' => $aiProviders->getAvailableModels('google')
            ];

            Response::json([
                'success' => true,
                'models' => $allModels
            ]);
        } else {
            // Return models for specific provider
            $models = $aiProviders->getAvailableModels($provider);

            Response::json([
                'success' => true,
                'provider' => $provider,
                'models' => $models
            ]);
        }

    } else {
        Response::methodNotAllowed();
    }

} catch (Exception $e) {
    error_log("Get AI Models API Error: " . $e->getMessage());
    Response::serverError('Failed to fetch AI models', $e->getMessage());
}

