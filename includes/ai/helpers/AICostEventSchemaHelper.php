<?php
// includes/ai/helpers/AICostEventSchemaHelper.php

/**
 * AI generation cost event schema helper.
 *
 * Stores actual job-count based costs for AI runs (not estimates).
 * This is intentionally job-based to match AIPricingStore:
 * - text_generation
 * - image_analysis
 * - image_creation
 */
class AICostEventSchemaHelper
{
    public static function ensureSchema(): void
    {
        Database::execute(
            "CREATE TABLE IF NOT EXISTS ai_generation_cost_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sku VARCHAR(50) NULL,
                endpoint VARCHAR(80) NOT NULL,
                step VARCHAR(32) NULL,
                provider VARCHAR(80) NOT NULL DEFAULT 'default',
                model VARCHAR(120) NULL,
                week_start DATE NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                text_jobs INT NOT NULL DEFAULT 0,
                image_analysis_jobs INT NOT NULL DEFAULT 0,
                image_creation_jobs INT NOT NULL DEFAULT 0,
                total_cost_cents INT NOT NULL DEFAULT 0,
                is_fallback_pricing TINYINT(1) NOT NULL DEFAULT 0,
                fallback_note VARCHAR(255) NULL,
                fallback_reasons JSON NULL,
                pricing_rates JSON NULL,
                request_meta JSON NULL,
                INDEX idx_sku_used_at (sku, used_at),
                INDEX idx_provider_week (provider, week_start),
                INDEX idx_endpoint (endpoint),
                INDEX idx_used_at (used_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

