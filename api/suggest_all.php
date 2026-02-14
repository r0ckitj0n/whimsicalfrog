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
require_once __DIR__ . '/../includes/ai/settings_manager.php';
require_once __DIR__ . '/../includes/ai/helpers/AICostEventStore.php';

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
$whimsicalTheme = isset($input['whimsicalTheme']) ? (bool) $input['whimsicalTheme'] : null;
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

function wf_compose_ai_failure_details($aiProviders, $baseMessage, $exceptionMessage = '')
{
    $details = [];
    if (method_exists($aiProviders, 'getLastRunDiagnostics')) {
        $details = $aiProviders->getLastRunDiagnostics();
    }

    $provider = trim((string) ($details['provider'] ?? 'unknown'));
    $model = trim((string) ($details['model'] ?? 'unknown'));
    $providerError = trim((string) ($details['provider_error'] ?? ''));
    $fallbackUsed = !empty($details['fallback_used']) ? 'yes' : 'no';
    $fallbackAttempted = !empty($details['fallback_attempted']) ? 'yes' : 'no';
    $method = trim((string) ($details['method'] ?? 'unknown'));

    $parts = [
        $baseMessage,
        "provider={$provider}",
        "model={$model}",
        "method={$method}",
        "fallback_attempted={$fallbackAttempted}",
        "fallback_used={$fallbackUsed}",
    ];

    if ($providerError !== '') {
        $parts[] = "provider_error={$providerError}";
    }
    if ($exceptionMessage !== '') {
        $parts[] = "error={$exceptionMessage}";
    }

    return [
        'message' => implode(' | ', $parts),
        'details' => $details
    ];
}

function wf_normalize_confidence_score($confidence)
{
    if (is_numeric($confidence)) {
        $value = (float) $confidence;
        if ($value <= 1.0) {
            return max(0.0, min(1.0, $value));
        }
        return max(0.0, min(1.0, $value / 100.0));
    }

    $normalized = strtolower(trim((string) $confidence));
    if ($normalized === 'very high') {
        return 0.95;
    }
    if ($normalized === 'high') {
        return 0.85;
    }
    if ($normalized === 'medium') {
        return 0.65;
    }
    if ($normalized === 'low') {
        return 0.40;
    }

    return 0.50;
}

function wf_pick_consensus_category($analyses, $fallbackCategory)
{
    if (!is_array($analyses) || count($analyses) === 0) {
        return $fallbackCategory;
    }

    $votes = [];
    foreach ($analyses as $entry) {
        $candidate = trim((string) ($entry['analysis']['category'] ?? ''));
        if ($candidate === '') {
            continue;
        }
        $key = strtolower($candidate);
        if (!isset($votes[$key])) {
            $votes[$key] = ['label' => $candidate, 'count' => 0];
        }
        $votes[$key]['count'] += 1;
    }

    if (count($votes) === 0) {
        return $fallbackCategory;
    }

    usort($votes, function ($a, $b) {
        return ($b['count'] <=> $a['count']);
    });

    return (string) ($votes[0]['label'] ?? $fallbackCategory);
}

function wf_resolve_png_analysis_image($imagePath)
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '') {
        return null;
    }

    if (strpos($imagePath, 'data:') === 0) {
        return (stripos($imagePath, 'data:image/png') === 0) ? $imagePath : null;
    }

    $pathInfo = pathinfo($imagePath);
    $extension = strtolower((string) ($pathInfo['extension'] ?? ''));
    if ($extension === 'png') {
        return $imagePath;
    }

    $dirname = (string) ($pathInfo['dirname'] ?? '');
    $filename = (string) ($pathInfo['filename'] ?? '');
    if ($dirname === '' || $filename === '') {
        return null;
    }

    $pngCandidate = $dirname . DIRECTORY_SEPARATOR . $filename . '.png';
    if (file_exists($pngCandidate)) {
        return $pngCandidate;
    }

    return null;
}

function wf_text_has_any_token($text, $tokens): bool
{
    $text = strtolower((string) $text);
    if (!is_array($tokens) || empty($tokens)) {
        return false;
    }
    foreach ($tokens as $t) {
        $t = strtolower(trim((string) $t));
        if ($t === '') {
            continue;
        }
        if (preg_match('/\\b' . preg_quote($t, '/') . '\\b/i', $text)) {
            return true;
        }
    }
    return false;
}

function wf_apply_theme_word_to_title($title, $themeWords): string
{
    $title = trim((string) $title);
    if ($title === '' || !is_array($themeWords) || empty($themeWords)) {
        return $title;
    }
    if (wf_text_has_any_token($title, $themeWords)) {
        return $title;
    }

    $w = trim((string) ($themeWords[0] ?? ''));
    if ($w === '') {
        return $title;
    }

    // Keep it simple and consistent: a single brand-aligned lead word.
    $candidate = trim($w . ' ' . $title);
    return $candidate;
}

function wf_apply_theme_word_to_description($description, $themeWords): string
{
    $description = trim((string) $description);
    if ($description === '' || !is_array($themeWords) || empty($themeWords)) {
        return $description;
    }
    if (wf_text_has_any_token($description, $themeWords)) {
        return $description;
    }

    $w = trim((string) ($themeWords[1] ?? $themeWords[0] ?? ''));
    if ($w === '') {
        return $description;
    }

    // Short, low-risk suffix sentence that naturally includes the token.
    return rtrim($description, ". \t\n\r\0\x0B") . ". A touch of {$w} charm.";
}

try {
    $aiProviders = getAIProviders();
    $images = [];

    // Prioritize passed imageData (string or array of image paths)
    if ($imageData) {
        $basePath = dirname(__DIR__); // One level up from /api to get project root
        $rawImages = is_array($imageData) ? $imageData : [$imageData];

        foreach ($rawImages as $rawImage) {
            if (!is_string($rawImage)) {
                continue;
            }
            $rawImage = trim($rawImage);
            if ($rawImage === '') {
                continue;
            }

            // If imageData is a relative web path (starts with /), convert to absolute filesystem path
            if (strpos($rawImage, '/') === 0 && strpos($rawImage, 'data:') !== 0) {
                $absolutePath = $basePath . $rawImage;
                $pngPath = wf_resolve_png_analysis_image($absolutePath);
                if ($pngPath !== null) {
                    $images[] = $pngPath;
                    error_log("suggest_all.php: Converted web path to PNG analysis path: $pngPath");
                } else {
                    error_log("suggest_all.php: PNG analysis image not found for converted path: $absolutePath");
                }
            } else {
                // It's either a data URI, pre-resolved path, or other supported format.
                $pngPath = wf_resolve_png_analysis_image($rawImage);
                if ($pngPath !== null) {
                    $images[] = $pngPath;
                }
            }
        }
    } else if ($useImages && !empty($sku)) {
        $storedImages = AIProviders::getItemImages($sku, 3);
        foreach ($storedImages as $storedImage) {
            $pngPath = wf_resolve_png_analysis_image($storedImage);
            if ($pngPath !== null) {
                $images[] = $pngPath;
            }
        }
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
            $unsupported = wf_compose_ai_failure_details(
                $aiProviders,
                'Selected AI model does not support image analysis',
                'Switch to a vision-capable model in AI Settings and re-test the provider'
            );
            Response::error($unsupported['message'], $unsupported['details'], 400);
        }
        if (empty($images)) {
            Response::error('Image analysis is required, but no PNG image was found for this item. Upload at least one PNG image and try again.', null, 400);
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

                $analysisCandidates = [];
                $analysisErrors = [];

                foreach ($images as $index => $imagePath) {
                    try {
                        error_log("suggest_all.php [info step]: Calling analyzeItemImage for image index {$index}");
                        $analysis = $aiProviders->analyzeItemImage($imagePath, $existingCategories);
                        error_log("suggest_all.php [info step]: analyzeItemImage returned for image index {$index}: " . json_encode($analysis));

                        $analysisLooksValid = is_array($analysis)
                            && !empty(trim((string) ($analysis['title'] ?? '')))
                            && !empty(trim((string) ($analysis['category'] ?? '')))
                            && empty($analysis['error']);

                        if (!$analysisLooksValid) {
                            $parseError = is_array($analysis) ? trim((string) ($analysis['error'] ?? '')) : '';
                            $analysisErrors[] = $parseError !== '' ? $parseError : "Image index {$index} returned incomplete analysis payload";
                            continue;
                        }

                        $analysisCandidates[] = [
                            'analysis' => $analysis,
                            'score' => wf_normalize_confidence_score($analysis['confidence'] ?? null),
                        ];
                    } catch (Exception $perImageError) {
                        $analysisErrors[] = "Image index {$index} failed: " . $perImageError->getMessage();
                    }
                }

                if (!empty($analysisCandidates)) {
                    usort($analysisCandidates, function ($a, $b) {
                        return (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
                    });

                    $bestAnalysis = $analysisCandidates[0]['analysis'];
                    $consensusCategory = wf_pick_consensus_category($analysisCandidates, (string) ($bestAnalysis['category'] ?? ''));
                    $resolvedCategory = wf_resolve_category_from_analysis(
                        $consensusCategory,
                        $bestAnalysis['title'] ?? '',
                        $bestAnalysis['description'] ?? '',
                        $existingCategories
                    );
                    $reasoningSuffix = " (Analyzed " . count($analysisCandidates) . " image(s) before generating info.)";
                    if (!empty($analysisErrors)) {
                        $reasoningSuffix .= " Some images were skipped: " . implode(' | ', $analysisErrors) . ".";
                    }

                    // Theme words (AI Settings controlled) for name/description.
                    $aiThemeSettings = AISettingsManager::getAISettings();
                    $themeWordsEnabled = (bool) ($aiThemeSettings['ai_theme_words_enabled'] ?? true);
                    $themeWordsEnabledName = (bool) ($aiThemeSettings['ai_theme_words_enabled_name'] ?? true);
                    $themeWordsEnabledDescription = (bool) ($aiThemeSettings['ai_theme_words_enabled_description'] ?? true);
                    $themeModeRequested = ($whimsicalTheme === true)
                        || (strtolower($contentTone) === 'whimsical_frog')
                        || (strtolower((string) ($aiThemeSettings['ai_content_tone'] ?? '')) === 'whimsical_frog');

                    $themeInspiration = [];
                    $themeWordTokens = [];
                    if ($themeWordsEnabled && $themeModeRequested && ($themeWordsEnabledName || $themeWordsEnabledDescription)) {
                        require_once __DIR__ . '/../includes/theme_words/manager.php';
                        $themeInspiration = get_whimsical_inspiration(3);
                        $themeWordTokens = array_values(array_filter(array_map(function ($item) {
                            return is_array($item) ? (string) ($item['text'] ?? '') : '';
                        }, $themeInspiration)));
                    }

                    $rawTitle = (string) ($bestAnalysis['title'] ?? '');
                    $rawDescription = (string) ($bestAnalysis['description'] ?? '');
                    $titleWithTheme = $themeWordsEnabledName ? wf_apply_theme_word_to_title($rawTitle, $themeWordTokens) : $rawTitle;
                    $descriptionWithTheme = $themeWordsEnabledDescription ? wf_apply_theme_word_to_description($rawDescription, $themeWordTokens) : $rawDescription;

                    if (!empty($themeInspiration)) {
                        // Source = SKU when we have it, otherwise a stable label.
                        log_theme_words_usage($themeInspiration, 'info_suggestion', $sku !== '' ? $sku : 'suggest_all_info');
                    }

                    $results['info_suggestion'] = [
                        'success' => true,
                        'name' => wf_apply_locked_words($titleWithTheme, $lockedWords['name'] ?? ''),
                        'description' => wf_apply_locked_words($descriptionWithTheme, $lockedWords['description'] ?? ''),
                        'category' => wf_apply_locked_words($resolvedCategory, $lockedWords['category'] ?? ''),
                        'confidence' => $bestAnalysis['confidence'] ?? 'medium',
                        'reasoning' => ($bestAnalysis['reasoning'] ?? '') . $reasoningSuffix
                    ];
                    // Update local variables for subsequent suggestions (image-first chain).
                    $name = $results['info_suggestion']['name'];
                    $description = $results['info_suggestion']['description'];
                    $category = $results['info_suggestion']['category'];
                } else {
                    error_log("suggest_all.php [info step]: analyzeItemImage returned null/empty");
                    $failure = wf_compose_ai_failure_details(
                        $aiProviders,
                        'Image analysis failed',
                        !empty($analysisErrors) ? implode(' | ', $analysisErrors) : 'Provider returned incomplete analysis payload'
                    );
                    Response::error($failure['message'], $failure['details'], 400);
                }
            } catch (Exception $e) {
                error_log("Info analysis failed in suggest_all: " . $e->getMessage());
                $failure = wf_compose_ai_failure_details($aiProviders, 'Image analysis failed', $e->getMessage());
                Response::error($failure['message'], $failure['details'], 400);
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
            // Log actual cost based on job counts (not estimate).
            // Info step: one image analysis call per image + one text call for dimensions suggestion.
            try {
                $settings = $aiProviders->getSettings();
                $pm = AICostEventStore::resolveProviderAndModelFromSettings(is_array($settings) ? $settings : []);

                $dimensionsAttempted = (!empty($dimensionsContextName) || !empty($dimensionsContextDescription) || !empty($dimensionsContextCategory));
                AICostEventStore::logEvent([
                    'endpoint' => 'suggest_all',
                    'step' => 'info',
                    'sku' => $sku !== '' ? $sku : null,
                    'provider' => $pm['provider'],
                    'model' => $pm['model'],
                    'text_jobs' => $dimensionsAttempted ? 1 : 0,
                    'image_analysis_jobs' => $useImages ? count($images) : 0,
                    'image_creation_jobs' => 0,
                    'request_meta' => [
                        'use_images' => (bool) $useImages,
                        'image_count' => is_array($images) ? count($images) : 0,
                        'image_first_priority' => (bool) $imageFirstPriority
                    ]
                ]);
            } catch (Throwable $logErr) {
                error_log('suggest_all.php: failed logging AI cost event: ' . $logErr->getMessage());
            }
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
            // Log actual cost based on job counts (not estimate).
            // Cost step: one multimodal call when images are provided; otherwise one text call.
            try {
                $settings = $aiProviders->getSettings();
                $pm = AICostEventStore::resolveProviderAndModelFromSettings(is_array($settings) ? $settings : []);

                $usesImagesForCost = (!empty($images) && $useImages);
                AICostEventStore::logEvent([
                    'endpoint' => 'suggest_all',
                    'step' => 'cost',
                    'sku' => $sku !== '' ? $sku : null,
                    'provider' => $pm['provider'],
                    'model' => $pm['model'],
                    'text_jobs' => $usesImagesForCost ? 0 : 1,
                    'image_analysis_jobs' => $usesImagesForCost ? 1 : 0,
                    'image_creation_jobs' => 0,
                    'request_meta' => [
                        'use_images' => (bool) $useImages,
                        'image_count' => is_array($images) ? count($images) : 0,
                    ]
                ]);
            } catch (Throwable $logErr) {
                error_log('suggest_all.php: failed logging AI cost event (cost): ' . $logErr->getMessage());
            }
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
            // Log actual cost based on job counts (not estimate).
            // Price step: one multimodal call when images are provided; otherwise one text call.
            try {
                $settings = $aiProviders->getSettings();
                $pm = AICostEventStore::resolveProviderAndModelFromSettings(is_array($settings) ? $settings : []);

                $usesImagesForPrice = (!empty($images) && $useImages);
                AICostEventStore::logEvent([
                    'endpoint' => 'suggest_all',
                    'step' => 'price',
                    'sku' => $sku !== '' ? $sku : null,
                    'provider' => $pm['provider'],
                    'model' => $pm['model'],
                    'text_jobs' => $usesImagesForPrice ? 0 : 1,
                    'image_analysis_jobs' => $usesImagesForPrice ? 1 : 0,
                    'image_creation_jobs' => 0,
                    'request_meta' => [
                        'use_images' => (bool) $useImages,
                        'image_count' => is_array($images) ? count($images) : 0,
                    ]
                ]);
            } catch (Throwable $logErr) {
                error_log('suggest_all.php: failed logging AI cost event (price): ' . $logErr->getMessage());
            }
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
            // Log actual cost based on job counts (not estimate).
            // Marketing step here is text-only (image insights are heuristic/local in this endpoint).
            try {
                $settings = $aiProviders->getSettings();
                $pm = AICostEventStore::resolveProviderAndModelFromSettings(is_array($settings) ? $settings : []);

                AICostEventStore::logEvent([
                    'endpoint' => 'suggest_all',
                    'step' => 'marketing',
                    'sku' => $sku !== '' ? $sku : null,
                    'provider' => $pm['provider'],
                    'model' => $pm['model'],
                    'text_jobs' => 1,
                    'image_analysis_jobs' => 0,
                    'image_creation_jobs' => 0,
                    'request_meta' => [
                        'use_images' => (bool) $useImages,
                        'image_count' => is_array($images) ? count($images) : 0,
                    ]
                ]);
            } catch (Throwable $logErr) {
                error_log('suggest_all.php: failed logging AI cost event (marketing): ' . $logErr->getMessage());
            }
            Response::json($results);
        }
    }

    Response::json($results);

} catch (Throwable $e) {
    error_log("Error in suggest_all.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred: ' . $e->getMessage());
}
