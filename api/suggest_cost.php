<?php
// api/suggest_cost.php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/ai_providers.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/ai_manager.php';
require_once __DIR__ . '/../includes/ai/helpers/CostHeuristics.php';
require_once __DIR__ . '/../includes/ai/helpers/TierScalingHelper.php';

AuthHelper::requireAdmin();

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    Response::error('Invalid JSON input.', null, 400);
}

$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$category = trim($input['category'] ?? '');
$sku = trim($input['sku'] ?? '');
$qualityTier = trim($input['quality_tier'] ?? 'standard');
$useImages = $input['useImages'] ?? false;

if (empty($name)) {
    Response::error('Item name is required for cost suggestion.', null, 400);
}

try {
    $aiProviders = getAIProviders();
    $images = [];
    if ($useImages && !empty($sku)) {
        try {
            $imageRows = Database::queryAll("SELECT image_path FROM item_images WHERE sku = ? ORDER BY sort_order ASC LIMIT 3", [$sku]);
            foreach ($imageRows as $row) {
                $abs = __DIR__ . '/../' . $row['image_path'];
                if (file_exists($abs)) {
                    $images[] = $abs;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to load images for cost: " . $e->getMessage());
        }
    }

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

    // Apply tier multiplier
    $multiplier = TierScalingHelper::getMultiplier($qualityTier);

    if ($multiplier !== 1.0) {
        $original_cost = (float) $costData['cost'];
        $adjustedCost = $original_cost * $multiplier;

        $costData['cost'] = $adjustedCost;

        // Update breakdown by scaling each category proportionately
        if (!empty($costData['breakdown']) && is_array($costData['breakdown'])) {
            TierScalingHelper::scaleBreakdown($costData['breakdown'], $multiplier);

            // Add an explicit adjustment entry to the breakdown for clarity if it's a flat structure
            if (!isset($costData['breakdown']['tier_adjustment'])) {
                $costData['breakdown']['tier_adjustment'] = $adjustedCost - $original_cost;
            }
        }

        $costData['reasoning'] .= ' • Cost adjusted by quality tier multiplier (' . $multiplier . 'x).';
    }

    // Normalize confidence to numeric for DB
    $confidenceLabel = $costData['confidence'] ?? 'medium';
    $confidenceValue = is_numeric($confidenceLabel)
        ? floatval($confidenceLabel)
        : (strtolower($confidenceLabel) === 'high' ? 0.9
            : (strtolower($confidenceLabel) === 'low' ? 0.2 : 0.5));

    if (!empty($sku)) {
        try {
            $analysis = $costData['analysis'] ?? [];
            $breakdown = $costData['breakdown'] ?? [];

            Database::execute("
                INSERT INTO cost_suggestions (
                    sku, suggested_cost, reasoning, confidence, breakdown,
                    detected_materials, detected_features, size_analysis, complexity_score,
                    production_time_estimate, skill_level_required, market_positioning, eco_friendly_score,
                    materials_cost_amount, labor_cost_amount, energy_cost_amount, equipment_cost_amount,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE 
                suggested_cost = VALUES(suggested_cost),
                reasoning = VALUES(reasoning),
                confidence = VALUES(confidence),
                breakdown = VALUES(breakdown),
                detected_materials = VALUES(detected_materials),
                detected_features = VALUES(detected_features),
                size_analysis = VALUES(size_analysis),
                complexity_score = VALUES(complexity_score),
                production_time_estimate = VALUES(production_time_estimate),
                skill_level_required = VALUES(skill_level_required),
                market_positioning = VALUES(market_positioning),
                eco_friendly_score = VALUES(eco_friendly_score),
                materials_cost_amount = VALUES(materials_cost_amount),
                labor_cost_amount = VALUES(labor_cost_amount),
                energy_cost_amount = VALUES(energy_cost_amount),
                equipment_cost_amount = VALUES(equipment_cost_amount),
                created_at = CURRENT_TIMESTAMP
            ", [
                $sku,
                $costData['cost'],
                $costData['reasoning'],
                $confidenceValue,
                json_encode($breakdown),
                json_encode($analysis['detected_materials'] ?? []),
                json_encode($analysis['detected_features'] ?? []),
                json_encode($analysis['size_analysis'] ?? []),
                $analysis['complexity_score'] ?? 0.5,
                $analysis['production_time_estimate'] ?? 0,
                $analysis['skill_level_required'] ?? 'intermediate',
                $analysis['market_positioning'] ?? 'standard',
                $analysis['eco_friendly_score'] ?? 0.5,
                $breakdown[WF_Constants::COST_CATEGORY_MATERIALS] ?? 0,
                $breakdown[WF_Constants::COST_CATEGORY_LABOR] ?? 0,
                $breakdown[WF_Constants::COST_CATEGORY_ENERGY] ?? 0,
                $breakdown[WF_Constants::COST_CATEGORY_EQUIPMENT] ?? 0
            ]);
        } catch (PDOException $e) {
            error_log("Error saving cost suggestion: " . $e->getMessage());
        }
    }

    Response::json([
        'success' => true,
        'suggested_cost' => $costData['cost'],
        'reasoning' => $costData['reasoning'],
        'confidence' => $confidenceValue,
        'confidenceLabel' => $confidenceLabel,
        'breakdown' => $costData['breakdown'] ?? [],
        'analysis' => $costData['analysis'] ?? [],
        'created_at' => date('c'),
        'providerUsed' => $aiProviders->getSettings()['ai_provider'] ?? 'jons_ai'
    ]);

} catch (Throwable $e) {
    error_log("Error in suggest_cost.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    Response::serverError('Internal server error occurred: ' . $e->getMessage());
}
