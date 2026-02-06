<?php
/**
 * includes/ai/helpers/PricingHeuristics.php
 * Centralized pricing heuristics logic
 */

require_once __DIR__ . '/PricingAnalysisHelper.php';
require_once __DIR__ . '/PricingStrategyHelper.php';

class PricingHeuristics
{
    public static function analyze($name, $description, $category, $cost_price, $pdo)
    {
        $itemAnalysis = self::analyzeItemForPricing($name, $description, $category);
        $pricingStrategies = self::analyzePricingStrategies($name, $description, $category, $cost_price, $itemAnalysis, $pdo);

        $pricingComponents = [];
        $baseMultiplier = 1.0;

        $costPlusPrice = $pricingStrategies['cost_plus_price'] * $baseMultiplier;
        $basePrice = $costPlusPrice;
        $factors = ['cost_plus' => $costPlusPrice];
        $pricingComponents['cost_plus'] = ['amount' => $costPlusPrice, 'label' => 'Cost-plus pricing', 'explanation' => 'Base pricing using cost multiplier analysis'];

        $marketPrice = $pricingStrategies['market_research_price'] * $baseMultiplier;
        if ($marketPrice > 0) {
            $factors['market_research'] = $marketPrice;
            $pricingComponents['market_research'] = ['amount' => $marketPrice, 'label' => 'Market research analysis', 'explanation' => 'Competitive market analysis and pricing research'];
            $basePrice = $marketPrice;
        }

        $competitivePrice = $pricingStrategies['competitive_price'];
        if ($competitivePrice > 0) {
            $factors['competitive'] = $competitivePrice;
            $pricingComponents['competitive_analysis'] = ['amount' => $competitivePrice, 'label' => 'Competitive analysis', 'explanation' => 'Analysis of competitor pricing'];
        }

        $valuePrice = $pricingStrategies['value_based_price'] * $baseMultiplier;
        if ($valuePrice > 0) {
            $factors['value_based'] = $valuePrice;
            $pricingComponents['value_based'] = ['amount' => $valuePrice, 'label' => 'Value-based pricing', 'explanation' => 'Perceived customer value'];
        }

        $confidenceObj = $pricingStrategies['pricing_confidence'] ?? [];
        $confidenceValue = 0.5;
        if (is_array($confidenceObj) && !empty($confidenceObj)) {
            $vals = array_filter($confidenceObj, fn($v) => is_numeric($v));
            if (!empty($vals)) {
                $confidenceValue = array_sum($vals) / count($vals);
            }
        } elseif (is_numeric($confidenceObj)) {
            $confidenceValue = floatval($confidenceObj);
        }

        // Default to market-average unless another option has >20pt confidence lead
        $selectedStrategy = null;
        $strategyPrices = [
            'market_research' => $marketPrice,
            'cost_plus' => $costPlusPrice,
            'competitive' => $competitivePrice,
            'value_based' => $valuePrice
        ];
        $confidenceMap = is_array($confidenceObj) ? $confidenceObj : [];
        $marketConfidence = $confidenceMap['market'] ?? 0.0;
        $selectedStrategy = $marketPrice > 0 ? 'market_research' : 'cost_plus';
        $selectedPrice = $strategyPrices[$selectedStrategy] ?? $costPlusPrice;
        $bestConf = $confidenceMap['pricing'] ?? 0.0;
        if ($selectedStrategy === 'market_research') {
            $bestConf = $marketConfidence;
        } elseif ($selectedStrategy === 'value_based') {
            $bestConf = $confidenceMap['value'] ?? 0.0;
        } elseif ($selectedStrategy === 'competitive') {
            $bestConf = $confidenceMap['competitive'] ?? 0.0;
        }

        foreach ($strategyPrices as $key => $price) {
            if ($price <= 0) continue;
            $confKey = $key === 'market_research' ? 'market' : ($key === 'cost_plus' ? 'pricing' : ($key === 'value_based' ? 'value' : 'competitive'));
            $conf = $confidenceMap[$confKey] ?? 0.0;
            if ($marketPrice > 0) {
                if ($conf >= ($marketConfidence + 0.20) && $conf > $bestConf) {
                    $selectedStrategy = $key;
                    $selectedPrice = $price;
                    $bestConf = $conf;
                }
            } else {
                if ($conf > $bestConf) {
                    $selectedStrategy = $key;
                    $selectedPrice = $price;
                    $bestConf = $conf;
                }
            }
        }

        if ($selectedPrice > 0) {
            $basePrice = $selectedPrice;
        }

        $brandPremium = $pricingStrategies['brand_premium_factor'] ?? 1.0;
        if ($brandPremium > 1.0) {
            $pricingComponents['brand_premium'] = ['amount' => $basePrice * ($brandPremium - 1.0), 'label' => 'Brand premium: +' . round(($brandPremium - 1.0) * 100) . '%', 'explanation' => 'Premium pricing based on brand positioning'];
            $basePrice *= $brandPremium;
        }

        $psychPrice = PricingStrategyHelper::applyPsychologicalPricing($basePrice);
        if ($psychPrice != $basePrice) {
            $pricingComponents['psychological_pricing'] = ['amount' => $psychPrice - $basePrice, 'label' => 'Psychological pricing', 'explanation' => 'Optimization using psychological principles'];
            $basePrice = $psychPrice;
        }

        $finalPrice = PricingStrategyHelper::validateRange($basePrice, $cost_price);
        $factors['final'] = $finalPrice;

        $components = [];
        foreach ($pricingComponents as $key => $c) {
            $components[] = ['type' => $key, 'label' => $c['label'], 'amount' => $c['amount'], 'explanation' => $c['explanation']];
        }

        return [
            'price' => $finalPrice,
            'reasoning' => 'Anchor: ' . ($selectedStrategy === 'market_research' ? 'Market research analysis' : ($selectedStrategy === 'cost_plus' ? 'Cost-plus pricing' : ($selectedStrategy === 'competitive' ? 'Competitive analysis' : 'Value-based pricing'))) . ' • ' . implode(' • ', array_map(fn($c) => $c['label'] . ': $' . number_format($c['amount'], 2), $pricingComponents)),
            'confidence' => round($confidenceValue, 2),
            'factors' => $factors,
            'analysis' => array_merge($itemAnalysis, $pricingStrategies, [
                'selected_pricing_anchor' => $selectedStrategy,
                'market_confidence' => $marketConfidence
            ]),
            'components' => $components
        ];
    }

    public static function analyzeItemForPricing($name, $description, $category)
    {
        $text = strtolower($name . ' ' . $description);
        $materials = PricingAnalysisHelper::detectMaterials($text);
        $features = PricingAnalysisHelper::detectFeatures($text);
        $sizeAnalysis = PricingAnalysisHelper::analyzeSize($text, $category);

        return [
            'detected_materials' => $materials,
            'detected_features' => $features,
            'size_analysis' => $sizeAnalysis,
            'complexity_score' => PricingStrategyHelper::calculateComplexity($materials, $features, $sizeAnalysis, $category),
            'market_positioning' => self::analyzeMarketPositioning($text, $features),
            'trend_alignment_score' => 0.7,
            'uniqueness_score' => 0.6,
            'demand_score' => 0.7,
            'market_saturation_level' => 'medium'
        ];
    }

    public static function analyzePricingStrategies($name, $description, $category, $cost_price, $itemAnalysis, $pdo)
    {
        $costPlus = self::calculateCostPlusPrice($cost_price, $category, $itemAnalysis);
        $marketResearch = self::calculateMarketResearchPrice($name, $description, $category, $itemAnalysis);
        $competitive = self::getCompetitivePrice($name, $category, $itemAnalysis);
        $valueBased = self::calculateValueBasedPrice($itemAnalysis, $category);

        return [
            'cost_plus_price' => $costPlus,
            'market_research_price' => $marketResearch,
            'competitive_price' => $competitive,
            'value_based_price' => $valueBased,
            'pricing_confidence' => PricingStrategyHelper::calculateConfidence($cost_price, $marketResearch, $competitive, $valueBased),
            'brand_premium_factor' => ($itemAnalysis['market_positioning'] === 'premium') ? 1.3 : 1.0
        ];
    }

    private static function calculateCostPlusPrice($cost, $cat, $analysis)
    {
        if ($cost <= 0) {
            $costs = ['T-Shirts' => 6.0, 'Tumblers' => 4.5, 'Artwork' => 8.0, 'Sublimation' => 5.5, 'Window Wraps' => 12.0];
            $cost = $costs[$cat] ?? 7.0;
        }
        $markup = 2.5 * (1 + (($analysis['complexity_score'] ?? 0.5) - 0.5) * 0.5);
        return $cost * $markup;
    }

    private static function calculateMarketResearchPrice($name, $desc, $cat, $analysis)
    {
        $ranges = ['T-Shirts' => [12, 35], 'Tumblers' => [8, 28], 'Artwork' => [15, 75], 'Sublimation' => [10, 45], 'Window Wraps' => [25, 80]];
        $r = $ranges[$cat] ?? [10, 40];
        $price = (($r[0] + $r[1]) / 2) * (stripos($name . ' ' . $desc, 'custom') !== false ? 1.3 : 1.0);
        $price = max($r[0], min($r[1], $price));
        foreach ($analysis['detected_materials'] as $m)
            $price *= $m['price_premium'];
        foreach ($analysis['detected_features'] as $f)
            $price *= $f['price_impact'];
        return $price;
    }

    private static function getCompetitivePrice($name, $cat, $analysis)
    {
        try {
            $rows = Database::queryAll("SELECT retail_price FROM items WHERE category = ? AND name != ? AND retail_price > 0", [$cat, $name]);
            if ($prices = array_filter(array_map(fn($r) => (float) current($r), $rows))) {
                $avg = array_sum($prices) / count($prices);
                return ($analysis['market_positioning'] === 'premium') ? $avg * 1.3 : (($analysis['market_positioning'] === 'budget') ? $avg * 0.8 : $avg);
            }
        } catch (Exception $e) {
        }
        return 0;
    }

    private static function calculateValueBasedPrice($analysis, $cat)
    {
        $values = ['T-Shirts' => 20.0, 'Tumblers' => 15.0, 'Artwork' => 40.0, 'Sublimation' => 25.0, 'Window Wraps' => 50.0];
        $base = $values[$cat] ?? 25.0;
        foreach ($analysis['detected_features'] as $f)
            $base *= ($f['market_demand'] === 'premium' ? 1.5 : ($f['market_demand'] === 'high' ? 1.3 : 1.0));
        return $base * (1 + ($analysis['uniqueness_score'] ?? 0.5));
    }

    private static function analyzeMarketPositioning($text, $features)
    {
        $premium = ['luxury', 'premium', 'high-end', 'artisan', 'custom', 'bespoke'];
        $budget = ['basic', 'simple', 'standard', 'economy', 'budget'];
        $pScore = 0;
        $bScore = 0;
        foreach ($premium as $kw)
            if (strpos($text, $kw) !== false)
                $pScore++;
        foreach ($budget as $kw)
            if (strpos($text, $kw) !== false)
                $bScore++;
        foreach ($features as $f)
            if (in_array($f['type'] ?? '', ['custom_design', 'handmade', 'luxury']))
                $pScore++;
        return ($pScore > $bScore) ? 'premium' : (($bScore > 0) ? 'budget' : 'standard');
    }
}
