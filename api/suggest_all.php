<?php
// api/suggest_all.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/ai_manager.php';
require_once __DIR__ . '/../includes/ai/helpers/CostHeuristics.php';
require_once __DIR__ . '/../includes/ai/helpers/PricingHeuristics.php';
require_once __DIR__ . '/../includes/ai/helpers/TierScalingHelper.php';
require_once __DIR__ . '/../includes/ai/helpers/MarketingHeuristics.php';

AuthHelper::requireAdmin();

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    Response::error('Invalid JSON input.', 400);
}

$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$category = trim($input['category'] ?? '');
$sku = trim($input['sku'] ?? '');
$cost_price = floatval($input['cost_price'] ?? 0);
$qualityTier = trim($input['quality_tier'] ?? 'standard');
$useImages = $input['useImages'] ?? false;
$imageData = $input['imageData'] ?? null;
$brandVoice = trim($input['brandVoice'] ?? '');
$contentTone = trim($input['contentTone'] ?? '');
$step = trim($input['step'] ?? ''); // Step-by-step mode: 'info', 'cost', 'price', 'marketing', or empty for full
$lockedWords = is_array($input['locked_words'] ?? null) ? $input['locked_words'] : [];
$imageFirstPriority = array_key_exists('image_first_priority', $input)
    ? filter_var($input['image_first_priority'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
    : true;
if ($imageFirstPriority === null) {
    $imageFirstPriority = true;
}

// Validate step parameter if provided
$validSteps = ['info', 'cost', 'price', 'marketing', ''];
if (!in_array($step, $validSteps)) {
    Response::error('Invalid step parameter. Valid values: info, cost, price, marketing', 400);
}

if (empty($name) && empty($category) && (!$useImages || (empty($sku) && empty($imageData)))) {
    Response::error('At least Name, Category, or Image analysis is required for AI generation.', 400);
}

if ($imageFirstPriority && ($step === '' || $step === 'info')) {
    $name = '';
    $description = '';
    $category = '';
}

function wf_parse_locked_tokens($value)
{
    if (!is_string($value) || trim($value) === '') {
        return [[], []];
    }
    preg_match_all('/"([^"]+)"/', $value, $phraseMatches);
    $phrases = array_values(array_filter(array_map('trim', $phraseMatches[1] ?? [])));
    $remaining = trim(preg_replace('/"([^"]+)"/', ' ', $value) ?? '');
    $words = array_values(array_filter(preg_split('/\s+/', $remaining) ?: []));
    return [$phrases, $words];
}

function wf_apply_locked_words($candidate, $constraint)
{
    $candidate = trim((string) $candidate);
    if (!is_string($constraint) || trim($constraint) === '') {
        return $candidate;
    }
    [$phrases, $words] = wf_parse_locked_tokens($constraint);
    $result = $candidate;
    foreach ($phrases as $phrase) {
        if ($phrase !== '' && stripos($result, $phrase) === false) {
            $result = trim($result . ' ' . $phrase);
        }
    }
    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }
        $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
        if (!preg_match($pattern, $result)) {
            $result = trim($result . ' ' . $word);
        }
    }
    return $result;
}

function wf_resolve_category_from_analysis($analysisCategory, $title, $description, $existingCategories)
{
    $analysisCategory = trim((string) $analysisCategory);
    $title = strtolower(trim((string) $title));
    $description = strtolower(trim((string) $description));
    $analysisLower = strtolower($analysisCategory);
    $text = trim($title . ' ' . $description . ' ' . $analysisLower);

    if (!is_array($existingCategories) || count($existingCategories) === 0) {
        return $analysisCategory;
    }

    $bestCategory = $analysisCategory;
    $bestScore = -INF;

    foreach ($existingCategories as $candidateRaw) {
        $candidate = trim((string) $candidateRaw);
        if ($candidate === '') {
            continue;
        }
        $candidateLower = strtolower($candidate);
        $score = 0.0;

        if ($analysisLower !== '' && $candidateLower === $analysisLower) {
            $score += 60.0;
        }

        if ($candidateLower !== '' && preg_match('/\b' . preg_quote($candidateLower, '/') . '\b/i', $text)) {
            $score += 30.0;
        }

        $tokens = preg_split('/[^a-z0-9]+/i', $candidateLower) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || strlen($token) < 3) {
                continue;
            }
            if (preg_match('/\b' . preg_quote($token, '/') . '\b/i', $text)) {
                $score += 10.0;
            }
        }

        $isHatCategory = preg_match('/\b(hat|hats|cap|caps|beanie|headwear)\b/i', $candidateLower) === 1;
        $mentionsHatInImageText = preg_match('/\b(hat|hats|cap|caps|beanie|headwear)\b/i', $text) === 1;
        if ($isHatCategory && !$mentionsHatInImageText) {
            $score -= 40.0;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCategory = $candidate;
        }
    }

    if (is_infinite($bestScore) || $bestScore <= 0) {
        return $analysisCategory;
    }
    return $bestCategory;
}

try {
    $aiProviders = getAIProviders();
    $images = [];

    // Prioritize passed imageData
    if ($imageData) {
        // If imageData is a relative web path (starts with /), convert to absolute filesystem path
        if (is_string($imageData) && strpos($imageData, '/') === 0 && strpos($imageData, 'data:') !== 0) {
            // Convert web path to absolute filesystem path
            $basePath = dirname(__DIR__); // One level up from /api to get project root
            $absolutePath = $basePath . $imageData;
            if (file_exists($absolutePath)) {
                $images = [$absolutePath];
                error_log("suggest_all.php: Converted web path to filesystem path: $absolutePath");
            } else {
                error_log("suggest_all.php: Image not found at converted path: $absolutePath");
            }
        } else {
            // It's either a data URI or some other format - use as-is
            $images = [$imageData];
        }
    } else if ($useImages && !empty($sku)) {
        $images = AIProviders::getItemImages($sku, 3);
    }

    $results = [
        'success' => true,
        'info_suggestion' => null,
        'cost_suggestion' => null,
        'price_suggestion' => null,
        'marketing_suggestion' => null
    ];

    $normalizeDimensionValue = function ($value): ?float {
        if (!is_numeric($value)) {
            return null;
        }
        $num = round((float) $value, 2);
        if (!is_finite($num) || $num <= 0) {
            return null;
        }
        return $num;
    };

    $normalizeDimensionsSuggestion = function ($raw) use ($normalizeDimensionValue): ?array {
        if (!is_array($raw)) {
            return null;
        }

        $weight = $normalizeDimensionValue($raw['weight_oz'] ?? null);
        $dimensions = is_array($raw['dimensions_in'] ?? null) ? $raw['dimensions_in'] : [];
        $length = $normalizeDimensionValue($dimensions['length'] ?? null);
        $width = $normalizeDimensionValue($dimensions['width'] ?? null);
        $height = $normalizeDimensionValue($dimensions['height'] ?? null);

        if ($weight === null || $length === null || $width === null || $height === null) {
            return null;
        }

        return [
            'weight_oz' => $weight,
            'package_length_in' => $length,
            'package_width_in' => $width,
            'package_height_in' => $height
        ];
    };

    // 0. Generate Info Suggestion if name is missing or if explicitly requested (implied by combined call)
    // Step mode: info - only run this step if step === 'info' or step === '' (full)
    if ($step === '' || $step === 'info') {
        if (!$useImages) {
            Response::error('Image analysis is required for Generate. Enable image-based generation and try again.', null, 400);
        }
        if (!$aiProviders->currentModelSupportsImages()) {
            Response::error('Selected AI model does not support image analysis. Switch to a vision-capable model in AI Settings and re-test the provider.', null, 400);
        }
        if (empty($images)) {
            Response::error('Image analysis is required, but no usable image was found for this item. Add a primary image and try again.', null, 400);
        }

        // Debug logging for info step
        error_log("suggest_all.php [info step]: images count=" . count($images) . ", useImages=" . ($useImages ? 'true' : 'false') . ", supportsImages=" . ($aiProviders->currentModelSupportsImages() ? 'true' : 'false'));

        if (!empty($images) && $useImages && $aiProviders->currentModelSupportsImages()) {
            try {
                $existingCategories = [];
                $rows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
                if ($rows) {
                    $existingCategories = array_map(function ($r) {
                        return array_values($r)[0];
                    }, $rows);
                }

                error_log("suggest_all.php [info step]: Calling analyzeItemImage with image data length=" . strlen($images[0]));
                $analysis = $aiProviders->analyzeItemImage($images[0], $existingCategories);
                error_log("suggest_all.php [info step]: analyzeItemImage returned: " . json_encode($analysis));

                if ($analysis) {
                    $resolvedCategory = wf_resolve_category_from_analysis(
                        $analysis['category'] ?? '',
                        $analysis['title'] ?? '',
                        $analysis['description'] ?? '',
                        $existingCategories
                    );

                    $results['info_suggestion'] = [
                        'success' => true,
                        'name' => wf_apply_locked_words($analysis['title'] ?? '', $lockedWords['name'] ?? ''),
                        'description' => wf_apply_locked_words($analysis['description'] ?? '', $lockedWords['description'] ?? ''),
                        'category' => wf_apply_locked_words($resolvedCategory, $lockedWords['category'] ?? ''),
                        'confidence' => $analysis['confidence'] ?? 'medium',
                        'reasoning' => ($analysis['reasoning'] ?? '') . " (Image-first analysis applied.)"
                    ];
                    // Update local variables for subsequent suggestions (image-first chain).
                    $name = $results['info_suggestion']['name'];
                    $description = $results['info_suggestion']['description'];
                    $category = $results['info_suggestion']['category'];
                } else {
                    error_log("suggest_all.php [info step]: analyzeItemImage returned null/empty");
                    Response::error('Image analysis failed for the current model. Switch to a vision-capable model in AI Settings and run Test Provider before generating.', null, 400);
                }
            } catch (Exception $e) {
                error_log("Info analysis failed in suggest_all: " . $e->getMessage());
                Response::error('Image analysis failed: ' . $e->getMessage() . '. Switch to a vision-capable model in AI Settings and run Test Provider.', null, 400);
            }
        } else if (!$imageFirstPriority && empty($name) && !empty($category)) {
            // Fallback: Generate info from category if name/images are missing
            try {
                $analysis = $aiProviders->generateMarketingContent('', '', $category);
                if ($analysis && !empty($analysis['title'])) {
                    $results['info_suggestion'] = [
                        'success' => true,
                        'name' => $analysis['title'],
                        'description' => $analysis['description'] ?? '',
                        'category' => $category,
                        'confidence' => 'medium',
                        'reasoning' => "Generated based on category: {$category}"
                    ];
                    $name = $analysis['title'];
                    $description = $results['info_suggestion']['description'];
                }
            } catch (Exception $e) {
                error_log("Info generation from category failed in suggest_all: " . $e->getMessage());
                if ($step === 'info') {
                    $results['info_suggestion'] = ['success' => false, 'error' => $e->getMessage()];
                }
            }
        }

        $dimensionsContextName = trim((string) (($results['info_suggestion']['name'] ?? '') ?: $name));
        $dimensionsContextDescription = trim((string) (($results['info_suggestion']['description'] ?? '') ?: $description));
        $dimensionsContextCategory = trim((string) (($results['info_suggestion']['category'] ?? '') ?: $category));

        if (!empty($dimensionsContextName) || !empty($dimensionsContextDescription) || !empty($dimensionsContextCategory)) {
            try {
                $dimensionsRaw = $aiProviders->generateDimensionsSuggestion(
                    $dimensionsContextName,
                    $dimensionsContextDescription,
                    $dimensionsContextCategory
                );
                $normalizedDimensions = $normalizeDimensionsSuggestion($dimensionsRaw);
                if ($normalizedDimensions) {
                    if (!$results['info_suggestion']) {
                        $results['info_suggestion'] = [
                            'success' => true,
                            'name' => $dimensionsContextName,
                            'description' => $dimensionsContextDescription,
                            'category' => $dimensionsContextCategory,
                            'confidence' => 'medium',
                            'reasoning' => 'Generated from existing item details.'
                        ];
                    }
                    $results['info_suggestion'] = array_merge($results['info_suggestion'], $normalizedDimensions);
                }
            } catch (Exception $e) {
                error_log("Dimensions generation failed in suggest_all: " . $e->getMessage());
            }
        }

        // If this is an info-only step, return now
        if ($step === 'info') {
            Response::json($results);
        }
    }

    if (empty($name)) {
        Response::error('Failed to generate item info from images. Please provide a name.', 400);
    }

    // 1. Generate Cost Suggestion
    // Step mode: cost - only run this step if step === 'cost' or step === '' (full)
    if ($step === '' || $step === 'cost') {
        try {
            if (!empty($images) && $useImages) {
                $costData = $aiProviders->generateCostSuggestionWithImages($name, $description, $category, $images);
            } else {
                $costData = $aiProviders->generateCostSuggestion($name, $description, $category);
            }

            // Default to market-average heuristic for cost suggestions
            $heuristic = CostHeuristics::analyze($name, $description, $category, Database::getInstance(), $qualityTier);
            if (!empty($heuristic['cost'])) {
                $costData = $heuristic;
                $costData['reasoning'] = ($costData['reasoning'] ?? '') . ' • Market-average default applied.';
            }

            // Normalize confidence
            $confidenceLabel = $costData['confidence'] ?? 'medium';
            $confidenceValue = is_numeric($confidenceLabel)
                ? floatval($confidenceLabel)
                : (strtolower($confidenceLabel) === 'high' ? 0.9
                    : (strtolower($confidenceLabel) === 'low' ? 0.2 : 0.5));

            // Apply tier multiplier
            $multiplier = TierScalingHelper::getMultiplier($qualityTier);
            if ($multiplier !== 1.0) {
                $original_cost = (float) $costData['cost'];
                $adjustedCost = $original_cost * $multiplier;
                $costData['cost'] = $adjustedCost;

                if (!empty($costData['breakdown']) && is_array($costData['breakdown'])) {
                    TierScalingHelper::scaleBreakdown($costData['breakdown'], $multiplier);
                }
                $costData['reasoning'] .= ' • Tier multiplier applied to breakdown.';
            }

            $results['cost_suggestion'] = [
                'success' => true,
                'suggested_cost' => $costData['cost'],
                'reasoning' => $costData['reasoning'],
                'confidence' => $confidenceValue,
                'breakdown' => $costData['breakdown'] ?? [],
                'analysis' => $costData['analysis'] ?? [],
                'created_at' => date('c')
            ];

            // Save cost suggestion if SKU provided
            if (!empty($sku)) {
                Database::execute("
                    INSERT INTO cost_suggestions (
                        sku, suggested_cost, reasoning, confidence, breakdown, created_at
                    ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE 
                    suggested_cost = VALUES(suggested_cost),
                    reasoning = VALUES(reasoning),
                    confidence = VALUES(confidence),
                    breakdown = VALUES(breakdown),
                    created_at = CURRENT_TIMESTAMP
                ", [
                    $sku,
                    $costData['cost'],
                    $costData['reasoning'],
                    $confidenceValue,
                    json_encode($costData['breakdown'] ?? [])
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to generate cost in suggest_all: " . $e->getMessage());
            $results['cost_suggestion'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // If this is a cost-only step, return now
        if ($step === 'cost') {
            Response::json($results);
        }
    }

    // 2. Generate Price Suggestion
    // Step mode: price - only run this step if step === 'price' or step === '' (full)
    if ($step === '' || $step === 'price') {
        try {
            // Use either provided cost_price or the newly suggested one
            $active_cost = $cost_price > 0 ? $cost_price : ($results['cost_suggestion']['suggested_cost'] ?? 0);

            if (!empty($images) && $useImages) {
                $pricingData = $aiProviders->generatePricingSuggestionWithImages($name, $description, $category, $active_cost, $images);
            } else {
                $pricingData = $aiProviders->generatePricingSuggestion($name, $description, $category, $active_cost);
            }

            // Default to market-average heuristic unless another strategy leads by >20 confidence points
            $heuristic = PricingHeuristics::analyze($name, $description, $category, $active_cost, Database::getInstance());
            if (!empty($heuristic['price'])) {
                $pricingData = $heuristic;
                $pricingData['reasoning'] = ($pricingData['reasoning'] ?? '') . ' • Market-average default applied.';
            }

            // Apply tier multiplier
            $multiplier = TierScalingHelper::getMultiplier($qualityTier);

            if ($multiplier !== 1.0) {
                $original_price = (float) $pricingData['price'];
                $adjustedPrice = $original_price * $multiplier;
                if ($active_cost > 0) {
                    $adjustedPrice = max($active_cost * 1.5, min($active_cost * 8.0, $adjustedPrice));
                }
                $adjustedPrice = max(1.0, $adjustedPrice);

                $effectiveMult = $original_price > 0 ? ($adjustedPrice / $original_price) : $multiplier;
                $pricingData['price'] = $adjustedPrice;

                if (!empty($pricingData['components']) && is_array($pricingData['components'])) {
                    TierScalingHelper::scalePricingComponents($pricingData['components'], $effectiveMult);
                }
                $pricingData['reasoning'] .= ' • Tier adjustment distributed to components.';
            }

            $results['price_suggestion'] = [
                'success' => true,
                'suggested_price' => $pricingData['price'],
                'reasoning' => $pricingData['reasoning'],
                'confidence' => $pricingData['confidence'],
                'factors' => $pricingData['factors'] ?? [],
                'components' => $pricingData['components'] ?? [],
                'analysis' => $pricingData['analysis'] ?? []
            ];

            // Save price suggestion if SKU provided
            if (!empty($sku)) {
                Database::execute("
                    INSERT INTO price_suggestions (
                        sku, suggested_price, reasoning, confidence, factors, components, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE 
                    suggested_price = VALUES(suggested_price),
                    reasoning = VALUES(reasoning),
                    confidence = VALUES(confidence),
                    factors = VALUES(factors),
                    components = VALUES(components),
                    created_at = CURRENT_TIMESTAMP
                ", [
                    $sku,
                    $pricingData['price'],
                    $pricingData['reasoning'],
                    $pricingData['confidence'],
                    json_encode($pricingData['factors'] ?? []),
                    json_encode($pricingData['components'] ?? [])
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to generate price in suggest_all: " . $e->getMessage());
            $results['price_suggestion'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // If this is a price-only step, return now
        if ($step === 'price') {
            Response::json($results);
        }
    }

    // 3. Generate Marketing Suggestion
    // Step mode: marketing - only run this step if step === 'marketing' or step === '' (full)
    if ($step === '' || $step === 'marketing') {
        try {
            $imageInsights = '';
            if (!empty($images) && $useImages) {
                $imageInsights = $aiProviders->extractMarketingInsightsFromImages($images, $name, $category);
            }

            $existingMarketingData = null;
            if (!empty($sku)) {
                try {
                    $existingMarketingData = Database::queryOne(
                        "SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1",
                        [$sku]
                    );
                } catch (Exception $e) {
                    error_log("suggest_all.php: Failed to load existing marketing data: " . $e->getMessage());
                }
            }

            $marketingData = $aiProviders->generateEnhancedMarketingContent(
                $name,
                $description,
                $category,
                $imageInsights,
                $brandVoice,
                $contentTone,
                $existingMarketingData
            );

            if ($marketingData) {
                // Fill missing fields with heuristic defaults to ensure rich marketing data
                try {
                    if (class_exists('MarketingHeuristics')) {
                        $heuristic = MarketingHeuristics::generateIntelligence(
                            $name,
                            $description,
                            $category,
                            Database::getInstance(),
                            $brandVoice,
                            $contentTone,
                            $existingMarketingData
                        );

                        $isEmptyValue = function ($value): bool {
                            if ($value === null) {
                                return true;
                            }
                            if (is_string($value)) {
                                return trim($value) === '';
                            }
                            if (is_array($value)) {
                                if (count($value) === 0) {
                                    return true;
                                }
                                $filtered = array_filter($value, function ($item) {
                                    if ($item === null) {
                                        return false;
                                    }
                                    if (is_string($item)) {
                                        return trim($item) !== '';
                                    }
                                    return true;
                                });
                                return count($filtered) === 0;
                            }
                            if (is_object($value)) {
                                return count(get_object_vars($value)) === 0;
                            }
                            return false;
                        };

                        foreach ($heuristic as $key => $value) {
                            if (!array_key_exists($key, $marketingData) || $isEmptyValue($marketingData[$key])) {
                                $marketingData[$key] = $value;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("suggest_all.php: Heuristic marketing merge failed: " . $e->getMessage());
                }

                $results['marketing_suggestion'] = [
                    'success' => true,
                    'title' => $marketingData['title'] ?? $name,
                    'description' => $marketingData['description'] ?? $description,
                    'keywords' => $marketingData['keywords'] ?? [],
                    'target_audience' => $marketingData['target_audience'] ?? '',
                    'selling_points' => $marketingData['selling_points'] ?? [],
                    'confidence' => $marketingData['confidence_score'] ?? 0.8,
                    'reasoning' => $marketingData['recommendation_reasoning'] ?? 'Generated based on current session data and images.',
                    'marketingIntelligence' => [
                        'demographic_targeting' => $marketingData['demographic_targeting'] ?? '',
                        'psychographic_profile' => $marketingData['psychographic_profile'] ?? '',
                        'seo_keywords' => $marketingData['seo_keywords'] ?? [],
                        'search_intent' => $marketingData['search_intent'] ?? '',
                        'seasonal_relevance' => $marketingData['seasonal_relevance'] ?? '',
                        'selling_points' => $marketingData['selling_points'] ?? [],
                        'competitive_advantages' => $marketingData['competitive_advantages'] ?? [],
                        'customer_benefits' => $marketingData['customer_benefits'] ?? [],
                        'call_to_action_suggestions' => $marketingData['call_to_action_suggestions'] ?? [],
                        'urgency_factors' => $marketingData['urgency_factors'] ?? [],
                        'conversion_triggers' => $marketingData['conversion_triggers'] ?? [],
                        'emotional_triggers' => $marketingData['emotional_triggers'] ?? [],
                        'marketing_channels' => $marketingData['marketing_channels'] ?? [],
                        'unique_selling_points' => $marketingData['unique_selling_points'] ?? '',
                        'value_propositions' => $marketingData['value_propositions'] ?? '',
                        'market_positioning' => $marketingData['market_positioning'] ?? '',
                        'brand_voice' => $marketingData['brand_voice'] ?? '',
                        'content_tone' => $marketingData['content_tone'] ?? '',
                        'pricing_psychology' => $marketingData['pricing_psychology'] ?? '',
                        'social_proof_elements' => $marketingData['social_proof_elements'] ?? [],
                        'objection_handlers' => $marketingData['objection_handlers'] ?? [],
                        'content_themes' => $marketingData['content_themes'] ?? [],
                        'pain_points_addressed' => $marketingData['pain_points_addressed'] ?? [],
                        'lifestyle_alignment' => $marketingData['lifestyle_alignment'] ?? [],
                        'analysis_factors' => $marketingData['analysis_factors'] ?? [],
                        'market_trends' => $marketingData['market_trends'] ?? []
                    ]
                ];

                // Auto-persist if SKU is available
                if (!empty($sku)) {
                    $normalizeJsonList = function ($value) {
                        if (is_string($value)) {
                            $trimmed = trim($value);
                            return $trimmed === '' ? [] : [$trimmed];
                        }
                        if (is_array($value)) {
                            return $value;
                        }
                        return [];
                    };

                    Database::execute("
                        INSERT INTO marketing_suggestions (
                            sku, suggested_title, suggested_description, keywords, target_audience,
                            demographic_targeting, psychographic_profile, seo_keywords, search_intent,
                            seasonal_relevance, selling_points, competitive_advantages,
                            customer_benefits, call_to_action_suggestions, urgency_factors,
                            conversion_triggers, emotional_triggers, marketing_channels,
                            unique_selling_points, value_propositions, market_positioning,
                            brand_voice, content_tone, pricing_psychology, social_proof_elements,
                            objection_handlers, content_themes, pain_points_addressed, lifestyle_alignment,
                            analysis_factors, market_trends,
                            confidence_score, recommendation_reasoning, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ON DUPLICATE KEY UPDATE 
                        suggested_title = VALUES(suggested_title),
                        suggested_description = VALUES(suggested_description),
                        keywords = VALUES(keywords),
                        target_audience = VALUES(target_audience),
                        demographic_targeting = VALUES(demographic_targeting),
                        psychographic_profile = VALUES(psychographic_profile),
                        seo_keywords = VALUES(seo_keywords),
                        search_intent = VALUES(search_intent),
                        seasonal_relevance = VALUES(seasonal_relevance),
                        selling_points = VALUES(selling_points),
                        competitive_advantages = VALUES(competitive_advantages),
                        customer_benefits = VALUES(customer_benefits),
                        call_to_action_suggestions = VALUES(call_to_action_suggestions),
                        urgency_factors = VALUES(urgency_factors),
                        conversion_triggers = VALUES(conversion_triggers),
                        emotional_triggers = VALUES(emotional_triggers),
                        marketing_channels = VALUES(marketing_channels),
                        unique_selling_points = VALUES(unique_selling_points),
                        value_propositions = VALUES(value_propositions),
                        market_positioning = VALUES(market_positioning),
                        brand_voice = VALUES(brand_voice),
                        content_tone = VALUES(content_tone),
                        pricing_psychology = VALUES(pricing_psychology),
                        social_proof_elements = VALUES(social_proof_elements),
                        objection_handlers = VALUES(objection_handlers),
                        content_themes = VALUES(content_themes),
                        pain_points_addressed = VALUES(pain_points_addressed),
                        lifestyle_alignment = VALUES(lifestyle_alignment),
                        analysis_factors = VALUES(analysis_factors),
                        market_trends = VALUES(market_trends),
                        confidence_score = VALUES(confidence_score),
                        recommendation_reasoning = VALUES(recommendation_reasoning),
                        created_at = CURRENT_TIMESTAMP
                    ", [
                        $sku,
                        $marketingData['title'] ?? $name,
                        $marketingData['description'] ?? $description,
                        json_encode($marketingData['keywords'] ?? []),
                        $marketingData['target_audience'] ?? '',
                        $marketingData['demographic_targeting'] ?? '',
                        $marketingData['psychographic_profile'] ?? '',
                        json_encode($marketingData['seo_keywords'] ?? []),
                        $marketingData['search_intent'] ?? '',
                        $marketingData['seasonal_relevance'] ?? '',
                        json_encode($marketingData['selling_points'] ?? []),
                        json_encode($marketingData['competitive_advantages'] ?? []),
                        json_encode($marketingData['customer_benefits'] ?? []),
                        json_encode($marketingData['call_to_action_suggestions'] ?? []),
                        json_encode($marketingData['urgency_factors'] ?? []),
                        json_encode($marketingData['conversion_triggers'] ?? []),
                        json_encode($marketingData['emotional_triggers'] ?? []),
                        json_encode($marketingData['marketing_channels'] ?? []),
                        json_encode($normalizeJsonList($marketingData['unique_selling_points'] ?? [])),
                        json_encode($normalizeJsonList($marketingData['value_propositions'] ?? [])),
                        $marketingData['market_positioning'] ?? '',
                        $marketingData['brand_voice'] ?? '',
                        $marketingData['content_tone'] ?? '',
                        $marketingData['pricing_psychology'] ?? '',
                        json_encode($marketingData['social_proof_elements'] ?? []),
                        json_encode($marketingData['objection_handlers'] ?? []),
                        json_encode($marketingData['content_themes'] ?? []),
                        json_encode($marketingData['pain_points_addressed'] ?? []),
                        json_encode($marketingData['lifestyle_alignment'] ?? []),
                        json_encode($marketingData['analysis_factors'] ?? []),
                        json_encode($marketingData['market_trends'] ?? []),
                        $marketingData['confidence_score'] ?? 0.5,
                        $marketingData['recommendation_reasoning'] ?? 'Generated by AI'
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Failed to generate marketing in suggest_all: " . $e->getMessage());
            $results['marketing_suggestion'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // If this is a marketing-only step, return now
        if ($step === 'marketing') {
            Response::json($results);
        }
    }

    Response::json($results);

} catch (Throwable $e) {
    error_log("Error in suggest_all.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred: ' . $e->getMessage());
}
