<?php
require_once 'config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // AI Settings for cost and price suggestions
    $aiSettings = [
        [
            'setting_key' => 'ai_cost_temperature',
            'setting_value' => '0.7',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Cost Suggestion Temperature',
            'description' => 'Controls AI creativity for cost suggestions (0.1 = conservative, 1.0 = creative). Lower values produce more consistent, predictable costs.',
            'is_required' => true,
            'display_order' => 1
        ],
        [
            'setting_key' => 'ai_price_temperature',
            'setting_value' => '0.7',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Price Suggestion Temperature',
            'description' => 'Controls AI creativity for price suggestions (0.1 = conservative, 1.0 = creative). Lower values produce more consistent, market-standard pricing.',
            'is_required' => true,
            'display_order' => 2
        ],
        [
            'setting_key' => 'ai_cost_multiplier_base',
            'setting_value' => '1.0',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Cost Base Multiplier',
            'description' => 'Base multiplier for all cost calculations (0.5 = 50% of calculated costs, 2.0 = 200% of calculated costs). Adjusts overall cost suggestions.',
            'is_required' => true,
            'display_order' => 3
        ],
        [
            'setting_key' => 'ai_price_multiplier_base',
            'setting_value' => '1.0',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Price Base Multiplier',
            'description' => 'Base multiplier for all price calculations (0.5 = 50% of calculated prices, 2.0 = 200% of calculated prices). Adjusts overall price suggestions.',
            'is_required' => true,
            'display_order' => 4
        ],
        [
            'setting_key' => 'ai_conservative_mode',
            'setting_value' => 'false',
            'setting_type' => 'boolean',
            'category' => 'ai',
            'display_name' => 'Conservative Mode',
            'description' => 'When enabled, AI suggestions will be more conservative and closer to standard industry practices. Reduces variability in suggestions.',
            'is_required' => true,
            'display_order' => 5
        ],
        [
            'setting_key' => 'ai_market_research_weight',
            'setting_value' => '0.3',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Market Research Weight',
            'description' => 'How much weight to give market research in pricing decisions (0.0 = ignore market, 1.0 = heavily favor market data). Affects price competitiveness.',
            'is_required' => true,
            'display_order' => 6
        ],
        [
            'setting_key' => 'ai_cost_plus_weight',
            'setting_value' => '0.4',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Cost-Plus Weight',
            'description' => 'How much weight to give cost-plus pricing in final price decisions (0.0 = ignore costs, 1.0 = heavily favor cost-based pricing). Affects profit margins.',
            'is_required' => true,
            'display_order' => 7
        ],
        [
            'setting_key' => 'ai_value_based_weight',
            'setting_value' => '0.3',
            'setting_type' => 'number',
            'category' => 'ai',
            'display_name' => 'Value-Based Weight',
            'description' => 'How much weight to give value-based pricing in final decisions (0.0 = ignore value, 1.0 = heavily favor value pricing). Affects premium pricing.',
            'is_required' => true,
            'display_order' => 8
        ]
    ];
    
    $insertStmt = $pdo->prepare("
        INSERT INTO business_settings (
            setting_key, setting_value, setting_type, category, 
            display_name, description, is_required, display_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            setting_type = VALUES(setting_type),
            category = VALUES(category),
            display_name = VALUES(display_name),
            description = VALUES(description),
            is_required = VALUES(is_required),
            display_order = VALUES(display_order)
    ");
    
    $addedCount = 0;
    foreach ($aiSettings as $setting) {
        try {
            $insertStmt->execute([
                $setting['setting_key'],
                $setting['setting_value'],
                $setting['setting_type'],
                $setting['category'],
                $setting['display_name'],
                $setting['description'],
                $setting['is_required'],
                $setting['display_order']
            ]);
            $addedCount++;
        } catch (PDOException $e) {
            echo "Warning: Could not add setting {$setting['setting_key']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "✅ AI Settings initialized successfully!\n";
    echo "📊 Added/updated $addedCount AI configuration settings\n";
    echo "🎯 You can now adjust AI temperature and behavior in Admin Settings > Business Settings > AI category\n";
    
} catch (Exception $e) {
    echo "❌ Error initializing AI settings: " . $e->getMessage() . "\n";
}
?> 