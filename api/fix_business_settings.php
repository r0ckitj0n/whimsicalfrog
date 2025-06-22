<?php
/**
 * Fix Business Settings Table Structure
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.\n";
    
    // Check current table structure
    echo "Checking business_settings table structure...\n";
    
    try {
        $stmt = $pdo->query("DESCRIBE business_settings");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Current columns: " . implode(", ", $columns) . "\n";
        
        // Add missing columns if they don't exist
        $requiredColumns = [
            'data_type' => "ALTER TABLE business_settings ADD COLUMN data_type enum('text','number','boolean','json','color','email','url') DEFAULT 'text' AFTER setting_value",
            'description' => "ALTER TABLE business_settings ADD COLUMN description text AFTER data_type",
            'is_required' => "ALTER TABLE business_settings ADD COLUMN is_required tinyint(1) DEFAULT '0' AFTER description"
        ];
        
        foreach ($requiredColumns as $column => $sql) {
            if (!in_array($column, $columns)) {
                echo "Adding missing column: $column\n";
                $pdo->exec($sql);
                echo "✅ Column $column added successfully\n";
            } else {
                echo "✅ Column $column already exists\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "Table doesn't exist, creating it...\n";
        $createSQL = "CREATE TABLE IF NOT EXISTS `business_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `category` varchar(50) NOT NULL DEFAULT 'general',
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text,
            `data_type` enum('text','number','boolean','json','color','email','url') DEFAULT 'text',
            `description` text,
            `is_required` tinyint(1) DEFAULT '0',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_setting` (`category`,`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createSQL);
        echo "✅ Table business_settings created successfully\n";
    }
    
    // Insert default AI settings
    echo "\nInserting default AI settings...\n";
    $defaultSettings = [
        ['ai', 'ai_cost_temperature', '0.7', 'number', 'AI temperature for cost suggestions (0.1-1.0)', 1],
        ['ai', 'ai_price_temperature', '0.7', 'number', 'AI temperature for price suggestions (0.1-1.0)', 1],
        ['ai', 'ai_cost_multiplier_base', '1.0', 'number', 'Base multiplier for cost calculations', 1],
        ['ai', 'ai_price_multiplier_base', '1.0', 'number', 'Base multiplier for price calculations', 1],
        ['ai', 'ai_conservative_mode', 'false', 'boolean', 'Enable conservative AI mode', 0],
        ['ai', 'ai_market_research_weight', '0.3', 'number', 'Weight for market research in pricing', 0],
        ['ai', 'ai_cost_plus_weight', '0.4', 'number', 'Weight for cost-plus pricing', 0],
        ['ai', 'ai_value_based_weight', '0.3', 'number', 'Weight for value-based pricing', 0],
        ['branding', 'primary_color', '#87ac3a', 'color', 'Primary brand color', 1],
        ['branding', 'secondary_color', '#6b8e23', 'color', 'Secondary brand color', 1]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO business_settings (category, setting_key, setting_value, data_type, description, is_required) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($defaultSettings as $setting) {
        try {
            $stmt->execute($setting);
            echo "✅ Added setting: {$setting[0]}.{$setting[1]}\n";
        } catch (PDOException $e) {
            echo "⚠️  Setting {$setting[0]}.{$setting[1]}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Business settings table fixed and populated!\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?> 