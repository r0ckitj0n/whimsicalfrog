<?php
/**
 * Get AI Model Capabilities API
 * Returns model information including image support
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $action = $_GET['action'] ?? 'get_current';

    switch ($action) {
        case 'get_current':
            // Get current AI provider and model
            $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE setting_key = ? AND category = 'ai'", ['ai_provider']);
            $provider = $row && isset($row['setting_value']) ? $row['setting_value'] : 'jons_ai';

            $modelKey = $provider . '_model';
            $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE setting_key = ? AND category = 'ai'", [$modelKey]);
            $modelId = $row && isset($row['setting_value']) ? $row['setting_value'] : 'jons-ai-basic';

            // Get model capabilities
            $model = Database::queryOne("SELECT * FROM ai_models WHERE provider = ? AND model_id = ? AND is_active = 1", [$provider, $modelId]);

            if (!$model) {
                // Fallback to Jon's AI if model not found
                $model = Database::queryOne("SELECT * FROM ai_models WHERE provider = ? AND model_id = ? AND is_active = 1", ['jons_ai', 'jons-ai-basic']);
            }

            echo json_encode([
                'success' => true,
                'current_provider' => $provider,
                'current_model' => $modelId,
                'model_info' => $model
            ]);
            break;

        case 'get_all':
            // Get all available models
            $models = Database::queryAll("SELECT * FROM ai_models WHERE is_active = 1 ORDER BY provider, model_name");

            $grouped = [];
            foreach ($models as $model) {
                $grouped[$model['provider']][] = $model;
            }

            echo json_encode([
                'success' => true,
                'models' => $grouped
            ]);
            break;

        case 'supports_images':
            // Check if current model supports images
            $provider = $_GET['provider'] ?? null;
            $modelId = $_GET['model'] ?? null;

            if (!$provider || !$modelId) {
                // Get current settings
                $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE setting_key = ? AND category = 'ai'", ['ai_provider']);
                $provider = $row && isset($row['setting_value']) ? $row['setting_value'] : 'jons_ai';

                $modelKey = $provider . '_model';
                $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE setting_key = ? AND category = 'ai'", [$modelKey]);
                $modelId = $row && isset($row['setting_value']) ? $row['setting_value'] : 'jons-ai-basic';
            }

            $row = Database::queryOne("SELECT supports_images FROM ai_models WHERE provider = ? AND model_id = ? AND is_active = 1", [$provider, $modelId]);
            $supportsImages = $row ? $row['supports_images'] : 0;

            echo json_encode([
                'success' => true,
                'provider' => $provider,
                'model' => $modelId,
                'supports_images' => (bool)$supportsImages
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 