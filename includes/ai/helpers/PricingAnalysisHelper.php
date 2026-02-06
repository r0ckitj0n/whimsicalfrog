<?php
/**
 * includes/ai/helpers/PricingAnalysisHelper.php
 * Detection and analysis logic for pricing heuristics
 */

class PricingAnalysisHelper {
    public static function detectMaterials($text) {
        $materials = [];
        $materialPricingDatabase = [
            'organic_cotton' => ['price_premium' => 1.4, 'market_appeal' => 'high'],
            'cotton' => ['price_premium' => 1.0, 'market_appeal' => 'standard'],
            'polyester' => ['price_premium' => 0.8, 'market_appeal' => 'budget'],
            'vinyl' => ['price_premium' => 1.1, 'market_appeal' => 'standard'],
            'canvas' => ['price_premium' => 1.3, 'market_appeal' => 'premium'],
            'stainless_steel' => ['price_premium' => 1.8, 'market_appeal' => 'premium'],
            'ceramic' => ['price_premium' => 1.2, 'market_appeal' => 'standard'],
            'glass' => ['price_premium' => 1.5, 'market_appeal' => 'premium']
        ];

        foreach ($materialPricingDatabase as $material => $data) {
            if (strpos($text, str_replace('_', ' ', $material)) !== false) {
                $materials[] = ['type' => $material, 'price_premium' => $data['price_premium'], 'market_appeal' => $data['market_appeal']];
            }
        }
        return $materials;
    }

    public static function detectFeatures($text) {
        $features = [];
        $featurePricingDatabase = [
            'custom_design' => ['price_impact' => 1.5, 'market_demand' => 'high'],
            'personalized' => ['price_impact' => 1.4, 'market_demand' => 'high'],
            'handmade' => ['price_impact' => 1.8, 'market_demand' => 'premium'],
            'limited_edition' => ['price_impact' => 1.6, 'market_demand' => 'high'],
            'premium' => ['price_impact' => 1.3, 'market_demand' => 'medium'],
            'luxury' => ['price_impact' => 2.0, 'market_demand' => 'premium'],
            'eco_friendly' => ['price_impact' => 1.3, 'market_demand' => 'growing'],
            'vintage' => ['price_impact' => 1.4, 'market_demand' => 'niche']
        ];

        foreach ($featurePricingDatabase as $feature => $data) {
            $keywords = explode('_', $feature);
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $features[] = ['type' => $feature, 'price_impact' => $data['price_impact'], 'market_demand' => $data['market_demand']];
                    break;
                }
            }
        }
        return $features;
    }

    public static function analyzeSize($text, $category) {
        $sizeAnalysis = ['detected_size' => 'standard', 'price_multiplier' => 1.0, 'market_segment' => 'mainstream'];
        $sizePricingPatterns = [
            'small' => ['multiplier' => 0.8, 'segment' => 'budget'],
            'standard' => ['multiplier' => 1.0, 'segment' => 'mainstream'],
            'large' => ['multiplier' => 1.3, 'segment' => 'premium'],
            'extra_large' => ['multiplier' => 1.6, 'segment' => 'premium']
        ];

        foreach ($sizePricingPatterns as $size => $data) {
            if (strpos($text, $size) !== false || strpos($text, str_replace('_', ' ', $size)) !== false) {
                $sizeAnalysis['detected_size'] = $size;
                $sizeAnalysis['price_multiplier'] = $data['multiplier'];
                $sizeAnalysis['market_segment'] = $data['segment'];
                break;
            }
        }
        return $sizeAnalysis;
    }
}
