<?php
// includes/ai/helpers/AIPricingSchemaHelper.php

/**
 * AI job pricing schema helper.
 *
 * Stores weekly per-job prices (in cents) in MySQL. This is intentionally job-based
 * (text generation / image analysis / image creation) rather than token-based.
 */

class AIPricingSchemaHelper
{
    public static function ensureSchema(): void
    {
        // Prices are stored in cents to avoid float precision issues.
        Database::execute(
            "CREATE TABLE IF NOT EXISTS ai_job_pricing_rates (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                week_start DATE NOT NULL,
                provider VARCHAR(80) NOT NULL DEFAULT 'default',
                job_type VARCHAR(60) NOT NULL,
                unit_cost_cents INT NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                source VARCHAR(40) NOT NULL DEFAULT 'fallback',
                note VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_week_provider_job (week_start, provider, job_type),
                INDEX idx_provider_week (provider, week_start),
                INDEX idx_job_type (job_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

