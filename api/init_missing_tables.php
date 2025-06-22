<?php
/**
 * Initialize Missing Database Tables for WhimsicalFrog Live Server
 * This script creates all tables that exist in local but might be missing on live
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database successfully.\n";
    
    // List of tables to create with their SQL
    $tables = [
        'business_settings' => "CREATE TABLE IF NOT EXISTS `business_settings` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'ai_models' => "CREATE TABLE IF NOT EXISTS `ai_models` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `provider` varchar(50) NOT NULL,
            `model_name` varchar(100) NOT NULL,
            `display_name` varchar(150) NOT NULL,
            `capabilities` json DEFAULT NULL,
            `max_tokens` int(11) DEFAULT NULL,
            `cost_per_1k_tokens` decimal(10,6) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT '1',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_model` (`provider`,`model_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'global_css_rules' => "CREATE TABLE IF NOT EXISTS `global_css_rules` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `category` varchar(50) NOT NULL DEFAULT 'general',
            `rule_name` varchar(100) NOT NULL,
            `css_property` varchar(100) NOT NULL,
            `css_value` text NOT NULL,
            `description` text,
            `is_active` tinyint(1) DEFAULT '1',
            `display_order` int(11) DEFAULT '0',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_rule` (`rule_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'room_settings' => "CREATE TABLE IF NOT EXISTS `room_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `room_id` int(11) NOT NULL,
            `room_name` varchar(100) NOT NULL,
            `door_label` varchar(100) DEFAULT NULL,
            `description` text,
            `display_order` int(11) DEFAULT '0',
            `is_active` tinyint(1) DEFAULT '1',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_room` (`room_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'website_config' => "CREATE TABLE IF NOT EXISTS `website_config` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `config_key` varchar(100) NOT NULL,
            `config_value` text,
            `category` varchar(50) DEFAULT 'general',
            `description` text,
            `data_type` enum('text','number','boolean','json','array') DEFAULT 'text',
            `is_system` tinyint(1) DEFAULT '0',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_config_key` (`config_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'css_variables' => "CREATE TABLE IF NOT EXISTS `css_variables` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `variable_name` varchar(100) NOT NULL,
            `variable_value` text NOT NULL,
            `category` varchar(50) DEFAULT 'general',
            `description` text,
            `is_active` tinyint(1) DEFAULT '1',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_variable` (`variable_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'ui_components' => "CREATE TABLE IF NOT EXISTS `ui_components` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `component_name` varchar(100) NOT NULL,
            `component_config` json DEFAULT NULL,
            `category` varchar(50) DEFAULT 'general',
            `description` text,
            `is_active` tinyint(1) DEFAULT '1',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_component` (`component_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'pricing_explanations' => "CREATE TABLE IF NOT EXISTS `pricing_explanations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sku` varchar(50) NOT NULL,
            `explanation_type` enum('cost','price','both') DEFAULT 'both',
            `explanation_text` text NOT NULL,
            `confidence_score` decimal(3,2) DEFAULT NULL,
            `ai_model_used` varchar(100) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sku` (`sku`),
            KEY `idx_type` (`explanation_type`),
            CONSTRAINT `pricing_explanations_ibfk_1` FOREIGN KEY (`sku`) REFERENCES `items` (`sku`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'email_logs' => "CREATE TABLE IF NOT EXISTS `email_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `recipient` varchar(255) NOT NULL,
            `subject` varchar(500) NOT NULL,
            `message` text,
            `status` enum('sent','failed','pending') DEFAULT 'pending',
            `error_message` text,
            `sent_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_recipient` (`recipient`),
            KEY `idx_status` (`status`),
            KEY `idx_sent_at` (`sent_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    // Create each table
    foreach ($tables as $tableName => $sql) {
        try {
            echo "Creating table: $tableName\n";
            $pdo->exec($sql);
            echo "✅ Table $tableName created successfully\n";
        } catch (PDOException $e) {
            echo "⚠️  Table $tableName: " . $e->getMessage() . "\n";
        }
    }
    
    // Insert default business settings for AI functionality
    echo "\nInserting default business settings...\n";
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
    
    // Insert default room settings
    echo "\nInserting default room settings...\n";
    $defaultRooms = [
        [0, 'Landing Page', 'Welcome', 'Welcome to WhimsicalFrog - Your Custom Craft Destination', 0],
        [1, 'Main Room', 'Enter', 'Choose your adventure! Click on any door to explore our products.', 1],
        [2, 'T-Shirts Room', 'T-Shirts', 'Custom T-Shirts - Express yourself with our unique designs', 2],
        [3, 'Tumblers Room', 'Tumblers', 'Custom Tumblers - Keep your drinks perfect temperature in style', 3],
        [4, 'Artwork Room', 'Artwork', 'Custom Artwork - Beautiful pieces to decorate your space', 4],
        [5, 'Sublimation Room', 'Sublimation', 'Sublimation Products - Vibrant, lasting designs on various items', 5],
        [6, 'Window Wraps Room', 'Window Wraps', 'Custom Window Wraps - Transform your windows with style', 6]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO room_settings (room_id, room_name, door_label, description, display_order) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($defaultRooms as $room) {
        try {
            $stmt->execute($room);
            echo "✅ Added room: {$room[1]}\n";
        } catch (PDOException $e) {
            echo "⚠️  Room {$room[1]}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Database initialization completed!\n";
    echo "All missing tables have been created and default data inserted.\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?> 