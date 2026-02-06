<?php
/**
 * Receipt Settings Manager Logic
 */

function getReceiptSettings()
{
    // Create table if it doesn't exist
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS receipt_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_type ENUM('shipping_method', 'item_count', 'item_category', 'default') NOT NULL,
            condition_key VARCHAR(100) NOT NULL,
            condition_value VARCHAR(255) NOT NULL,
            message_title VARCHAR(255) NOT NULL,
            message_content TEXT NOT NULL,
            ai_generated BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_condition (setting_type, condition_key, condition_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    Database::execute($createTableSQL);

    $settings = Database::queryAll("SELECT * FROM receipt_settings ORDER BY setting_type, condition_key, condition_value");
    
    $grouped = ['shipping_method' => [], 'item_count' => [], 'item_category' => [], 'default' => []];
    foreach ($settings as $setting) {
        $grouped[$setting['setting_type']][] = $setting;
    }
    return $grouped;
}

function updateReceiptSettings($input)
{
    if (!isset($input['settings'])) throw new Exception('Settings array required');
    
    Database::beginTransaction();
    try {
        foreach ($input['settings'] as $setting) {
            if (isset($setting['id']) && $setting['id'] > 0) {
                Database::execute("
                    UPDATE receipt_settings 
                    SET condition_key = ?, condition_value = ?, message_title = ?, message_content = ?, ai_generated = ?
                    WHERE id = ?
                ", [
                    $setting['condition_key'], $setting['condition_value'], $setting['message_title'],
                    $setting['message_content'], $setting['ai_generated'] ?? false, $setting['id']
                ]);
            } else {
                Database::execute("
                    INSERT INTO receipt_settings (setting_type, condition_key, condition_value, message_title, message_content, ai_generated)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [
                    $setting['setting_type'], $setting['condition_key'], $setting['condition_value'],
                    $setting['message_title'], $setting['message_content'], $setting['ai_generated'] ?? false
                ]);
            }
        }
        Database::commit();
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}

function generateAIReceiptMessage($context)
{
    $settingType = $context['setting_type'] ?? 'default';
    $conditionKey = $context['condition_key'] ?? '';
    $conditionValue = $context['condition_value'] ?? '';

    $aiProvider = new AIProviders();
    $aiSettings = getAISettingsForReceipt();
    $prompt = buildReceiptMessagePrompt($settingType, $conditionKey, $conditionValue, $aiSettings);

    try {
        $result = $aiProvider->generateReceiptMessage($prompt, $aiSettings);
        return ['success' => true, 'message' => $result, 'ai_generated' => true];
    } catch (Exception $e) {
        $fallback = generateFallbackMessage($settingType, $conditionKey, $conditionValue, $aiSettings);
        return ['success' => true, 'message' => $fallback, 'ai_generated' => false, 'note' => 'AI unavailable: ' . $e->getMessage()];
    }
}

function getAISettingsForReceipt()
{
    $defaults = ['ai_brand_voice' => 'friendly', 'ai_content_tone' => 'professional'];
    $results = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai' AND setting_key IN ('ai_brand_voice', 'ai_content_tone')");
    foreach ($results as $row) {
        $defaults[$row['setting_key']] = $row['setting_value'];
    }
    return $defaults;
}

function buildReceiptMessagePrompt($settingType, $conditionKey, $conditionValue, $aiSettings)
{
    $brandVoice = $aiSettings['ai_brand_voice'] ?? 'friendly';
    $contentTone = $aiSettings['ai_content_tone'] ?? 'professional';
    $contextDescription = "Context: $settingType, $conditionKey, $conditionValue"; // Simplified
    return "Create a personalized order receipt message for WhimsicalFrog Crafts. $contextDescription. Voice: $brandVoice. Tone: $contentTone. Return JSON with 'title' and 'content'.";
}

function generateFallbackMessage($settingType, $conditionKey, $conditionValue, $aiSettings)
{
    return ['title' => 'Order Confirmed', 'content' => 'Your order is being processed with care.'];
}

function initializeDefaultReceiptSettings()
{
    $count = Database::queryOne("SELECT COUNT(*) as count FROM receipt_settings");
    if ($count['count'] > 0) return;

    $defaults = [
        ['default', 'status', 'completed', 'Payment Received', 'Your order is being processed with care.']
    ];
    foreach ($defaults as $d) {
        Database::execute("INSERT INTO receipt_settings (setting_type, condition_key, condition_value, message_title, message_content) VALUES (?, ?, ?, ?, ?)", $d);
    }
}
