<?php
// includes/ai/helpers/CostHeuristics.php

require_once __DIR__ . '/../../Constants.php';

class CostHeuristics
{
    public static function analyze($name, $description, $category, $pdo, $qualityTier = WF_Constants::QUALITY_TIER_STANDARD)
    {
        $category = self::pickCategory($category, $name, $description);
        $analysis = self::analyzeItemEnhanced($name, $description, $category);
        $baseCosts = self::getCategoryBaseCosts($category);

        $materialsCost = self::calculateMaterialsCost($analysis, $baseCosts, $category);
        $laborCost = self::calculateLaborCost($analysis, $baseCosts, $category);
        $energyCost = self::calculateEnergyCost($analysis, $baseCosts, $category);
        $equipmentCost = self::calculateEquipmentCost($analysis, $baseCosts, $category);

        $complexityMultiplier = self::getComplexityMultiplier($analysis);

        // Deterministic adjustments
        $baseMultiplier = 1.0; // Could be pulled from settings if passed

        $adjustedMaterialsCost = $materialsCost * $baseMultiplier;
        $adjustedLaborCost = $laborCost * $baseMultiplier;
        $adjustedEnergyCost = $energyCost * $baseMultiplier;
        $adjustedEquipmentCost = $equipmentCost * $baseMultiplier;

        $materialsFinal = $adjustedMaterialsCost * $complexityMultiplier;
        $laborFinal = $adjustedLaborCost * $complexityMultiplier;
        $energyFinal = $adjustedEnergyCost * $complexityMultiplier;
        $equipmentFinal = $adjustedEquipmentCost * $complexityMultiplier;
        $totalCost = $materialsFinal + $laborFinal + $energyFinal + $equipmentFinal;

        $marketAverage = self::getMarketAverageCost($category, $pdo);
        $marketScale = 1.0;
        if ($marketAverage > 0 && $totalCost > 0) {
            $marketScale = $marketAverage / $totalCost;
            $materialsFinal *= $marketScale;
            $laborFinal *= $marketScale;
            $energyFinal *= $marketScale;
            $equipmentFinal *= $marketScale;
            $totalCost = $marketAverage;
        }

        return [
            'cost' => round($totalCost, 2),
            'reasoning' => self::generateReasoning($materialsCost, $laborCost, $energyCost, $equipmentCost, $complexityMultiplier, $analysis, $marketAverage, $marketScale),
            'confidence' => self::determineConfidence($analysis, $category),
            'breakdown' => [
                WF_Constants::COST_CATEGORY_MATERIALS => round($materialsFinal, 2),
                WF_Constants::COST_CATEGORY_LABOR => round($laborFinal, 2),
                WF_Constants::COST_CATEGORY_ENERGY => round($energyFinal, 2),
                WF_Constants::COST_CATEGORY_EQUIPMENT => round($equipmentFinal, 2),
                'complexity_multiplier' => $complexityMultiplier,
                'base_total' => round($adjustedMaterialsCost + $adjustedLaborCost + $adjustedEnergyCost + $adjustedEquipmentCost, 2),
                'final_total' => round($totalCost, 2),
                'market_average_cost' => $marketAverage > 0 ? round($marketAverage, 2) : null,
                'market_scale' => $marketAverage > 0 ? round($marketScale, 4) : null
            ],
            'analysis' => $analysis
        ];
    }

    private static function pickCategory($category, $name, $description)
    {
        $c = trim((string) $category);
        if ($c !== '')
            return $c;
        $text = strtolower(trim((string) ($name . ' ' . $description)));
        if ($text === '')
            return $c;
        if (preg_match('/\b(tumbler|mug|cup|drinkware|insulated)\b/i', $text))
            return 'Tumblers';
        if (preg_match('/\b(t\s*-?\s*shirt|tee|shirt|hoodie|sweatshirt|apparel)\b/i', $text))
            return 'T-Shirts';
        if (preg_match('/\b(canvas|print|poster|wall\s+art|artwork)\b/i', $text))
            return 'Artwork';
        if (preg_match('/\b(sublimation|sublimated|dye\s+sublimation)\b/i', $text))
            return 'Sublimation';
        if (preg_match('/\b(window\s+wrap|wrap|decal|vinyl|sticker|graphic)\b/i', $text))
            return 'Window Wraps';
        return $c;
    }

    private static function analyzeItemEnhanced($name, $description, $category)
    {
        $text = strtolower($name . ' ' . $description);
        $materials = self::detectMaterials($text);
        $features = self::detectFeatures($text);
        $size = self::detectSize($text);

        return [
            WF_Constants::COST_CATEGORY_MATERIALS => $materials,
            'features' => $features,
            'size' => $size,
            'text_length' => strlen($text),
            'has_description' => !empty(trim($description)),
            'complexity_score' => self::calculateComplexityScore($features),
            'production_time_estimate' => self::estimateTime($category, $features),
            'skill_level_required' => self::assessSkill($features),
            'market_positioning' => WF_Constants::QUALITY_TIER_STANDARD,
            'eco_friendly_score' => 0.5,
            'material_cost_factors' => [],
            'labor_complexity_factors' => [],
            'energy_usage_factors' => [],
            'equipment_requirements' => [],
            'material_confidence' => 0.8,
            'labor_confidence' => 0.7,
            'energy_confidence' => 0.7,
            'equipment_confidence' => 0.8,
            'size_analysis' => ['size' => $size]
        ];
    }

    private static function detectMaterials($text)
    {
        $materials = [];
        $map = [
            'cotton' => ['cotton', 'organic cotton'],
            'canvas' => ['canvas'],
            'stainless_steel' => ['stainless steel', 'steel'],
            'ceramic' => ['ceramic', 'porcelain'],
            'glass' => ['glass'],
            'wood' => ['wood', 'bamboo'],
            'vinyl' => ['vinyl', 'htv']
        ];
        foreach ($map as $m => $keys) {
            foreach ($keys as $k) {
                if (strpos($text, $k) !== false) {
                    $materials[] = $m;
                    break;
                }
            }
        }
        return $materials;
    }

    private static function detectFeatures($text)
    {
        $features = [];
        $map = [
            'custom' => ['custom', 'personalized'],
            'handmade' => ['handmade', 'hand-crafted'],
            'premium' => ['premium', 'luxury'],
            'sublimation' => ['sublimation'],
            'engraved' => ['engraved', 'laser'],
            'detailed' => ['detailed', 'intricate']
        ];
        foreach ($map as $f => $keys) {
            foreach ($keys as $k) {
                if (strpos($text, $k) !== false) {
                    $features[] = $f;
                    break;
                }
            }
        }
        return $features;
    }

    private static function detectSize($text)
    {
        if (preg_match('/\b(small|mini)\b/i', $text))
            return WF_Constants::SIZE_SMALL;
        if (preg_match('/\b(large|big|jumbo)\b/i', $text))
            return WF_Constants::SIZE_LARGE;
        if (preg_match('/\b(extra large|xl)\b/i', $text))
            return WF_Constants::SIZE_EXTRA_LARGE;
        return WF_Constants::SIZE_STANDARD;
    }

    private static function getCategoryBaseCosts($category)
    {
        $costs = [
            'T-Shirts' => [WF_Constants::COST_CATEGORY_MATERIALS => 7.50, WF_Constants::COST_CATEGORY_LABOR => 8.00, WF_Constants::COST_CATEGORY_ENERGY => 1.00, WF_Constants::COST_CATEGORY_EQUIPMENT => 1.50],
            'Tumblers' => [WF_Constants::COST_CATEGORY_MATERIALS => 12.00, WF_Constants::COST_CATEGORY_LABOR => 10.00, WF_Constants::COST_CATEGORY_ENERGY => 2.00, WF_Constants::COST_CATEGORY_EQUIPMENT => 2.50],
            'Artwork' => [WF_Constants::COST_CATEGORY_MATERIALS => 18.00, WF_Constants::COST_CATEGORY_LABOR => 25.00, WF_Constants::COST_CATEGORY_ENERGY => 4.00, WF_Constants::COST_CATEGORY_EQUIPMENT => 5.00],
            'Sublimation' => [WF_Constants::COST_CATEGORY_MATERIALS => 8.00, WF_Constants::COST_CATEGORY_LABOR => 12.00, WF_Constants::COST_CATEGORY_ENERGY => 2.50, WF_Constants::COST_CATEGORY_EQUIPMENT => 3.00],
            'Window Wraps' => [WF_Constants::COST_CATEGORY_MATERIALS => 15.00, WF_Constants::COST_CATEGORY_LABOR => 20.00, WF_Constants::COST_CATEGORY_ENERGY => 1.50, WF_Constants::COST_CATEGORY_EQUIPMENT => 2.00],
        ];
        return $costs[$category] ?? [WF_Constants::COST_CATEGORY_MATERIALS => 10.00, WF_Constants::COST_CATEGORY_LABOR => 15.00, WF_Constants::COST_CATEGORY_ENERGY => 2.00, WF_Constants::COST_CATEGORY_EQUIPMENT => 2.50];
    }

    private static function getMarketAverageCost($category, $pdo)
    {
        try {
            $row = Database::queryOne(
                "SELECT AVG(cost_price) AS avg_cost FROM items WHERE category = ? AND cost_price > 0",
                [$category]
            );
            if ($row && isset($row['avg_cost'])) {
                return (float) $row['avg_cost'];
            }
        } catch (Exception $e) {
            // Best-effort only
        }
        return 0.0;
    }

    private static function calculateMaterialsCost($analysis, $baseCosts, $category)
    {
        $cost = $baseCosts[WF_Constants::COST_CATEGORY_MATERIALS];
        if (in_array('premium', $analysis['features']))
            $cost *= 1.4;
        if ($analysis['size'] === WF_Constants::SIZE_SMALL)
            $cost *= 0.8;
        if ($analysis['size'] === WF_Constants::SIZE_LARGE)
            $cost *= 1.3;
        if ($analysis['size'] === WF_Constants::SIZE_EXTRA_LARGE)
            $cost *= 1.6;
        return $cost;
    }

    private static function calculateLaborCost($analysis, $baseCosts, $category)
    {
        $cost = $baseCosts[WF_Constants::COST_CATEGORY_LABOR];
        if (in_array('custom', $analysis['features']))
            $cost *= 1.5;
        if (in_array('handmade', $analysis['features']))
            $cost *= 1.6;
        return $cost;
    }

    private static function calculateEnergyCost($analysis, $baseCosts, $category)
    {
        $cost = $baseCosts[WF_Constants::COST_CATEGORY_ENERGY];
        if (in_array('sublimation', $analysis['features']))
            $cost *= 1.4;
        return $cost;
    }

    private static function calculateEquipmentCost($analysis, $baseCosts, $category)
    {
        $cost = $baseCosts[WF_Constants::COST_CATEGORY_EQUIPMENT];
        if (in_array('engraved', $analysis['features']))
            $cost *= 1.4;
        return $cost;
    }

    private static function getComplexityMultiplier($analysis)
    {
        $m = 1.0;
        $m += count($analysis['features']) * 0.1;
        return min($m, 2.5);
    }

    private static function generateReasoning($m, $l, $e, $eq, $mult, $analysis, $marketAverage = 0.0, $marketScale = 1.0)
    {
        $res = "Base: Mat $" . number_format($m, 2) . ", Labor $" . number_format($l, 2);
        if ($mult > 1.0)
            $res .= " (Complexity: +" . (($mult - 1) * 100) . "%)";
        if ($marketAverage > 0) {
            $res .= " • Anchor: Market average $" . number_format($marketAverage, 2);
            if (abs($marketScale - 1.0) > 0.01) {
                $res .= " (scale " . number_format($marketScale, 2) . "x)";
            }
        }
        return $res;
    }

    private static function determineConfidence($analysis, $category)
    {
        // Aim for > 80% (0.8) when we have both category and materials
        if (!empty($category) && count($analysis['materials'] ?? []) > 0) {
            return 0.92;
        }
        if (!empty($category) || count($analysis['materials'] ?? []) > 0) {
            return 0.78;
        }
        return 0.55;
    }

    private static function calculateComplexityScore($features)
    {
        return count($features) / 5.0;
    }
    private static function estimateTime($cat, $features)
    {
        return 60;
    }
    private static function assessSkill($features)
    {
        return WF_Constants::SKILL_INTERMEDIATE;
    }
}
