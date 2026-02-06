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

// Check if user is admin (with dev-only bypass option)

// Determine action as early as possible for conditional auth
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $_POST = array_merge($_POST, $input);
}

$__wf_action = $_GET['action'] ?? $_POST['action'] ?? '';

// Security Check: Ensure user is logged in and is an Admin
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();

// Dev-only bypass: allow saving from localhost when explicitly enabled via env
$__wf_is_localhost = isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
$__wf_dev_allow_ai_save = getenv('WF_DEV_ALLOW_AI_SAVE') === '1';
$__wf_dev_header = isset($_SERVER['HTTP_X_WF_DEV_ADMIN']) && $_SERVER['HTTP_X_WF_DEV_ADMIN'] === '1';
$__wf_dev_bypass = $__wf_is_localhost && ($__wf_dev_allow_ai_save || $__wf_dev_header);

// For read-only actions on localhost, allow UI to function without full admin
if ((!$isLoggedIn || !$isAdmin) && !$__wf_dev_bypass) {
    $readOnlyOk = $__wf_is_localhost && in_array($__wf_action, ['get_settings', 'get_providers', 'list_models'], true);
    if (!$readOnlyOk) {
        Response::forbidden('Admin access required');
    }
}

$action = $__wf_action;

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
            if (!$input) {
                Response::error('Invalid JSON input', null, 400);
            }

            $result = updateAISettings($input, $pdo);
            Response::json(['success' => true, 'message' => 'AI settings updated successfully']);
            break;

        case 'test_provider':
            $provider = $_POST['provider'] ?? $_GET['provider'] ?? '';
            if (empty($provider)) {
                Response::error('Provider not specified', null, 400);
            }

            $result = getAIProviders()->testProvider($provider);
            Response::json($result);
            break;

        case 'list_models':
            $provider = $_GET['provider'] ?? '';
            $force = isset($_GET['force']) && ($_GET['force'] === '1' || strtolower($_GET['force']) === 'true');
            $source = $_GET['source'] ?? '';
            if (!$provider) {
                Response::error('Provider not specified', null, 400);
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

        default:
            Response::error('Invalid action', null, 400);
    }

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}

?>