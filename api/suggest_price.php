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

    // Default to market-average heuristic unless another strategy leads by >20 confidence points
    $heuristic = PricingHeuristics::analyze($name, $description, $category, $cost_price, Database::getInstance());
    if (!empty($heuristic['price'])) {
        $pricingData = $heuristic;
        $pricingData['reasoning'] = ($pricingData['reasoning'] ?? '') . ' • Market-average default applied.';
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

    if (!empty($sku)) {
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

    Response::json([
        'success' => true,
        'suggested_price' => $pricingData['price'],
        'reasoning' => $pricingData['reasoning'],
        'confidence' => $pricingData['confidence'],
        'factors' => $pricingData['factors'] ?? [],
        'components' => $pricingData['components'] ?? [],
        'analysis' => $pricingData['analysis'] ?? []
    ]);

} catch (Throwable $e) {
    error_log("Error in suggest_price.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    Response::serverError('Internal server error occurred: ' . $e->getMessage());
}
