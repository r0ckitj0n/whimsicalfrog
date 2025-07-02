<?php
/**
 * WhimsicalFrog AI Management and Configuration
 * Centralized system functions to eliminate duplication
 * Generated: 2025-07-01 23:30:28
 */

// Include AI and database dependencies
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../api/ai_providers.php';

/**
 * Load AI settings from database
 * @return array
 */
function loadAISettings() {
    $defaults = [
        'ai_provider' => 'jons_ai',
        'openai_api_key' => '',
        'openai_model' => 'gpt-3.5-turbo',
        'anthropic_api_key' => '',
        'anthropic_model' => 'claude-3-haiku-20240307',
        'google_api_key' => '',
        'google_model' => 'gemini-pro',
        'meta_api_key' => '',
        'meta_model' => 'llama-3.1-70b-instruct',
        'ai_temperature' => 0.7,
        'ai_max_tokens' => 1000,
        'ai_timeout' => 30,
        'fallback_to_local' => true
    ];
    
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error loading AI settings: " . $e->getMessage());
        // Return defaults if database is not available
    }
    
    return $defaults;
}

/**
 * Get AI providers list
 * @return array
 */
function getAIProviders() {
    return $GLOBALS['aiProviders'] ?? [];
}

?>