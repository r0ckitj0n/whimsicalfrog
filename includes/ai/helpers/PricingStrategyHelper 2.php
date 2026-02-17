<?php
/**
 * includes/ai/helpers/PricingStrategyHelper.php
 * Strategy and confidence logic for pricing heuristics
 */

class PricingStrategyHelper
{
    public static function calculateComplexity($materials, $features, $sizeAnalysis, $category)
    {
        $complexity = 0.5;
        foreach ($materials as $material) {
            if ($material['market_appeal'] === 'premium')
                $complexity += 0.2;
        }
        foreach ($features as $feature) {
            if ($feature['market_demand'] === 'premium')
                $complexity += 0.3;
            elseif ($feature['market_demand'] === 'high')
                $complexity += 0.2;
        }
        $categoryComplexity = [
            'T-Shirts' => 0.3,
            'Tumblers' => 0.4,
            'Artwork' => 0.8,
            'Sublimation' => 0.6,
            'Window Wraps' => 0.7
        ];
        $complexity += $categoryComplexity[$category] ?? 0.5;
        return min(2.0, max(0.1, $complexity));
    }

    public static function calculateConfidence($cost_price, $marketPrice, $competitivePrice, $valuePrice)
    {
        // Aim for > 80% (0.8) for better user perception as requested
        $confidence = ['market' => 0.75, 'competitive' => 0.65, 'value' => 0.7, 'pricing' => 0.75];
        if ($cost_price > 0)
            $confidence['pricing'] = 0.94;
        if ($marketPrice > 0)
            $confidence['market'] = 0.92;
        if ($competitivePrice > 0)
            $confidence['competitive'] = 0.88;
        if ($valuePrice > 0 && $valuePrice < 1000)
            $confidence['value'] = 0.85;
        return $confidence;
    }

    public static function applyPsychologicalPricing($price)
    {
        if ($price >= 20)
            return floor($price) + 0.99;
        if ($price >= 10)
            return floor($price) + 0.95;
        if ($price < 5)
            return round($price * 2) / 2;
        return floor($price) + 0.99;
    }

    public static function validateRange($price, $cost_price)
    {
        if ($cost_price > 0) {
            return max($cost_price * 1.5, min($cost_price * 8.0, $price));
        }
        return max(1.00, $price);
    }
}
