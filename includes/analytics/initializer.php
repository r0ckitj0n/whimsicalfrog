<?php
/**
 * Analytics Table Initializer
 */

function initializeAnalyticsTables()
{
    $sessions = "CREATE TABLE IF NOT EXISTS analytics_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        user_id INT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        referrer TEXT,
        landing_page VARCHAR(500),
        utm_source VARCHAR(255),
        utm_medium VARCHAR(255),
        utm_campaign VARCHAR(255),
        utm_term VARCHAR(255),
        utm_content VARCHAR(255),
        device_type ENUM('desktop','tablet','mobile') DEFAULT 'desktop',
        browser VARCHAR(100),
        operating_system VARCHAR(100),
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        total_page_views INT DEFAULT 0,
        bounce BOOLEAN DEFAULT TRUE,
        converted BOOLEAN DEFAULT FALSE,
        conversion_value DECIMAL(10,2) DEFAULT 0,
        UNIQUE KEY uniq_session_id (session_id),
        INDEX idx_started_at (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $page_views = "CREATE TABLE IF NOT EXISTS page_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        page_url VARCHAR(500),
        page_title VARCHAR(255),
        page_type VARCHAR(100),
        item_sku VARCHAR(50),
        time_on_page INT DEFAULT 0,
        scroll_depth INT DEFAULT 0,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        exit_page BOOLEAN DEFAULT FALSE,
        INDEX idx_session_id (session_id),
        INDEX idx_viewed_at (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $interactions = "CREATE TABLE IF NOT EXISTS user_interactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        page_url VARCHAR(500),
        interaction_type ENUM('click', 'hover', 'scroll', 'form_submit', 'search', 'filter', 'cart_add', 'cart_remove', 'checkout_start', 'checkout_complete') NOT NULL,
        element_type VARCHAR(100),
        element_id VARCHAR(255),
        element_text TEXT,
        item_sku VARCHAR(50),
        interaction_data JSON,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_id (session_id),
        INDEX idx_interaction_type (interaction_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $item_analytics = "CREATE TABLE IF NOT EXISTS item_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_sku VARCHAR(50) NOT NULL,
        views_count INT DEFAULT 0,
        unique_views_count INT DEFAULT 0,
        cart_adds_count INT DEFAULT 0,
        cart_removes_count INT DEFAULT 0,
        purchases_count INT DEFAULT 0,
        avg_time_on_page DECIMAL(8,2) DEFAULT 0,
        bounce_rate DECIMAL(5,2) DEFAULT 0,
        conversion_rate DECIMAL(5,2) DEFAULT 0,
        revenue_generated DECIMAL(10,2) DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_sku (item_sku)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $optimization = "CREATE TABLE IF NOT EXISTS optimization_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        suggestion_type ENUM('performance', 'conversion', 'ui_ux', 'content', 'item', 'marketing') NOT NULL,
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        title VARCHAR(255) NOT NULL,
        description TEXT,
        suggested_action TEXT,
        data_source TEXT,
        confidence_score DECIMAL(3,2) DEFAULT 0.5,
        potential_impact ENUM('low', 'medium', 'high') DEFAULT 'medium',
        implementation_effort ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        status ENUM('new', 'reviewed', 'implemented', 'dismissed') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    Database::execute($sessions);
    Database::execute($page_views);
    Database::execute($interactions);
    Database::execute($item_analytics);
    Database::execute($optimization);
}
