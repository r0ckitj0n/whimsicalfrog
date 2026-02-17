<?php
// api/suggest_price.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/ai_manager.php';
require_once __DIR__ . '/../includes/ai/helpers/PricingHeuristics.php';
require_once __DIR__ . '/../includes/ai/helpers/TierScalingHelper.php';
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

$qualityTier = isset($input['quality_tier']) ? trim($input['quality_tier']) : 'standard';
if ($qualityTier === '')
    $qualityTier = 'standard';

if (empty($input['name'])) {
    Response::error('Item name is required.', 400);
}

$name = trim($input['name']);
$description = trim($input['description'] ?? '');
$category = trim($input['category'] ?? '');
$cost_price = floatval($input['cost_price'] ?? 0);
$sku = trim($input['sku'] ?? '');
$useImages = $input['useImages'] ?? false;

try {
    $aiProviders = getAIProviders();
    $images = [];
    if ($useImages && !empty($sku)) {
        $images = AIProviders::getItemImages($sku, 3);
    }

    if (!empty($images) && $useImages) {
        $pricingData = $aiProviders->generatePricingSuggestionWithImages($name, $description, $category, $cost_price, $images);
    } else {
        $pricingData = $aiProviders->generatePricingSuggestion($name, $description, $category, $cost_price);
    }

    $diagnostics = [];
    try {
        $diagnostics = $aiProviders->getLastRunDiagnostics();
    } catch (Throwable $e) {
        $diagnostics = [];
    }

    $fallbackUsed = false;
    $fallbackKind = 'none';
    $fallbackReason = '';

    if (!empty($diagnostics['fallback_used'])) {
        $fallbackUsed = true;
        $fallbackKind = 'provider_fallback';
        $providerName = (string) ($diagnostics['provider'] ?? ($aiProviders->getSettings()['ai_provider'] ?? 'unknown'));
        $providerErr = (string) ($diagnostics['provider_error'] ?? 'unknown error');
        $fallbackReason = "Primary provider '{$providerName}' failed: {$providerErr}. Used local fallback provider.";
    }

    $toConfidence = static function ($raw): float {
        if (is_array($raw)) {
            $vals = array_values(array_filter($raw, fn($v) => is_numeric($v)));
            if (!empty($vals)) {
                $avg = array_sum($vals) / count($vals);
                return max(0.0, min(1.0, (float) $avg));
            }
        }
        if (is_numeric($raw)) return max(0.0, min(1.0, (float) $raw));
        $s = strtolower(trim((string) $raw));
        if ($s === 'high') return 0.9;
        if ($s === 'low') return 0.2;
        if ($s === 'medium') return 0.5;
        return 0.0;
    };

    $aiPrice = isset($pricingData['price']) ? (float) $pricingData['price'] : 0.0;
    if ($aiPrice <= 0.0) {
        // Per project policy: do not silently "fallback price" when a stored suggestion exists.
        // If we have something already in DB, keep it and let the client display it (stale-cache path).
        if (!empty($sku)) {
            $existing = Database::queryOne(
                "SELECT suggested_price, created_at FROM price_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1",
                [$sku]
            );
            if ($existing) {
                Response::serverError('AI price generation failed; keeping existing stored price suggestion.');
            }
        }

        // No stored suggestion exists -> last-resort fallback.
        $heuristic = PricingHeuristics::analyze($name, $description, $category, $cost_price, Database::getInstance());
        $heurPrice = isset($heuristic['price']) ? (float) $heuristic['price'] : 0.0;
        if ($heurPrice > 0.0) {
            $pricingData = $heuristic;
            $pricingData['reasoning'] = ($pricingData['reasoning'] ?? '') . ' • AI output unavailable, market-average fallback applied.';
            $fallbackUsed = true;
            $fallbackKind = 'heuristic';
            $fallbackReason = 'AI output was unavailable or invalid (price <= 0). Market-average heuristic fallback applied.';
            if (!empty($diagnostics['provider_error'])) {
                $providerName = (string) ($diagnostics['provider'] ?? ($aiProviders->getSettings()['ai_provider'] ?? 'unknown'));
                $fallbackReason .= " Provider error from '{$providerName}': " . (string) $diagnostics['provider_error'];
            }
        } else {
            Response::serverError('AI price generation failed and no heuristic fallback was available.');
        }
    }

    // Apply tier multiplier
    $multiplier = TierScalingHelper::getMultiplier($qualityTier);

    if ($multiplier !== 1.0) {
        $original_price = (float) $pricingData['price'];
        $adjustedPrice = $original_price * $multiplier;

        // Basic range validation
        if ($cost_price > 0) {
            $adjustedPrice = max($cost_price * 1.5, min($cost_price * 8.0, $adjustedPrice));
        }
        $adjustedPrice = max(1.0, $adjustedPrice);

        // Effective multiplier might differ if range validation kicked in
        $effectiveMultiplier = $original_price > 0 ? ($adjustedPrice / $original_price) : $multiplier;

        $pricingData['price'] = $adjustedPrice;

        if (!empty($pricingData['components']) && is_array($pricingData['components'])) {
            TierScalingHelper::scalePricingComponents($pricingData['components'], $effectiveMultiplier);
        }

        $pricingData['reasoning'] .= ' • Tier adjustment distributed to components.';
    }

    // Persist only when this is a real AI-generated price (not heuristic fallback).
    if (!empty($sku) && $fallbackKind !== 'heuristic') {
        try {
            Database::execute("
                INSERT INTO price_suggestions (
                    sku, suggested_price, reasoning, confidence, factors, components,
                    created_at
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
        } catch (PDOException $e) {
            error_log("Error saving price suggestion: " . $e->getMessage());
        }
    }

    // Record job-based cost for this completed AI run (used for reporting and to keep pricing rates warm).
    // Skip when heuristic fallback was used (no external AI call).
    if ($fallbackKind !== 'heuristic') {
        try {
            $settings = is_object($aiProviders) ? ($aiProviders->getSettings() ?? []) : [];
            $pm = AICostEventStore::resolveProviderAndModelFromSettings(is_array($settings) ? $settings : []);

            // If a local provider fallback was used, don't record external-cost events.
            if (!$fallbackUsed) {
                $textJobs = (!empty($images) && $useImages) ? 0 : 1;
                $analysisJobs = (!empty($images) && $useImages) ? 1 : 0; // single multimodal call
                AICostEventStore::logEvent([
                    'endpoint' => 'suggest_price.php',
                    'step' => 'generate_price',
                    'provider' => (string) ($pm['provider'] ?? 'default'),
                    'model' => (string) ($pm['model'] ?? 'default-model'),
                    'sku' => $sku !== '' ? $sku : null,
                    'text_jobs' => $textJobs,
                    'image_analysis_jobs' => $analysisJobs,
                    'image_creation_jobs' => 0,
                    'request_meta' => [
                        'quality_tier' => $qualityTier,
                        'use_images' => (bool) $useImages,
                        'image_count' => is_array($images) ? count($images) : 0,
                    ]
                ]);
            }
        } catch (Throwable $e) {
            error_log('Failed to log AI cost event for suggest_price: ' . $e->getMessage());
        }
    }

    Response::json([
        'success' => true,
        'suggested_price' => $pricingData['price'],
        'reasoning' => $pricingData['reasoning'],
        'confidence' => $pricingData['confidence'],
        'factors' => $pricingData['factors'] ?? [],
        'components' => $pricingData['components'] ?? [],
        'analysis' => $pricingData['analysis'] ?? [],
        'fallback_used' => (bool) $fallbackUsed,
        'fallback_kind' => (string) $fallbackKind,
        'fallback_reason' => (string) $fallbackReason
    ]);

} catch (Throwable $e) {
    error_log("Error in suggest_price.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    Response::serverError('Internal server error occurred: ' . $e->getMessage());
}
