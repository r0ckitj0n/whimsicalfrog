<?php
/**
 * AI Settings API for WhimsicalFrog
 * Manages AI provider configurations and settings
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once 'ai_providers.php';
require_once 'ai_settings_helper.php';
require_once __DIR__ . '/../includes/auth.php';

// Determine action and input early
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $_POST = array_merge($_POST, $input);
}

$__wf_action = $_GET['action'] ?? $_POST['action'] ?? '';
requireAdmin(true);

$action = $__wf_action;
$allowedActions = ['get_settings', 'get_providers', 'update_settings', 'test_provider', 'list_models', 'init_ai_settings'];
if (!in_array($action, $allowedActions, true)) {
    Response::error('Invalid action', null, 400);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$postOnlyActions = ['update_settings', 'test_provider', 'init_ai_settings'];
if (in_array($action, $postOnlyActions, true) && $method !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
if (!in_array($action, $postOnlyActions, true) && $method !== 'GET') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    switch ($action) {
        case 'get_settings':
            $settings = getAISettings();
            Response::json(['success' => true, 'settings' => $settings]);
            break;

        case 'get_providers':
            $providers = getAIProviders()->getAvailableProviders();
            Response::json(['success' => true, 'providers' => $providers]);
            break;

        case 'update_settings':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input) || empty($input)) {
                Response::error('Invalid JSON input', null, 400);
            }

            $result = updateAISettings($input, $pdo);
            Response::json(['success' => true, 'message' => 'AI settings updated successfully']);
            break;

        case 'test_provider':
            $provider = $_POST['provider'] ?? $_GET['provider'] ?? '';
            $provider = strtolower(trim((string)$provider));
            $allowedProviders = [
                WF_Constants::AI_PROVIDER_OPENAI,
                WF_Constants::AI_PROVIDER_ANTHROPIC,
                WF_Constants::AI_PROVIDER_GOOGLE,
                WF_Constants::AI_PROVIDER_META,
                WF_Constants::AI_PROVIDER_JONS_AI
            ];
            if (empty($provider)) {
                Response::error('Provider not specified', null, 400);
            }
            if (!in_array($provider, $allowedProviders, true)) {
                Response::error('Invalid provider', null, 422);
            }

            $result = getAIProviders()->testProvider($provider);
            Response::json($result);
            break;

        case 'list_models':
            $provider = $_GET['provider'] ?? '';
            $provider = strtolower(trim((string)$provider));
            $force = isset($_GET['force']) && ($_GET['force'] === '1' || strtolower($_GET['force']) === 'true');
            $source = $_GET['source'] ?? '';
            $source = strtolower(trim((string)$source));
            $allowedProviders = [
                WF_Constants::AI_PROVIDER_OPENAI,
                WF_Constants::AI_PROVIDER_ANTHROPIC,
                WF_Constants::AI_PROVIDER_GOOGLE,
                WF_Constants::AI_PROVIDER_META
            ];
            if (!$provider) {
                Response::error('Provider not specified', null, 400);
            }
            if (!in_array($provider, $allowedProviders, true)) {
                Response::error('Invalid provider', null, 422);
            }
            if (!in_array($source, ['', 'openrouter'], true)) {
                Response::error('Invalid source', null, 422);
            }
            try {
                if ($source === 'openrouter') {
                    $models = ai_list_models_openrouter($provider, $force);
                } else {
                    $models = ai_list_models($provider, $force);
                }
                Response::json(['success' => true, 'models' => $models]);
            } catch (Exception $e) {
                Response::error($e->getMessage(), null, 500);
            }
            break;

        case 'init_ai_settings':
            $result = initializeAISettings($pdo);
            Response::json(['success' => true, 'message' => 'AI settings initialized', 'inserted' => $result]);
            break;
    }

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}

?>
