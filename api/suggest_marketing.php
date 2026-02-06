<?php
// api/suggest_marketing.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';
require_once __DIR__ . '/marketing_helper.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/ai/helpers/MarketingHeuristics.php';

AuthHelper::requireAdmin();

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

set_time_limit(120);
ini_set('max_execution_time', 120);

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
$freshStart = isset($input['fresh_start']) ? (bool) $input['fresh_start'] : false;
$whimsicalTheme = isset($input['whimsicalTheme']) ? (bool) $input['whimsicalTheme'] : null;
$preferredBrandVoice = trim($input['brandVoice'] ?? '');
$preferredContentTone = trim($input['contentTone'] ?? '');
$useImages = $input['useImages'] ?? false;

if (empty($name)) {
    Response::error('Item name is required for marketing suggestion.', 400);
}

// Brand voice/tone logic
$brandVoicePrompt = $preferredBrandVoice;
$contentTonePrompt = $preferredContentTone;

if ($whimsicalTheme !== null) {
    $preferredBrandVoice = $whimsicalTheme ? 'playful' : 'bold';
    $preferredContentTone = $whimsicalTheme ? 'whimsical_frog' : 'no_frog';
    $brandVoicePrompt = $whimsicalTheme ? 'playful, friendly brand with subtle whimsical frog themes' : 'bold, confident item copywriter';
    $contentTonePrompt = $whimsicalTheme ? 'light, inviting, and slightly whimsical' : 'provocative and intriguing; no frog-themed terminology';
}

$themeInspiration = [];
if ($whimsicalTheme || $preferredContentTone === 'whimsical_frog') {
    require_once __DIR__ . '/../includes/theme_words/manager.php';
    $themeInspiration = get_whimsical_inspiration(5);
    $wordList = array_map(fn($item) => $item['text'], $themeInspiration);
    if (!empty($wordList)) {
        $brandVoicePrompt .= ". Incorporate these brand-aligned keywords organically: " . implode(', ', $wordList);
        $contentTonePrompt .= ". Use a selection of these words if they fit the context: " . implode(', ', $wordList);
    }
}

try {
    $aiProviders = getAIProviders();
    $existingMarketingData = null;

    if (!$freshStart && !empty($sku)) {
        try {
            $existingMarketingData = Database::queryOne("SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1", [$sku]);
        } catch (Exception $e) {
            error_log("Error fetching existing marketing data: " . $e->getMessage());
        }
    }

    $images = [];
    if ($useImages && !empty($sku)) {
        try {
            $imageRows = Database::queryAll("SELECT image_path FROM item_images WHERE sku = ? ORDER BY sort_order ASC LIMIT 3", [$sku]);
            foreach ($imageRows as $row) {
                $abs = __DIR__ . '/../' . $row['image_path'];
                if (file_exists($abs))
                    $images[] = $abs;
            }
        } catch (Exception $e) {
            error_log("Failed to load images for marketing: " . $e->getMessage());
        }
    }

    $imageAnalysisData = [];
    $imageInsights = '';
    if (!empty($images) && $useImages) {
        try {
            $imageAnalysisData = $aiProviders->analyzeImagesForAltText($images, $name, $description, $category);
            $imageInsights = $aiProviders->extractMarketingInsightsFromImages($imageAnalysisData, $name, $category);
        } catch (Exception $e) {
            error_log("Image analysis failed: " . $e->getMessage());
        }
    }

    $marketingData = $aiProviders->generateEnhancedMarketingContent(
        $name,
        $description,
        $category,
        $imageInsights,
        $brandVoicePrompt,
        $contentTonePrompt,
        $existingMarketingData
    );

    if (!$marketingData || !is_array($marketingData)) {
        throw new Exception('AI generation returned invalid or empty data.');
    }

    // Fill missing fields with heuristic defaults to ensure rich marketing data
    try {
        if (class_exists('MarketingHeuristics')) {
            $heuristic = MarketingHeuristics::generateIntelligence(
                $name,
                $description,
                $category,
                Database::getInstance(),
                $preferredBrandVoice,
                $preferredContentTone,
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
        error_log("Heuristic marketing merge failed: " . $e->getMessage());
    }

    if (!empty($sku)) {
        try {
            error_log("Attempting to persist marketing data for SKU: $sku");
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
                $marketingData['title'] ?? 'New Item',
                $marketingData['description'] ?? '',
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

            if (!empty($themeInspiration)) {
                log_theme_words_usage($themeInspiration, 'marketing_suggestion', $sku);
            }

            error_log("Marketing data persisted successfully for SKU: $sku");
        } catch (Throwable $e) {
            error_log("Error saving marketing suggestion for SKU $sku: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    error_log("Returning marketing success response for SKU: $sku");
    Response::json([
        'success' => true,
        'title' => $marketingData['title'] ?? 'New Item',
        'description' => $marketingData['description'] ?? '',
        'keywords' => $marketingData['keywords'] ?? [],
        'targetAudience' => $marketingData['target_audience'] ?? '',
        'imageAnalysis' => $imageAnalysisData,
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
        ],
        'confidence' => $marketingData['confidence_score'] ?? 0.5,
        'reasoning' => $marketingData['recommendation_reasoning'] ?? ''
    ]);

} catch (Throwable $e) {
    error_log("CRITICAL error in suggest_marketing.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    Response::serverError('Internal server error: ' . $e->getMessage());
}
