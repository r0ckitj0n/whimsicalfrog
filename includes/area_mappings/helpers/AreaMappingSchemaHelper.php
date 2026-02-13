<?php
// includes/area_mappings/helpers/AreaMappingSchemaHelper.php

class AreaMappingSchemaHelper
{
    /**
     * Ensure necessary tables and columns exist
     */
    public static function ensureSchema()
    {
        try {
            Database::execute("CREATE TABLE IF NOT EXISTS area_mappings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_number VARCHAR(50) NOT NULL,
                area_selector VARCHAR(255) NOT NULL,
                mapping_type ENUM('item','category','link','content','button','page','modal','action') NOT NULL DEFAULT 'item',
                item_sku VARCHAR(64) NULL,
                category_id INT NULL,
                link_url TEXT NULL,
                link_label VARCHAR(255) NULL,
                link_icon VARCHAR(255) NULL,
                link_image TEXT NULL,
                content_target TEXT NULL,
                content_image TEXT NULL,
                display_order INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_room_number (room_number),
                INDEX idx_item_sku (item_sku),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Migration logic
            self::runMigrations();

            // Sitemap entries table
            Database::execute("CREATE TABLE IF NOT EXISTS sitemap_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                label VARCHAR(255) NOT NULL,
                kind ENUM('page','modal','action') NOT NULL DEFAULT 'page',
                source VARCHAR(255) DEFAULT 'static',
                is_active TINYINT(1) DEFAULT 1,
                lastmod TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            Database::execute("CREATE TABLE IF NOT EXISTS shortcut_sign_assets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mapping_id INT NOT NULL,
                room_number VARCHAR(50) NOT NULL,
                image_url TEXT NOT NULL,
                png_url TEXT NULL,
                webp_url TEXT NULL,
                source VARCHAR(40) NOT NULL DEFAULT 'unknown',
                is_active TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mapping (mapping_id),
                INDEX idx_room (room_number),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        } catch (Exception $e) {
            error_log("AreaMappingSchemaHelper Error: " . $e->getMessage());
            throw $e;
        }
    }

    private static function runMigrations()
    {
        $migrations = [
            "ALTER TABLE area_mappings ADD COLUMN room_number VARCHAR(50) NOT NULL",
            "CREATE INDEX idx_room_number ON area_mappings (room_number)",
            "ALTER TABLE area_mappings ADD COLUMN item_sku VARCHAR(64) NULL",
            "ALTER TABLE area_mappings ADD COLUMN link_url TEXT NULL",
            "ALTER TABLE area_mappings ADD COLUMN link_label VARCHAR(255) NULL",
            "ALTER TABLE area_mappings ADD COLUMN link_icon VARCHAR(255) NULL",
            "ALTER TABLE area_mappings MODIFY COLUMN mapping_type ENUM('item','category','link','content','button','page','modal','action') NOT NULL DEFAULT 'item'",
            "ALTER TABLE area_mappings ADD COLUMN link_image TEXT NULL",
            "ALTER TABLE area_mappings ADD COLUMN content_target TEXT NULL",
            "ALTER TABLE area_mappings ADD COLUMN content_image TEXT NULL",
            "CREATE INDEX idx_item_sku ON area_mappings (item_sku)",
            "ALTER TABLE sitemap_entries MODIFY COLUMN kind ENUM('page','modal','action') NOT NULL DEFAULT 'page'"
        ];

        foreach ($migrations as $sql) {
            try {
                Database::execute($sql);
            } catch (Exception $e) {
                // @reason: Idempotent DDL - column/index may already exist
            }
        }

        // Special check for is_active
        try {
            if (!self::hasColumn('area_mappings', 'is_active')) {
                Database::execute("ALTER TABLE area_mappings ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                Database::execute("CREATE INDEX idx_active ON area_mappings (is_active)");
            }
            // @reason: Idempotent DDL - column/index may already exist
        } catch (Exception $e) {
        }

        try {
            if (!self::hasColumn('shortcut_sign_assets', 'source')) {
                Database::execute("ALTER TABLE shortcut_sign_assets ADD COLUMN source VARCHAR(40) NOT NULL DEFAULT 'unknown'");
            }
            if (!self::hasColumn('shortcut_sign_assets', 'is_active')) {
                Database::execute("ALTER TABLE shortcut_sign_assets ADD COLUMN is_active TINYINT(1) DEFAULT 0");
            }
        } catch (Exception $e) {
        }

        // Migrate room_type to room_number
        try {
            Database::execute("UPDATE area_mappings SET room_number = SUBSTRING(room_type, 5) WHERE (room_number IS NULL OR room_number = '') AND room_type REGEXP '^room[0-9]+$'");
            // @reason: Graceful migration - source column may not exist
        } catch (Exception $e) {
        }
    }

    public static function hasColumn($table, $column)
    {
        try {
            $row = Database::queryOne("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
            return $row && (int) $row['c'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
