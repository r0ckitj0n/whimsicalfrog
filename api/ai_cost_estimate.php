<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/ai/helpers/AIPricingStore.php';

AuthHelper::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    Response::error('Invalid JSON input.', null, 400);
}

$actionKey = trim((string) ($input['action_key'] ?? ''));
$actionLabel = trim((string) ($input['action_label'] ?? 'AI generation'));
$operationsInput = $input['operations'] ?? [];
$context = is_array($input['context'] ?? null) ? $input['context'] : [];

if ($actionKey === '') {
    Response::error('action_key is required.', null, 400);
}

function wf_job_counts_for_operation(string $opKey, int $imageCount, int $imageGenerations): array
{
    $opKey = strtolower(trim($opKey));
    $boundedImages = max(0, min(3, $imageCount)); // current endpoints typically cap to 3 images

    $jobs = [
        AIPricingStore::JOB_TEXT_GENERATION => 0,
        AIPricingStore::JOB_IMAGE_ANALYSIS => 0,
        AIPricingStore::JOB_IMAGE_CREATION => 0,
    ];

    // Operation keys reflect the actual backend orchestration paths:
    // - suggest_all.php (info): analyzes each image (one AI call per image) + generates dimensions (one text call).
    // - suggest_all.php (cost): one multimodal call when images are provided; otherwise one text call.
    // - suggest_all.php (price): one text call (orchestrator does not pass images here).
    // - suggest_marketing.php: analyzes each image (one AI call per image) + enhanced marketing (one text call).
    // - generate_room_image.php: prompt refinement is tracked separately as room_prompt_refinement.
    switch ($opKey) {
        case 'info_from_images':
            $jobs[AIPricingStore::JOB_IMAGE_ANALYSIS] = $boundedImages;
            $jobs[AIPricingStore::JOB_TEXT_GENERATION] = 1; // dimensions suggestion
            break;
        case 'cost_estimation':
            if ($boundedImages > 0) {
                $jobs[AIPricingStore::JOB_IMAGE_ANALYSIS] = 1; // single multimodal cost call
            } else {
                $jobs[AIPricingStore::JOB_TEXT_GENERATION] = 1;
            }
            break;
        case 'price_estimation':
            $jobs[AIPricingStore::JOB_TEXT_GENERATION] = 1;
            break;
        case 'marketing_generation':
            $jobs[AIPricingStore::JOB_IMAGE_ANALYSIS] = $boundedImages;
            $jobs[AIPricingStore::JOB_TEXT_GENERATION] = 1;
            break;
        case 'room_prompt_refinement':
            $jobs[AIPricingStore::JOB_TEXT_GENERATION] = 1;
            break;
        case 'room_image_generation':
            $jobs[AIPricingStore::JOB_IMAGE_CREATION] = max(1, $imageGenerations);
            break;
        case 'image_edit_generation':
            $jobs[AIPricingStore::JOB_IMAGE_CREATION] = max(1, $imageGenerations);
            break;
        default:
            // Safe default: assume one text generation call.
            $jobs[AIPricingStore::JOB_TEXT_GENERATION] = 1;
            break;
    }

    return $jobs;
}

function wf_default_operation_catalog(int $defaultImageCount): array
{
    return [
        'info_from_images' => [
            'label' => 'Image analysis + item info',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => $defaultImageCount,
            'image_generations' => 0
        ],
        'cost_estimation' => [
            'label' => 'Cost suggestion',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => $defaultImageCount,
            'image_generations' => 0
        ],
        'price_estimation' => [
            'label' => 'Price suggestion',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => max(1, $defaultImageCount),
            'image_generations' => 0
        ],
        'marketing_generation' => [
            'label' => 'Marketing generation',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => max(1, $defaultImageCount),
            'image_generations' => 0
        ],
        'room_prompt_refinement' => [
            'label' => 'Room prompt refinement',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => 0,
            'image_generations' => 0
        ],
        'room_image_generation' => [
            'label' => 'Room image generation',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => 0,
            'image_generations' => 1
        ],
        'image_edit_generation' => [
            'label' => 'Image edit generation',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'image_count' => 1,
            'image_generations' => 1
        ]
    ];
}

function wf_default_operations_for_action(string $actionKey): array
{
    $actionMap = [
        'inventory_generate_all' => ['info_from_images', 'cost_estimation', 'marketing_generation', 'price_estimation'],
        'inventory_generate_info_marketing' => ['info_from_images', 'marketing_generation'],
        'inventory_generate_cost' => ['info_from_images', 'cost_estimation'],
        'inventory_generate_price' => ['info_from_images', 'price_estimation'],
        'inventory_generate_marketing' => ['info_from_images', 'marketing_generation'],
        'room_generate_prompt' => ['room_prompt_refinement'],
        'room_generate_background' => ['room_prompt_refinement', 'room_image_generation'],
        'room_generate_background_only' => ['room_image_generation'],
        'create_room_generate_image' => ['room_image_generation'],
        'shortcut_generate_sign_image' => ['room_image_generation'],
        'item_image_submit_to_ai' => ['image_edit_generation'],
        'background_image_submit_to_ai' => ['image_edit_generation'],
        'shortcut_image_submit_to_ai' => ['image_edit_generation'],
        'cost_breakdown_generate_all' => ['info_from_images', 'cost_estimation'],
        'ai_suggestions_generate_all' => ['info_from_images', 'cost_estimation', 'marketing_generation', 'price_estimation'],
        'ai_suggestions_generate_info' => ['info_from_images', 'marketing_generation']
    ];
    return $actionMap[$actionKey] ?? ['info_from_images'];
}

function wf_coerce_operations(array $operationsInput, string $actionKey, int $defaultImageCount): array
{
    $catalog = wf_default_operation_catalog($defaultImageCount);
    $ops = [];

    if (!empty($operationsInput)) {
        foreach ($operationsInput as $raw) {
            if (!is_array($raw))
                continue;
            $key = trim((string) ($raw['key'] ?? ''));
            if ($key === '')
                continue;
            $base = $catalog[$key] ?? [
                'label' => ucwords(str_replace('_', ' ', $key)),
                'input_tokens' => 900,
                'output_tokens' => 400,
                'image_count' => 0,
                'image_generations' => 0
            ];
            $count = max(1, (int) ($raw['count'] ?? 1));
            $imageCount = max(0, (int) ($raw['image_count'] ?? $base['image_count']));
            $imageGenerations = max(0, (int) ($raw['image_generations'] ?? $base['image_generations']));
            $ops[] = [
                'key' => $key,
                'label' => trim((string) ($raw['label'] ?? $base['label'])),
                'count' => $count,
                'input_tokens' => (int) ($base['input_tokens'] * $count),
                'output_tokens' => (int) ($base['output_tokens'] * $count),
                'image_count' => $imageCount * $count,
                'image_generations' => $imageGenerations * $count
            ];
        }
    }

    if (!empty($ops)) {
        return $ops;
    }

    foreach (wf_default_operations_for_action($actionKey) as $key) {
        $base = $catalog[$key];
        $ops[] = [
            'key' => $key,
            'label' => $base['label'],
            'count' => 1,
            'input_tokens' => (int) $base['input_tokens'],
            'output_tokens' => (int) $base['output_tokens'],
            'image_count' => (int) $base['image_count'],
            'image_generations' => (int) $base['image_generations']
        ];
    }
    return $ops;
}

function wf_index_by_key(array $items): array
{
    $indexed = [];
    foreach ($items as $item) {
        if (!is_array($item))
            continue;
        $key = trim((string) ($item['key'] ?? ''));
        if ($key === '')
            continue;
        $indexed[$key] = $item;
    }
    return $indexed;
}

function wf_try_ai_estimate(array $ops, array $context): array
{
    // Intentionally disabled: we do not call external AI to estimate pricing.
    // Estimates are based on weekly-stored per-job pricing in MySQL.
    return [
        'success' => false,
        'line_items' => [],
        'assumptions' => []
    ];
}

function wf_get_provider_and_model_from_settings(): array
{
    // Keep this endpoint lightweight: avoid bootstrapping full AIProviders.
    // Only read the keys needed to label the estimate output.
    $defaults = [
        'ai_provider' => 'jons_ai',
        'openai_model' => 'gpt-4o',
        'anthropic_model' => 'claude-3-5-sonnet-20241022',
        'google_model' => 'gemini-1.5-pro',
        'meta_model' => 'meta-llama/llama-3.1-405b-instruct',
    ];

    try {
        $rows = Database::queryAll(
            "SELECT setting_key, setting_value
             FROM business_settings
             WHERE category = 'ai'
             AND setting_key IN ('ai_provider','openai_model','anthropic_model','google_model','meta_model')"
        );
        foreach ($rows as $row) {
            $k = (string) ($row['setting_key'] ?? '');
            if ($k === '' || !array_key_exists($k, $defaults)) continue;
            $defaults[$k] = trim((string) ($row['setting_value'] ?? ''));
        }
    } catch (Throwable $e) {
        // Non-fatal: fall back to defaults.
    }

    $provider = $defaults['ai_provider'] !== '' ? $defaults['ai_provider'] : 'jons_ai';
    $modelKey = $provider . '_model';
    $model = (string) ($defaults[$modelKey] ?? ($provider === 'jons_ai' ? 'jons-ai' : 'default-model'));

    return [
        'provider' => $provider,
        'model' => $model !== '' ? $model : 'default-model',
    ];
}

try {
    $pm = wf_get_provider_and_model_from_settings();
    $provider = (string) ($pm['provider'] ?? 'jons_ai');
    $model = (string) ($pm['model'] ?? ($provider === 'jons_ai' ? 'jons-ai' : 'default-model'));

    $defaultImageCount = max(0, (int) ($context['image_count'] ?? 1));
    $ops = wf_coerce_operations(is_array($operationsInput) ? $operationsInput : [], $actionKey, $defaultImageCount);

    // Read-only: estimates should not write fallback/copied rates.
    $pricing = AIPricingStore::getWeeklyRatesReadOnly($provider);
    $ratesByJob = (array) ($pricing['rates'] ?? []);
    $textRate = (int) (($ratesByJob[AIPricingStore::JOB_TEXT_GENERATION]['unit_cost_cents'] ?? 0));
    $analysisRate = (int) (($ratesByJob[AIPricingStore::JOB_IMAGE_ANALYSIS]['unit_cost_cents'] ?? 0));
    $creationRate = (int) (($ratesByJob[AIPricingStore::JOB_IMAGE_CREATION]['unit_cost_cents'] ?? 0));

    $lineItems = [];
    $expectedTotal = 0.0;
    $minTotal = 0.0;
    $maxTotal = 0.0;
    $totalJobCounts = [
        AIPricingStore::JOB_TEXT_GENERATION => 0,
        AIPricingStore::JOB_IMAGE_ANALYSIS => 0,
        AIPricingStore::JOB_IMAGE_CREATION => 0,
    ];

    foreach ($ops as $op) {
        $imageCount = max(0, (int) ($op['image_count'] ?? 0));
        $imageGenerations = max(0, (int) ($op['image_generations'] ?? 0));
        $jobs = wf_job_counts_for_operation((string) ($op['key'] ?? ''), $imageCount, $imageGenerations);

        $textJobs = (int) ($jobs[AIPricingStore::JOB_TEXT_GENERATION] ?? 0);
        $analysisJobs = (int) ($jobs[AIPricingStore::JOB_IMAGE_ANALYSIS] ?? 0);
        $creationJobs = (int) ($jobs[AIPricingStore::JOB_IMAGE_CREATION] ?? 0);

        $totalJobCounts[AIPricingStore::JOB_TEXT_GENERATION] += $textJobs;
        $totalJobCounts[AIPricingStore::JOB_IMAGE_ANALYSIS] += $analysisJobs;
        $totalJobCounts[AIPricingStore::JOB_IMAGE_CREATION] += $creationJobs;

        $expectedCents = ($textJobs * $textRate) + ($analysisJobs * $analysisRate) + ($creationJobs * $creationRate);
        $expected = round($expectedCents / 100, 6);
        $min = $expected;
        $max = $expected;

        $expectedTotal += $expected;
        $minTotal += $min;
        $maxTotal += $max;

        $lineItems[] = [
            'key' => $op['key'],
            'label' => $op['label'],
            'estimated_input_tokens' => 0,
            'estimated_output_tokens' => 0,
            'image_count' => $imageCount,
            'image_generations' => $imageGenerations,
            'job_counts' => [
                'text_generation' => $textJobs,
                'image_analysis' => $analysisJobs,
                'image_creation' => $creationJobs
            ],
            'expected_cost' => round($expected, 6),
            'min_cost' => round($min, 6),
            'max_cost' => round($max, 6),
            'reasoning' => ''
        ];
    }

    // Only consider fallback pricing "in effect" if a job type with fallback source
    // is actually used by this action (job count > 0). This prevents confusing warnings
    // like "fallback image_analysis" when the estimate uses a0.
    $fallbackSources = ['fallback', 'fallback_default', 'fallback_manual'];
    $usedFallbackReasons = [];
    foreach ($totalJobCounts as $jt => $count) {
        if ($count <= 0) continue;
        $rate = $ratesByJob[$jt] ?? null;
        $src = is_array($rate) ? strtolower(trim((string) ($rate['source'] ?? ''))) : '';
        if (in_array($src, $fallbackSources, true)) {
            $note = is_array($rate) ? trim((string) ($rate['note'] ?? '')) : '';
            $usedFallbackReasons[] = $note !== '' ? $note : "Fallback pricing: stored rate marked as fallback for {$provider}/{$jt}.";
        }
    }
    $usedFallbackReasons = array_values(array_unique(array_filter(array_map('strval', $usedFallbackReasons))));
    $isFallbackForThisEstimate = !empty($usedFallbackReasons);
    $fallbackNoteForThisEstimate = $isFallbackForThisEstimate ? ('Fallback pricing in effect: ' . $usedFallbackReasons[0]) : '';

    $assumptions = [];
    $assumptions[] = 'Estimate uses weekly stored per-job pricing (text generation / image analysis / image creation).';
    $assumptions[] = 'Includes the operations requested by this action and sums costs across all jobs.';
    $assumptions[] = 'Does not include extra cost from retries, provider-side token accounting, or model-specific pricing differences.';
    if ($isFallbackForThisEstimate) {
        $assumptions[] = $fallbackNoteForThisEstimate;
        $assumptions[] = 'Fallback reason(s): ' . implode(' | ', array_slice($usedFallbackReasons, 0, 4));
    }

    Response::json([
        'success' => true,
        'estimate' => [
            'provider' => $provider,
            'model' => $model,
            'currency' => 'USD',
            'source' => 'stored',
            'pricing' => [
                'week_start' => (string) ($pricing['week_start'] ?? ''),
                'provider' => (string) ($pricing['provider'] ?? $provider),
                'rates' => array_values((array) ($pricing['rates'] ?? [])),
                'is_fallback_pricing' => $isFallbackForThisEstimate,
                'fallback_note' => $fallbackNoteForThisEstimate,
                'fallback_reasons' => $usedFallbackReasons
            ],
            'expected_cost' => round($expectedTotal, 6),
            'min_cost' => round($minTotal, 6),
            'max_cost' => round($maxTotal, 6),
            'operation_count' => count($ops),
            'line_items' => $lineItems,
            'assumptions' => $assumptions
        ]
    ]);
} catch (Throwable $e) {
    error_log('ai_cost_estimate failed: ' . $e->getMessage());
    Response::serverError('Failed to estimate AI generation cost: ' . $e->getMessage());
}
