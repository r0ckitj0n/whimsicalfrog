<?php
/**
 * Get AI Model Capabilities API
 * Returns model information including image support
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $action = $_GET['action'] ?? 'get_current';
    
    switch ($action) {
        case 'get_current':
            // Get current AI provider and model
            $stmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE setting_key = ? AND category = 'ai'");
            
            $stmt->execute(['ai_provider']);
            $provider = $stmt->fetchColumn() ?: 'local';
            
            $modelKey = $provider . '_model';
            $stmt->execute([$modelKey]);
            $modelId = $stmt->fetchColumn() ?: 'local-basic';
            
            // Get model capabilities
            $stmt = $pdo->prepare("SELECT * FROM ai_models WHERE provider = ? AND model_id = ? AND is_active = 1");
            $stmt->execute([$provider, $modelId]);
            $model = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$model) {
                // Fallback to local if model not found
                $stmt->execute(['local', 'local-basic']);
                $model = $stmt->fetch(PDO::FETCH_ASSOC);
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
            $stmt = $pdo->query("SELECT * FROM ai_models WHERE is_active = 1 ORDER BY provider, model_name");
            $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                $stmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE setting_key = ? AND category = 'ai'");
                
                $stmt->execute(['ai_provider']);
                $provider = $stmt->fetchColumn() ?: 'local';
                
                $modelKey = $provider . '_model';
                $stmt->execute([$modelKey]);
                $modelId = $stmt->fetchColumn() ?: 'local-basic';
            }
            
            $stmt = $pdo->prepare("SELECT supports_images FROM ai_models WHERE provider = ? AND model_id = ? AND is_active = 1");
            $stmt->execute([$provider, $modelId]);
            $supportsImages = $stmt->fetchColumn();
            
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