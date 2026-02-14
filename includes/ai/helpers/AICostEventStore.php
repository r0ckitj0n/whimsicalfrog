<?php
// includes/ai/helpers/AICostEventStore.php

require_once __DIR__ . '/AIPricingStore.php';
require_once __DIR__ . '/AICostEventSchemaHelper.php';

/**
 * Records actual AI costs for completed runs using the same job-based model as estimates.
 */
class AICostEventStore
{
    /**
     * @param array{
     *   endpoint:string,
     *   provider:string,
     *   model?:string|null,
     *   week_start?:string|null,
     *   currency?:string|null,
     *   sku?:string|null,
     *   step?:string|null,
     *   text_jobs?:int,
     *   image_analysis_jobs?:int,
     *   image_creation_jobs?:int,
     *   request_meta?:array|null
     * } $event
     */
    public static function logEvent(array $event): void
    {
        Database::getInstance();
        AICostEventSchemaHelper::ensureSchema();

        $endpoint = trim((string) ($event['endpoint'] ?? ''));
        if ($endpoint === '') {
            throw new InvalidArgumentException('AICostEventStore::logEvent requires endpoint.');
        }

        $provider = trim(strtolower((string) ($event['provider'] ?? 'default')));
        $model = isset($event['model']) ? trim((string) $event['model']) : null;
        $sku = isset($event['sku']) ? trim((string) $event['sku']) : null;
        $step = isset($event['step']) ? trim((string) $event['step']) : null;

        $textJobs = max(0, (int) ($event['text_jobs'] ?? 0));
        $analysisJobs = max(0, (int) ($event['image_analysis_jobs'] ?? 0));
        $creationJobs = max(0, (int) ($event['image_creation_jobs'] ?? 0));

        $weekStart = isset($event['week_start']) && trim((string) $event['week_start']) !== ''
            ? (string) $event['week_start']
            : AIPricingStore::weekStartUtc();

        $pricing = AIPricingStore::getWeeklyRates($provider, $weekStart);
        $ratesByJob = (array) ($pricing['rates'] ?? []);

        $textRate = (int) (($ratesByJob[AIPricingStore::JOB_TEXT_GENERATION]['unit_cost_cents'] ?? 0));
        $analysisRate = (int) (($ratesByJob[AIPricingStore::JOB_IMAGE_ANALYSIS]['unit_cost_cents'] ?? 0));
        $creationRate = (int) (($ratesByJob[AIPricingStore::JOB_IMAGE_CREATION]['unit_cost_cents'] ?? 0));

        $totalCents = ($textJobs * $textRate) + ($analysisJobs * $analysisRate) + ($creationJobs * $creationRate);

        $currency = isset($event['currency']) && trim((string) $event['currency']) !== ''
            ? strtoupper(trim((string) $event['currency']))
            : 'USD';

        $ratesJson = [
            AIPricingStore::JOB_TEXT_GENERATION => $ratesByJob[AIPricingStore::JOB_TEXT_GENERATION] ?? null,
            AIPricingStore::JOB_IMAGE_ANALYSIS => $ratesByJob[AIPricingStore::JOB_IMAGE_ANALYSIS] ?? null,
            AIPricingStore::JOB_IMAGE_CREATION => $ratesByJob[AIPricingStore::JOB_IMAGE_CREATION] ?? null,
        ];

        $requestMeta = $event['request_meta'] ?? null;
        if ($requestMeta !== null && !is_array($requestMeta)) {
            $requestMeta = ['note' => (string) $requestMeta];
        }

        Database::execute(
            "INSERT INTO ai_generation_cost_events (
                sku, endpoint, step, provider, model,
                week_start, currency,
                text_jobs, image_analysis_jobs, image_creation_jobs,
                total_cost_cents,
                is_fallback_pricing, fallback_note, fallback_reasons,
                pricing_rates, request_meta
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $sku !== '' ? $sku : null,
                $endpoint,
                $step !== '' ? $step : null,
                $provider !== '' ? $provider : 'default',
                $model !== '' ? $model : null,
                (string) ($pricing['week_start'] ?? $weekStart),
                $currency,
                $textJobs,
                $analysisJobs,
                $creationJobs,
                (int) $totalCents,
                !empty($pricing['is_fallback']) ? 1 : 0,
                (string) ($pricing['fallback_note'] ?? ''),
                json_encode(array_values((array) ($pricing['fallback_reasons'] ?? []))),
                json_encode($ratesJson),
                $requestMeta !== null ? json_encode($requestMeta) : null
            ]
        );
    }

    /**
     * Helper for consistency with ai_cost_estimate.php provider/model selection.
     * @param array<string, mixed> $settings
     * @return array{provider:string, model:string}
     */
    public static function resolveProviderAndModelFromSettings(array $settings): array
    {
        $provider = trim((string) ($settings['ai_provider'] ?? 'jons_ai'));
        $provider = $provider !== '' ? $provider : 'jons_ai';
        $modelKey = $provider . '_model';
        $model = trim((string) ($settings[$modelKey] ?? ($provider === 'jons_ai' ? 'jons-ai' : 'default-model')));
        return [
            'provider' => $provider,
            'model' => $model !== '' ? $model : 'default-model'
        ];
    }
}

