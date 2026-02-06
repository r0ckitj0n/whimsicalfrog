<?php
/**
 * Log Table Schema Definitions for DatabaseLogger
 */
class LogTableDefinitions
{
    public static function getSchemas()
    {
        return [
            'analytics_logs' => "CREATE TABLE IF NOT EXISTS analytics_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id VARCHAR(255),
                page_url VARCHAR(500),
                event_type VARCHAR(100),
                event_data JSON,
                user_agent TEXT,
                ip_address VARCHAR(45),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_user_id (user_id),
                INDEX idx_event_type (event_type)
            )",

            'error_logs' => "CREATE TABLE IF NOT EXISTS error_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL DEFAULT 'ERROR',
                message TEXT NOT NULL,
                context_data JSON,
                user_id INT NULL,
                file_path VARCHAR(500),
                line_number INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_error_type (error_type),
                INDEX idx_file_path (file_path)
            )",

            'user_activity_logs' => "CREATE TABLE IF NOT EXISTS user_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id VARCHAR(255),
                activity_type VARCHAR(100) NOT NULL,
                activity_description TEXT,
                target_type VARCHAR(100),
                target_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_activity_type (activity_type)
            )",

            'admin_activity_logs' => "CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_user_id INT NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                action_description TEXT,
                target_type VARCHAR(100),
                target_id INT,
                ip_address VARCHAR(45),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_user_id (admin_user_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_action_type (action_type)
            )",

            'order_logs' => "CREATE TABLE IF NOT EXISTS order_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id VARCHAR(50) NOT NULL,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                log_message TEXT,
                previous_status VARCHAR(50),
                new_status VARCHAR(50),
                admin_user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id),
                INDEX idx_created_at (created_at),
                INDEX idx_action (action)
            )",

            'inventory_logs' => "CREATE TABLE IF NOT EXISTS inventory_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_sku VARCHAR(50) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                change_description TEXT,
                old_quantity INT,
                new_quantity INT,
                old_price DECIMAL(10,2),
                new_price DECIMAL(10,2),
                user_id INT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_item_sku (item_sku),
                INDEX idx_timestamp (timestamp),
                INDEX idx_action_type (action_type)
            )",

            'email_logs' => "CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                from_email VARCHAR(255),
                email_subject VARCHAR(500),
                email_type VARCHAR(100),
                status VARCHAR(50) DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sent_at (sent_at),
                INDEX idx_to_email (to_email),
                INDEX idx_status (status)
            )"
        ];
    }
}
