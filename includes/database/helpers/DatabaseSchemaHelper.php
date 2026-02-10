<?php
// includes/database/helpers/DatabaseSchemaHelper.php

require_once __DIR__ . '/../../Constants.php';

class DatabaseSchemaHelper
{
    /**
     * Get database schema information
     */
    public static function getDatabaseSchema()
    {
        try {
            $pdo = Database::getInstance();
            global $db;
            $quotedDb = $pdo->quote($db);

            $stmt = $pdo->query("
                SELECT 
                    table_name AS table_name,
                    table_rows AS table_rows,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = $quotedDb
                ORDER BY table_name
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $schemaInfo = [];
            foreach ($tables as $table) {
                $tableName = $table['table_name'] ?? $table['TABLE_NAME'] ?? 'Unknown';
                $tableRows = $table['table_rows'] ?? $table['TABLE_ROWS'] ?? 0;
                $tableSize = $table['size_mb'] ?? 0;

                if ($tableName === 'Unknown' || empty($tableName)) {
                    continue;
                }

                $stmt = $pdo->query("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_schema = $quotedDb
                    AND table_name = " . $pdo->quote($tableName) . "
                    ORDER BY ordinal_position
                ");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $schemaInfo[] = [
                    'name' => $tableName,
                    'rows' => $tableRows !== null ? $tableRows : 0,
                    'size' => $tableSize . ' MB',
                    'columns' => $columns ?: []
                ];
            }

            return [
                'success' => true,
                'tables' => $schemaInfo
            ];
        } catch (Exception $e) {
            throw new Exception('Schema retrieval failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize database with default tables and data
     */
    public static function initializeDatabase()
    {
        try {
            $startTime = microtime(true);
            $pdo = Database::getInstance();

            $tablesCreated = 0;
            $tablesSkipped = 0;
            $defaultRecords = 0;
            $details = [];
            $warnings = [];

            // Define tables (this should probably be loaded from a schema file, but following existing logic)
            $tables = self::getCoreSchemaDefinitions();

            foreach ($tables as $tableName => $sql) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
                    if ($stmt->rowCount() > 0) {
                        $tablesSkipped++;
                        $details[] = ['type' => 'skip', 'message' => "Table '$tableName' already exists, skipped"];
                    } else {
                        $pdo->exec($sql);
                        $tablesCreated++;
                        $details[] = ['type' => 'success', 'message' => "Created table '$tableName'"];
                    }
                } catch (Exception $e) {
                    $warnings[] = "Failed to create table '$tableName': " . $e->getMessage();
                    $details[] = ['type' => 'error', 'message' => "Failed to create table '$tableName': " . $e->getMessage()];
                }
            }

            // Insert default data logic
            $dataResults = self::insertDefaultData($pdo);
            $defaultRecords = $dataResults['records'];
            $details = array_merge($details, $dataResults['details']);
            $warnings = array_merge($warnings, $dataResults['warnings']);

            return [
                'success' => true,
                'tables_created' => $tablesCreated,
                'tables_skipped' => $tablesSkipped,
                'default_records' => $defaultRecords,
                'execution_time' => round((microtime(true) - $startTime), 2) . ' seconds',
                'details' => $details,
                'warnings' => $warnings
            ];
        } catch (Exception $e) {
            throw new Exception('Database initialization failed: ' . $e->getMessage());
        }
    }

    private static function getCoreSchemaDefinitions()
    {
        return [
            'items' => "
                CREATE TABLE IF NOT EXISTS `items` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `sku` varchar(50) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `description` text,
                    `price` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `retail_price` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `stock_quantity` int(11) NOT NULL DEFAULT '0',
                    `reorder_point` int(11) DEFAULT '0',
                    `category_id` int(11) DEFAULT NULL,
                    `gender` varchar(20) DEFAULT NULL,
                    `status` enum('draft','live') DEFAULT 'draft',
                    `image_url` varchar(500) DEFAULT NULL,
                    `weight` varchar(100) DEFAULT NULL,
                    `weight_oz` decimal(8,2) DEFAULT NULL,
                    `package_length_in` decimal(8,2) DEFAULT NULL,
                    `package_width_in` decimal(8,2) DEFAULT NULL,
                    `package_height_in` decimal(8,2) DEFAULT NULL,
                    `materials` text,
                    `dimensions` varchar(255) DEFAULT NULL,
                    `care_instructions` text,
                    `technical_details` text,
                    `features` text,
                    `color_options` text,
                    `size_options` text,
                    `production_time` varchar(100) DEFAULT NULL,
                    `customization_options` text,
                    `usage_tips` text,
                    `warranty_info` varchar(255) DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT '1',
                    `is_archived` tinyint(1) DEFAULT '0',
                    `archived_at` datetime DEFAULT NULL,
                    `archived_by` varchar(255) DEFAULT NULL,
                    `ai_cost_confidence` decimal(5,4) DEFAULT NULL,
                    `ai_cost_at` timestamp NULL DEFAULT NULL,
                    `ai_price_confidence` decimal(5,4) DEFAULT NULL,
                    `ai_price_at` timestamp NULL DEFAULT NULL,
                    `locked_fields` JSON DEFAULT NULL,
                    `locked_words` JSON DEFAULT NULL,
                    `quality_tier` varchar(20) DEFAULT 'standard',
                    `cost_quality_tier` varchar(20) DEFAULT 'standard',
                    `price_quality_tier` varchar(20) DEFAULT 'standard',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `sku` (`sku`),
                    KEY `category_id` (`category_id`),
                    KEY `status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'users' => "
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` varchar(16) NOT NULL,
                    `username` varchar(64) NOT NULL,
                    `password` varchar(255) NOT NULL,
                    `email` varchar(128) NOT NULL,
                    `role` varchar(32) DEFAULT NULL,
                    `first_name` varchar(64) DEFAULT NULL,
                    `last_name` varchar(64) DEFAULT NULL,
                    `phone_number` varchar(32) DEFAULT NULL,
                    `is_protected` tinyint(1) DEFAULT '0',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `username` (`username`),
                    UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'orders' => "
                CREATE TABLE IF NOT EXISTS `orders` (
                    `id` varchar(25) NOT NULL,
                    `user_id` varchar(64) NOT NULL,
                    `cashier_id` varchar(64) DEFAULT NULL,
                    `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `discount_amount` decimal(10,2) DEFAULT '0.00',
                    `coupon_code` varchar(50) DEFAULT NULL,
                    `payment_method` varchar(50) DEFAULT NULL,
                    `check_number` varchar(64) DEFAULT NULL,
                    `shipping_address` text,
                    `status` varchar(50) DEFAULT NULL,
                    `tracking_number` varchar(100) DEFAULT NULL,
                    `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
                    `payment_at` datetime DEFAULT NULL,
                    `payment_notes` text,
                    `fulfillment_notes` text,
                    `shipping_method` varchar(100) DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    KEY `status` (`status`),
                    KEY `payment_status` (`payment_status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'order_items' => "
                CREATE TABLE IF NOT EXISTS `order_items` (
                    `id` varchar(32) NOT NULL,
                    `order_id` varchar(25) NOT NULL,
                    `sku` varchar(50) NOT NULL,
                    `quantity` int(11) NOT NULL DEFAULT '1',
                    `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `color` varchar(50) DEFAULT NULL,
                    `size` varchar(32) DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `order_id` (`order_id`),
                    KEY `sku` (`sku`),
                    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'categories' => "
                CREATE TABLE IF NOT EXISTS `categories` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `slug` varchar(100) NOT NULL,
                    `description` text,
                    `parent_id` int(11) DEFAULT NULL,
                    `sort_order` int(11) DEFAULT '0',
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `slug` (`slug`),
                    KEY `parent_id` (`parent_id`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'website_configs' => "
                CREATE TABLE IF NOT EXISTS `website_configs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `category` varchar(50) DEFAULT 'general',
                    `setting_key` varchar(100) NOT NULL,
                    `setting_value` text,
                    `setting_type` varchar(50) DEFAULT 'text',
                    `description` text,
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_key` (`setting_key`),
                    KEY `category` (`category`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'users_meta' => "
                CREATE TABLE IF NOT EXISTS `users_meta` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` VARCHAR(255) NOT NULL,
                    `meta_key` VARCHAR(191) NOT NULL,
                    `meta_value` TEXT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uniq_user_meta` (`user_id`, `meta_key`),
                    KEY `idx_user` (`user_id`),
                    KEY `idx_key` (`meta_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'addresses' => "
                CREATE TABLE IF NOT EXISTS `addresses` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `owner_type` VARCHAR(32) NOT NULL DEFAULT 'customer',
                    `owner_id` VARCHAR(64) NOT NULL,
                    `address_name` VARCHAR(100) NOT NULL,
                    `address_line_1` VARCHAR(255) NOT NULL,
                    `address_line_2` VARCHAR(255) NULL,
                    `city` VARCHAR(100) NOT NULL,
                    `state` VARCHAR(50) NOT NULL,
                    `zip_code` VARCHAR(20) NOT NULL,
                    `is_default` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_owner` (`owner_type`, `owner_id`),
                    KEY `idx_owner_default` (`owner_type`, `owner_id`, `is_default`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }

    private static function insertDefaultData($pdo)
    {
        $records = 0;
        $details = [];
        $warnings = [];

        $defaultData = [
            'users' => [
                'check' => "SELECT COUNT(*) FROM users WHERE username = '" . WF_Constants::ROLE_ADMIN . "'",
                'insert' => "INSERT INTO users (id, username, email, password, role, first_name, last_name) 
                           VALUES ('ADM001', ?, ?, ?, ?, 'System', 'Administrator')",
                'data' => [WF_Constants::ROLE_ADMIN, 'admin@whimsicalfrog.com', password_hash('admin123', PASSWORD_DEFAULT), WF_Constants::ROLE_ADMIN],
                'description' => 'Default admin user'
            ],
            'website_configs' => [
                'multiple' => [
                    [
                        'check' => "SELECT COUNT(*) FROM website_configs WHERE setting_key = 'site_name'",
                        'insert' => "INSERT INTO website_configs (setting_key, setting_value, setting_type, description, category) 
                                   VALUES ('site_name', 'WhimsicalFrog', 'text', 'Website name', 'general')",
                        'description' => 'Site name setting'
                    ]
                ]
            ]
        ];

        foreach ($defaultData as $table => $config) {
            try {
                if (isset($config['multiple'])) {
                    foreach ($config['multiple'] as $record) {
                        if ($pdo->query($record['check'])->fetchColumn() == 0) {
                            $pdo->exec($record['insert']);
                            $records++;
                            $details[] = ['type' => 'success', 'message' => "Added: " . $record['description']];
                        }
                    }
                } else {
                    if ($pdo->query($config['check'])->fetchColumn() == 0) {
                        $stmt = $pdo->prepare($config['insert']);
                        $stmt->execute($config['data'] ?? []);
                        $records++;
                        $details[] = ['type' => 'success', 'message' => "Added: " . $config['description']];
                    }
                }
            } catch (Exception $e) {
                $warnings[] = "Failed to insert default data for '$table': " . $e->getMessage();
            }
        }

        return ['records' => $records, 'details' => $details, 'warnings' => $warnings];
    }
}
