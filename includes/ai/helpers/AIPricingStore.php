<?php
// includes/ai/helpers/AIPricingStore.php

require_once __DIR__ . '/AIPricingSchemaHelper.php';

/**
 * Weekly-cached AI job pricing with fallback pricing.
 *
 * Fallback pricing (user-provided):
 * - text_generation: 1 cent per job
 * - image_creation: 3 cents per job
 * - image_analysis: 2 cents per job
 *
 * Notes:
 * - Prices are stored in MySQL in cents.
 * - We do not call an AI provider to "estimate" pricing. We only read weekly stored rates.
 * - When there is no stored price for the week, we copy the most recent stored rate for that provider/job.
 * - Only if we cannot find any stored rate at all do we insert fallback pricing and mark it as fallback.
 */
class AIPricingStore
{
    public const JOB_TEXT_GENERATION = 'text_generation';
    public const JOB_IMAGE_CREATION = 'image_creation';
    public const JOB_IMAGE_ANALYSIS = 'image_analysis';

    private const FALLBACK_CENTS = [
        self::JOB_TEXT_GENERATION => 1,
        self::JOB_IMAGE_CREATION => 3,
        self::JOB_IMAGE_ANALYSIS => 2,
    ];

    /**
     * @return array{
     *   week_start:string,
     *   provider:string,
     *   currency:string,
     *   rates: array<string, array{job_type:string, unit_cost_cents:int, unit_cost_usd:float, currency:string, source:string, note:string}>,
     *   is_fallback:bool,
     *   fallback_note:string,
     *   fallback_reasons:string[]
     * }
     */
    public static function getWeeklyRates(string $provider, ?string $weekStart = null): array
    {
        Database::getInstance();
        AIPricingSchemaHelper::ensureSchema();

        $provider = trim(strtolower($provider)) !== '' ? trim(strtolower($provider)) : 'default';
        $weekStart = $weekStart ?: self::weekStartUtc();

        $jobTypes = array_keys(self::FALLBACK_CENTS);
        $rates = [];
        $anyFallback = false;
        $fallbackNote = '';
        $fallbackReasons = [];

        // Attempt to load all rates for this provider/week.
        $rows = Database::queryAll(
            "SELECT job_type, unit_cost_cents, currency, source, note
             FROM ai_job_pricing_rates
             WHERE provider = ? AND week_start = ?",
            [$provider, $weekStart]
        );
        foreach ($rows as $row) {
            $jt = (string)($row['job_type'] ?? '');
            if ($jt === '') {
                continue;
            }
            $cents = (int)($row['unit_cost_cents'] ?? 0);
            $currency = (string)($row['currency'] ?? 'USD');
            $source = (string)($row['source'] ?? 'stored');
            $note = (string)($row['note'] ?? '');
            $rates[$jt] = [
                'job_type' => $jt,
                'unit_cost_cents' => $cents,
                'unit_cost_usd' => round($cents / 100, 4),
                'currency' => $currency,
                'source' => $source,
                'note' => $note,
            ];

            if (self::isFallbackSource($source)) {
                $anyFallback = true;
                $fallbackReasons[] = $note !== ''
                    ? $note
                    : "Fallback pricing: stored rate marked as fallback for {$provider}/{$jt}.";
            }
        }

        // Fill any missing job types by copying last-known stored rates (provider-specific), else fallback.
        foreach ($jobTypes as $jobType) {
            if (isset($rates[$jobType])) {
                continue;
            }

            $latest = Database::queryOne(
                "SELECT unit_cost_cents, currency
                 FROM ai_job_pricing_rates
                 WHERE provider = ? AND job_type = ?
                 ORDER BY week_start DESC
                 LIMIT 1",
                [$provider, $jobType]
            );

            if (is_array($latest) && isset($latest['unit_cost_cents'])) {
                $copiedCents = (int)$latest['unit_cost_cents'];
                $currency = (string)($latest['currency'] ?? 'USD');
                self::upsertRate($weekStart, $provider, $jobType, $copiedCents, $currency, 'stored_copy', 'Copied from most recent stored rate.');
                $rates[$jobType] = [
                    'job_type' => $jobType,
                    'unit_cost_cents' => $copiedCents,
                    'unit_cost_usd' => round($copiedCents / 100, 4),
                    'currency' => $currency,
                    'source' => 'stored_copy',
                    'note' => 'Copied from most recent stored rate.',
                ];
                continue;
            }

            // No stored price available at all -> fallback.
            $fallbackCents = (int)(self::FALLBACK_CENTS[$jobType] ?? 0);
            $note = "Fallback pricing: no stored price available for {$provider}/{$jobType}.";
            self::upsertRate($weekStart, $provider, $jobType, $fallbackCents, 'USD', 'fallback', $note);
            $rates[$jobType] = [
                'job_type' => $jobType,
                'unit_cost_cents' => $fallbackCents,
                'unit_cost_usd' => round($fallbackCents / 100, 4),
                'currency' => 'USD',
                'source' => 'fallback',
                'note' => $note,
            ];
            $anyFallback = true;
            $fallbackReasons[] = $note;
        }

        if ($anyFallback) {
            $fallbackReasons = array_values(array_unique(array_filter(array_map('strval', $fallbackReasons))));
            if (empty($fallbackReasons)) {
                $fallbackReasons[] = 'Fallback pricing in effect (one or more stored rates were missing or marked fallback).';
            }
            $fallbackNote = 'Fallback pricing in effect: ' . $fallbackReasons[0];
        }

        return [
            'week_start' => $weekStart,
            'provider' => $provider,
            'currency' => 'USD',
            'rates' => $rates,
            'is_fallback' => $anyFallback,
            'fallback_note' => $fallbackNote,
            'fallback_reasons' => $fallbackReasons,
        ];
    }

    public static function weekStartUtc(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->modify('monday this week')->format('Y-m-d');
    }

    private static function isFallbackSource(string $source): bool
    {
        $s = strtolower(trim($source));
        return $s === 'fallback' || $s === 'fallback_default' || $s === 'fallback_manual';
    }

    private static function upsertRate(
        string $weekStart,
        string $provider,
        string $jobType,
        int $unitCostCents,
        string $currency,
        string $source,
        string $note
    ): void {
        Database::execute(
            "INSERT INTO ai_job_pricing_rates (week_start, provider, job_type, unit_cost_cents, currency, source, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                unit_cost_cents = VALUES(unit_cost_cents),
                currency = VALUES(currency),
                source = VALUES(source),
                note = VALUES(note),
                updated_at = CURRENT_TIMESTAMP",
            [$weekStart, $provider, $jobType, $unitCostCents, $currency, $source, $note]
        );
    }
}
