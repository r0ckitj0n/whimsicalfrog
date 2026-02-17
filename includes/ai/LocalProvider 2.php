<?php
// includes/ai/LocalProvider.php

require_once __DIR__ . '/../Constants.php';
require_once __DIR__ . '/BaseProvider.php';
require_once __DIR__ . '/helpers/MarketingHeuristics.php';
require_once __DIR__ . '/helpers/CostHeuristics.php';
require_once __DIR__ . '/helpers/PricingHeuristics.php';

class LocalProvider extends BaseProvider
{
    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone)
    {
        if (class_exists('MarketingHeuristics')) {
            return MarketingHeuristics::generateIntelligence($name, $description, $category, $this->getPDO(), $brandVoice, $contentTone);
        }
        return $this->generateBasicFallback($name, $category);
    }

    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null)
    {
        if (class_exists('MarketingHeuristics')) {
            return MarketingHeuristics::generateIntelligence($name, $description, $category, $this->getPDO(), $brandVoice, $contentTone, $existingMarketingData);
        }
        $desc = $description . ($imageInsights ? "\n\nVisual Insights: " . $imageInsights : "");
        return $this->generateMarketing($name, $desc, $category, $brandVoice, $contentTone);
    }

    public function generateCost($name, $description, $category)
    {
        if (class_exists('CostHeuristics')) {
            return CostHeuristics::analyze($name, $description, $category, $this->getPDO());
        }

        // Simple heuristic fallback if class missing
        $cat = strtoupper((string) $category);
        $cost = 5.00;
        if (strpos($cat, 'TUMBLER') !== false)
            $cost = 8.50;
        elseif (strpos($cat, 'SHIRT') !== false)
            $cost = 6.00;
        elseif (strpos($cat, 'ART') !== false)
            $cost = 12.00;

        return [
            'cost' => $cost,
            'reasoning' => 'Estimated based on category baseline (local fallback)'
        ];
    }

    public function generatePricing($name, $description, $category, $cost_price)
    {
        if (class_exists('PricingHeuristics')) {
            return PricingHeuristics::analyze($name, $description, $category, $cost_price, $this->getPDO());
        }

        // Basic fallback
        $cost = (float) $cost_price;
        $price = $cost > 0 ? $cost * 2.5 : 20.00;

        return [
            'price' => round($price, 2),
            'reasoning' => 'Standard local markup applied',
            'confidence' => WF_Constants::CONFIDENCE_MEDIUM,
            'factors' => ['cost' => $cost],
            'components' => [
                [
                    'type' => 'cost_plus',
                    'label' => 'Standard Markup',
                    'amount' => $price,
                    'explanation' => '2.5x cost multiplier'
                ]
            ],
            'analysis' => []
        ];
    }

    public function generateDimensions($name, $description, $category)
    {
        $cat = strtoupper((string) $category);
        if (strpos($cat, 'TUMBLER') !== false) {
            return ['weight_oz' => 12.0, 'dimensions_in' => ['length' => 10.0, 'width' => 4.0, 'height' => 4.0]];
        }
        if (strpos($cat, 'SHIRT') !== false || strpos($cat, 'TEE') !== false || strpos($cat, 'T-SHIRT') !== false || strpos($cat, 'TS') !== false) {
            return ['weight_oz' => 5.0, 'dimensions_in' => ['length' => 10.0, 'width' => 8.0, 'height' => 1.0]];
        }
        if (strpos($cat, 'ART') !== false) {
            return ['weight_oz' => 16.0, 'dimensions_in' => ['length' => 12.0, 'width' => 9.0, 'height' => 2.0]];
        }
        if (strpos($cat, 'WRAP') !== false) {
            return ['weight_oz' => 10.0, 'dimensions_in' => ['length' => 12.0, 'width' => 3.0, 'height' => 3.0]];
        }
        return ['weight_oz' => 8.0, 'dimensions_in' => ['length' => 8.0, 'width' => 6.0, 'height' => 4.0]];
    }

    public function generateReceipt($prompt)
    {
        return [
            'title' => 'Order Confirmed',
            'content' => "Your order is being processed with care. You'll receive updates as your custom items are prepared and ready."
        ];
    }

    private function pickLocalCategory($category, $name, $description)
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

    private function analyzeLocalPricingSignals($name, $description, $category)
    {
        $text = strtolower(trim((string) ($name . ' ' . $description)));
        $materials = [];
        $features = [];
        $materialMap = [
            'cotton' => ['cotton', 'pima', 'supima'],
            'vinyl' => ['vinyl', 'htv'],
            'stainless_steel' => ['stainless', 'steel', 'metal'],
            'canvas' => ['canvas'],
            'ceramic' => ['ceramic'],
            'glass' => ['glass'],
            'wood' => ['wood', 'bamboo'],
        ];
        foreach ($materialMap as $mat => $keys) {
            foreach ($keys as $k) {
                if (strpos($text, $k) !== false) {
                    $materials[] = $mat;
                    break;
                }
            }
        }
        $featureMap = [
            'custom' => ['custom', 'personalized', 'name', 'monogram'],
            'handmade' => ['handmade', 'hand crafted', 'artisan'],
            'premium' => ['premium', 'deluxe', 'luxury'],
            'insulated' => ['insulated', 'double wall'],
            'full_color' => ['full color', 'multicolor'],
            'business' => ['business', 'logo', 'storefront'],
        ];
        foreach ($featureMap as $feat => $keys) {
            foreach ($keys as $k) {
                if (strpos($text, $k) !== false) {
                    $features[] = $feat;
                    break;
                }
            }
        }
        return [
            WF_Constants::COST_CATEGORY_MATERIALS => array_values(array_unique($materials)),
            'features' => array_values(array_unique($features)),
            'market_positioning' => in_array('premium', $features) ? WF_Constants::QUALITY_TIER_PREMIUM : WF_Constants::QUALITY_TIER_STANDARD,
        ];
    }

    private function getLocalCategoryMarkup($category)
    {
        $markups = ['T-Shirts' => 2.5, 'Tumblers' => 2.8, 'Artwork' => 4.0, 'Sublimation' => 3.2, 'Window Wraps' => 3.5, 'default' => 2.5];
        return (float) ($markups[$category] ?? $markups['default']);
    }

    private function getLocalCategoryBasePrice($category)
    {
        $prices = ['T-Shirts' => 19.99, 'Tumblers' => 16.99, 'Artwork' => 29.99, 'Sublimation' => 24.99, 'Window Wraps' => 39.99, 'default' => 19.99];
        return (float) ($prices[$category] ?? $prices['default']);
    }

    private function getLocalMarketBaseline($category, $signals)
    {
        $ranges = [
            'T-Shirts' => ['min' => 15.0, 'max' => 35.0],
            'Tumblers' => ['min' => 18.0, 'max' => 45.0],
            'Artwork' => ['min' => 30.0, 'max' => 120.0],
            'Sublimation' => ['min' => 20.0, 'max' => 75.0],
            'Window Wraps' => ['min' => 40.0, 'max' => 150.0],
            'default' => ['min' => 20.0, 'max' => 60.0],
        ];
        $range = $ranges[$category] ?? $ranges['default'];
        $modifier = 1.0;
        $features = $signals['features'] ?? [];
        if (in_array('premium', $features) || in_array('handmade', $features))
            $modifier += 0.25;
        if (in_array('custom', $features))
            $modifier += 0.15;
        $price = (($range['min'] + $range['max']) / 2.0) * $modifier;
        return ['price' => max($range['min'], min($range['max'], $price)), 'note' => 'Category range adjusted by features'];
    }

    private function getLocalCompetitiveBaseline($category, $name, $cost_price)
    {
        try {
            $rows = Database::queryAll("SELECT retail_price FROM items WHERE category = ? AND name != ? AND retail_price > 0 ORDER BY retail_price", [$category, $name]);
            $prices = array_filter(array_map(fn($r) => (float) current($r), $rows), fn($p) => $p > 0);
            $count = count($prices);
            if ($count > 0) {
                $median = $prices[(int) floor($count / 2)];
                return ['count' => $count, 'median' => $median, 'suggested' => $cost_price > 0 ? max($median, $cost_price * 1.5) : $median];
            }
        } catch (Exception $e) {
        }
        return ['count' => 0];
    }

    private function getLocalValueBaseline($category, $signals)
    {
        $base = $this->getLocalCategoryBasePrice($category);
        $mult = 1.0;
        $features = $signals['features'] ?? [];
        if (in_array('custom', $features))
            $mult += 0.2;
        if (in_array('handmade', $features))
            $mult += 0.25;
        if (in_array('premium', $features))
            $mult += 0.2;
        return $base * $mult;
    }

    private function getLocalBrandPremiumFactor($signals)
    {
        $features = $signals['features'] ?? [];
        $premium = 1.0;
        if (in_array('premium', $features))
            $premium = max($premium, 1.2);
        if (in_array('handmade', $features))
            $premium = max($premium, 1.15);
        if (in_array('custom', $features))
            $premium = max($premium, 1.1);
        return $premium;
    }

    private function applyLocalPsychologicalPricing($price)
    {
        if ($price <= 0)
            return $price;
        if ($price >= 20)
            return floor($price) + 0.99;
        if ($price >= 10)
            return floor($price) + 0.95;
        return floor($price) + 0.99;
    }

    private function validateLocalPriceRange($price, $cost_price)
    {
        if ($cost_price > 0) {
            return max($cost_price * 1.5, min($cost_price * 8.0, $price));
        }
        return max(1.0, $price);
    }

    private function scoreLocalPricingConfidence($cost_price, $competitiveStats)
    {
        $hasCost = ($cost_price > 0);
        $compCount = ($competitiveStats['count'] ?? 0);
        if ($hasCost && $compCount >= 5)
            return WF_Constants::CONFIDENCE_HIGH;
        if ($hasCost || $compCount >= 5)
            return WF_Constants::CONFIDENCE_MEDIUM;
        return WF_Constants::CONFIDENCE_LOW;
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        return null; // Expressly not supported, let the caller handle it
    }

    public function generateAltText($images, $name, $description, $category)
    {
        $results = [];
        foreach ((array) $images as $img) {
            $results[] = [
                'alt_text' => "Item image of {$name} in category {$category}",
                'description' => "Placeholder alt text for {$name}"
            ];
        }
        return $results;
    }

    private function getPDO()
    {
        try {
            return Database::getInstance();
        } catch (Exception $e) {
            return null;
        }
    }

    private function generateBasicFallback($name, $category)
    {
        return [
            'title' => $name,
            'description' => "Custom crafted {$name} from our {$category} collection.",
            'keywords' => explode(' ', $name),
            'selling_points' => ['Handmade quality', 'Customized for you']
        ];
    }

    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone)
    {
        return $this->generateMarketing($name, $description, $category, $brandVoice, $contentTone);
    }

    public function generateCostWithImages($name, $description, $category, $images)
    {
        return $this->generateCost($name, $description, $category);
    }

    public function generatePricingWithImages($name, $description, $category, $cost_price, $images)
    {
        return $this->generatePricing($name, $description, $category, $cost_price);
    }

    public function detectObjectBoundaries($imagePath)
    {
        if (class_exists('GDImageHelper')) {
            $edges = GDImageHelper::detectEdges($imagePath);
            if ($edges)
                return $edges;
        }

        if (class_exists('VisionHeuristics')) {
            return VisionHeuristics::getFallbackCropBounds($imagePath);
        }

        return [
            'left' => 0.05,
            'top' => 0.05,
            'right' => 0.95,
            'bottom' => 0.95,
            'confidence' => 0.3,
            'description' => 'Static fallback trim'
        ];
    }

    public function getModels()
    {
        return [
            ['id' => 'jons-ai', 'name' => "Jon's AI Algorithm", 'description' => "Jon's built-in AI system"]
        ];
    }

    public function supportsImages(): bool
    {
        return false;
    }
}
