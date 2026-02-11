<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/ai_providers.php';

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

/**
 * Pricing rates are rough, conservative estimates used for preflight confirmation.
 * Values are USD per 1M tokens unless stated otherwise.
 */
function wf_estimate_rates(string $provider, string $model): array
{
    $provider = strtolower($provider);
    $modelLower = strtolower($model);

    $rates = [
        'input_per_million' => 5.0,
        'output_per_million' => 15.0,
        'image_generation_per_image' => 0.04
    ];

    if ($provider === 'jons_ai') {
        return [
            'input_per_million' => 0.0,
            'output_per_million' => 0.0,
            'image_generation_per_image' => 0.0
        ];
    }

    if ($provider === 'openai') {
        if (strpos($modelLower, 'mini') !== false) {
            $rates['input_per_million'] = 0.6;
            $rates['output_per_million'] = 2.4;
        } elseif (strpos($modelLower, 'gpt-5') !== false) {
            $rates['input_per_million'] = 1.25;
            $rates['output_per_million'] = 10.0;
        } elseif (strpos($modelLower, '4o') !== false) {
            $rates['input_per_million'] = 2.5;
            $rates['output_per_million'] = 10.0;
        }

        if (strpos($modelLower, 'gpt-image') !== false) {
            $rates['image_generation_per_image'] = 0.04;
        }
        return $rates;
    }

    if ($provider === 'anthropic') {
        return [
            'input_per_million' => 3.0,
            'output_per_million' => 15.0,
            'image_generation_per_image' => 0.03
        ];
    }

    if ($provider === 'google') {
        return [
            'input_per_million' => 1.0,
            'output_per_million' => 4.0,
            'image_generation_per_image' => 0.03
        ];
    }

    if ($provider === 'meta') {
        return [
            'input_per_million' => 0.6,
            'output_per_million' => 0.8,
            'image_generation_per_image' => 0.03
        ];
    }

    return $rates;
}

function wf_default_operation_catalog(int $defaultImageCount): array
{
    return [
        'info_from_images' => [
            'label' => 'Image analysis + item info',
            'input_tokens' => 2200 + (900 * $defaultImageCount),
            'output_tokens' => 700,
            'image_count' => $defaultImageCount,
            'image_generations' => 0
        ],
        'cost_estimation' => [
            'label' => 'Cost suggestion',
            'input_tokens' => 900 + (300 * $defaultImageCount),
            'output_tokens' => 450,
            'image_count' => $defaultImageCount,
            'image_generations' => 0
        ],
        'price_estimation' => [
            'label' => 'Price suggestion',
            'input_tokens' => 1000 + (150 * $defaultImageCount),
            'output_tokens' => 500,
            'image_count' => max(1, $defaultImageCount),
            'image_generations' => 0
        ],
        'marketing_generation' => [
            'label' => 'Marketing generation',
            'input_tokens' => 1400 + (350 * $defaultImageCount),
            'output_tokens' => 1200,
            'image_count' => max(1, $defaultImageCount),
            'image_generations' => 0
        ],
        'room_prompt_refinement' => [
            'label' => 'Room prompt refinement',
            'input_tokens' => 1800,
            'output_tokens' => 900,
            'image_count' => 0,
            'image_generations' => 0
        ],
        'room_image_generation' => [
            'label' => 'Room image generation',
            'input_tokens' => 350,
            'output_tokens' => 0,
            'image_count' => 0,
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

function wf_try_ai_estimate(array $ops, array $context, AIProviders $aiProviders): array
{
    $payloadSummary = array_map(static function (array $op): array {
        return [
            'key' => $op['key'],
            'label' => $op['label'],
            'count' => $op['count'],
            'baseline_input_tokens' => $op['input_tokens'],
            'baseline_output_tokens' => $op['output_tokens'],
            'image_count' => $op['image_count'],
            'image_generations' => $op['image_generations']
        ];
    }, $ops);

    $prompt = "Estimate token and image usage for these AI operations.\n";
    $prompt .= "Return ONLY valid JSON with keys: line_items, assumptions.\n";
    $prompt .= "line_items must be an array of objects with keys: key, estimated_input_tokens, estimated_output_tokens, image_count, image_generations, reasoning.\n";
    $prompt .= "If uncertain, stay conservative but realistic.\n";
    $prompt .= "Operations JSON: " . json_encode($payloadSummary, JSON_UNESCAPED_SLASHES);
    $prompt .= "\nContext JSON: " . json_encode($context, JSON_UNESCAPED_SLASHES);

    $result = $aiProviders->generateReceiptMessage($prompt);
    if (!is_array($result) || !isset($result['line_items']) || !is_array($result['line_items'])) {
        return [
            'success' => false,
            'line_items' => [],
            'assumptions' => []
        ];
    }

    return [
        'success' => true,
        'line_items' => $result['line_items'],
        'assumptions' => isset($result['assumptions']) && is_array($result['assumptions']) ? $result['assumptions'] : []
    ];
}

try {
    $aiProviders = getAIProviders();
    $settings = $aiProviders->getSettings();
    $provider = (string) ($settings['ai_provider'] ?? 'jons_ai');
    $modelKey = $provider . '_model';
    $model = (string) ($settings[$modelKey] ?? ($provider === 'jons_ai' ? 'jons-ai' : 'default-model'));

    $defaultImageCount = max(0, (int) ($context['image_count'] ?? 1));
    $ops = wf_coerce_operations(is_array($operationsInput) ? $operationsInput : [], $actionKey, $defaultImageCount);

    $aiEstimate = ['success' => false, 'line_items' => [], 'assumptions' => []];
    if ($provider !== 'jons_ai') {
        try {
            $aiEstimate = wf_try_ai_estimate($ops, $context, $aiProviders);
        } catch (Throwable $estimateErr) {
            error_log('ai_cost_estimate AI estimate failed: ' . $estimateErr->getMessage());
        }
    }

    $lineOverrides = wf_index_by_key($aiEstimate['line_items']);
    $rates = wf_estimate_rates($provider, $model);

    $lineItems = [];
    $expectedTotal = 0.0;
    $minTotal = 0.0;
    $maxTotal = 0.0;

    foreach ($ops as $op) {
        $override = $lineOverrides[$op['key']] ?? null;
        $inputTokens = max(0, (int) ($override['estimated_input_tokens'] ?? $op['input_tokens']));
        $outputTokens = max(0, (int) ($override['estimated_output_tokens'] ?? $op['output_tokens']));
        $imageCount = max(0, (int) ($override['image_count'] ?? $op['image_count']));
        $imageGenerations = max(0, (int) ($override['image_generations'] ?? $op['image_generations']));

        $tokenCost = (($inputTokens / 1000000) * $rates['input_per_million']) + (($outputTokens / 1000000) * $rates['output_per_million']);
        // Approximate extra image-analysis token load.
        $imageAnalysisTokens = $imageCount * 1200;
        $imageAnalysisCost = ($imageAnalysisTokens / 1000000) * $rates['input_per_million'];
        $imageGenCost = $imageGenerations * $rates['image_generation_per_image'];
        $expected = $tokenCost + $imageAnalysisCost + $imageGenCost;
        $min = $expected * 0.7;
        $max = $expected * 1.3;

        $expectedTotal += $expected;
        $minTotal += $min;
        $maxTotal += $max;

        $lineItems[] = [
            'key' => $op['key'],
            'label' => $op['label'],
            'estimated_input_tokens' => $inputTokens,
            'estimated_output_tokens' => $outputTokens,
            'image_count' => $imageCount,
            'image_generations' => $imageGenerations,
            'expected_cost' => round($expected, 6),
            'min_cost' => round($min, 6),
            'max_cost' => round($max, 6),
            'reasoning' => isset($override['reasoning']) ? (string) $override['reasoning'] : ''
        ];
    }

    $assumptions = [];
    $assumptions[] = 'Preflight estimates are approximate and can vary with prompt length, retries, and provider-side billing changes.';
    $assumptions[] = 'Includes the operations requested by this action and combines them into one total.';
    if (!$aiEstimate['success']) {
        $assumptions[] = 'Used heuristic token estimates because live AI estimation was unavailable.';
    } else {
        foreach ($aiEstimate['assumptions'] as $assumption) {
            $text = trim((string) $assumption);
            if ($text !== '') {
                $assumptions[] = $text;
            }
        }
    }

    Response::json([
        'success' => true,
        'estimate' => [
            'provider' => $provider,
            'model' => $model,
            'currency' => 'USD',
            'source' => $aiEstimate['success'] ? 'ai' : 'heuristic',
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
