<?php
/**
 * Initialize AI Models Database Table
 * Creates table to track AI model capabilities including image support
 */

require_once __DIR__ . '/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create ai_models table
    $sql = "CREATE TABLE IF NOT EXISTS ai_models (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(50) NOT NULL,
        model_id VARCHAR(100) NOT NULL,
        model_name VARCHAR(150) NOT NULL,
        supports_images BOOLEAN DEFAULT FALSE,
        supports_text BOOLEAN DEFAULT TRUE,
        max_tokens INT DEFAULT 4000,
        context_window INT DEFAULT 8000,
        cost_per_1k_tokens DECIMAL(10,6) DEFAULT 0.000000,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_provider_model (provider, model_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    // Insert default model data
    $models = [
        // OpenAI Models
        ['openai', 'gpt-3.5-turbo', 'GPT-3.5 Turbo', false, true, 4000, 16000, 0.001500],
        ['openai', 'gpt-4', 'GPT-4', false, true, 8000, 8000, 0.030000],
        ['openai', 'gpt-4-turbo', 'GPT-4 Turbo', false, true, 4000, 128000, 0.010000],
        ['openai', 'gpt-4o', 'GPT-4o', true, true, 4000, 128000, 0.005000],
        ['openai', 'gpt-4o-mini', 'GPT-4o Mini', true, true, 16000, 128000, 0.000150],
        
        // Anthropic Models
        ['anthropic', 'claude-3-haiku-20240307', 'Claude 3 Haiku', false, true, 4000, 200000, 0.000250],
        ['anthropic', 'claude-3-sonnet-20240229', 'Claude 3 Sonnet', false, true, 4000, 200000, 0.003000],
        ['anthropic', 'claude-3-opus-20240229', 'Claude 3 Opus', false, true, 4000, 200000, 0.015000],
        ['anthropic', 'claude-3-5-haiku-20241022', 'Claude 3.5 Haiku', true, true, 8000, 200000, 0.001000],
        ['anthropic', 'claude-3-5-sonnet-20241022', 'Claude 3.5 Sonnet', true, true, 8000, 200000, 0.003000],
        
        // Google Models
        ['google', 'gemini-pro', 'Gemini Pro', false, true, 2048, 32000, 0.000500],
        ['google', 'gemini-pro-vision', 'Gemini Pro Vision', true, true, 2048, 32000, 0.000500],
        ['google', 'gemini-1.5-pro', 'Gemini 1.5 Pro', true, true, 8000, 2000000, 0.003500],
        ['google', 'gemini-1.5-flash', 'Gemini 1.5 Flash', true, true, 8000, 1000000, 0.000075],
        
        // Meta Models (via OpenRouter)
        ['meta', 'meta-llama/llama-3.1-8b-instruct', 'Llama 3.1 8B Instruct', false, true, 4000, 128000, 0.000180],
        ['meta', 'meta-llama/llama-3.1-70b-instruct', 'Llama 3.1 70B Instruct', false, true, 4000, 128000, 0.000880],
        ['meta', 'meta-llama/llama-3.1-405b-instruct', 'Llama 3.1 405B Instruct', false, true, 4000, 128000, 0.005400],
        ['meta', 'meta-llama/llama-3.2-11b-vision-instruct', 'Llama 3.2 11B Vision', true, true, 4000, 128000, 0.000550],
        ['meta', 'meta-llama/llama-3.2-90b-vision-instruct', 'Llama 3.2 90B Vision', true, true, 4000, 128000, 0.001200],
        
        // Local fallback
        ['jons_ai', 'jons-ai-basic', "Jon's Basic AI", false, true, 1000, 4000, 0.000000]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO ai_models (provider, model_id, model_name, supports_images, supports_text, max_tokens, context_window, cost_per_1k_tokens) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($models as $model) {
        $stmt->execute($model);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'AI models database initialized successfully',
        'models_added' => count($models)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 