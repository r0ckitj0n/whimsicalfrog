<?php
/**
 * Get Available AI Models API
 * Returns available models for each AI provider
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';
require_once 'ai_providers.php';

// Set JSON header
header('Content-Type: application/json');

// Use centralized authentication
requireAdmin();

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
            
            echo json_encode([
                'success' => true,
                'models' => $allModels
            ]);
        } else {
            // Return models for specific provider
            $models = $aiProviders->getAvailableModels($provider);
            
            echo json_encode([
                'success' => true,
                'provider' => $provider,
                'models' => $models
            ]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("Get AI Models API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch AI models',
        'message' => $e->getMessage()
    ]);
}
?> 