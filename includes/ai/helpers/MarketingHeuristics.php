<?php
/**
 * Marketing Heuristics - Centralized AI logic for item marketing.
 * Refactored to stay below 500 lines by using specialized helpers.
 */

require_once __DIR__ . '/../../Constants.php';
require_once __DIR__ . '/marketing/Detector.php';
require_once __DIR__ . '/marketing/DescriptionGenerator.php';
require_once __DIR__ . '/marketing/Targeting.php';

class MarketingHeuristics
{
    public static function generateIntelligence($name, $description, $category, $pdo, $preferredBrandVoice = '', $preferredContentTone = '', $existingMarketingData = null)
    {
        $category = self::pickCategory($category, $name, $description);
        $pricingContext = self::getPricingContext($category, $name);

        $analysis = self::analyzeItem($name, $description, $category);
        $analysis['pricing_context'] = $pricingContext;

        $brandVoice = !empty($preferredBrandVoice) ? $preferredBrandVoice : self::determineBrandVoice($category, $analysis);
        $contentTone = !empty($preferredContentTone) ? $preferredContentTone : self::determineContentTone($category, $analysis);

        $currentTitle = !empty($name) ? $name : ($existingMarketingData['suggested_title'] ?? '');
        $title = self::generateEnhancedTitle($currentTitle, $category, $analysis, $brandVoice, $contentTone, $existingMarketingData);

        $currentDescription = !empty($description) ? $description : ($existingMarketingData['suggested_description'] ?? '');
        $enhancedDescription = MarketingDescriptionGenerator::generate($currentTitle, $currentDescription, $category, $analysis, $brandVoice, $contentTone, $existingMarketingData);

        return [
            'title' => $title,
            'description' => $enhancedDescription,
            'keywords' => self::generateSEOKeywords($name, $category, $analysis),
            'target_audience' => MarketingTargeting::identifyTargetAudience($category, $analysis),
            'emotional_triggers' => MarketingTargeting::identifyEmotionalTriggers($category, $analysis),
            'psychographic_profile' => MarketingTargeting::generatePsychographicProfile($category, $analysis),
            'demographic_targeting' => MarketingTargeting::generateDemographicTargeting($category, $analysis),
            'selling_points' => self::generateSellingPoints($name, $category, $analysis, $brandVoice),
            'market_positioning' => self::determineMarketPositioning($category, $analysis),
            'competitive_advantages' => self::identifyCompetitiveAdvantages($category, $analysis),
            'brand_voice' => $brandVoice,
            'content_tone' => $contentTone,
            'customer_benefits' => self::generateCustomerBenefits($category, $analysis),
            'call_to_action_suggestions' => self::generateCallsToAction($category),
            'urgency_factors' => self::generateUrgencyFactors($category, $analysis),
            'conversion_triggers' => self::generateConversionTriggers($category, $analysis),
            'marketing_channels' => self::recommendMarketingChannels($category, $analysis),
            'seo_keywords' => self::generateSEOKeywords($name, $category, $analysis),
            'search_intent' => self::determineSearchIntent($category, $analysis),
            'seasonal_relevance' => self::determineSeasonalRelevance($name, $category),
            'unique_selling_points' => self::generateUniqueSellingPoints($name, $category, $analysis),
            'value_propositions' => self::generateValuePropositions($category, $analysis),
            'pricing_psychology' => self::generatePricingPsychology($category),
            'social_proof_elements' => self::generateSocialProof($category),
            'objection_handlers' => self::generateObjectionHandlers($category),
            'content_themes' => self::generateContentThemes($category, $analysis),
            'pain_points_addressed' => self::generatePainPoints($category, $analysis),
            'lifestyle_alignment' => self::generateLifestyleAlignment($category),
            'market_trends' => self::generateMarketTrends($category),
            'confidence_score' => self::calculateConfidence($analysis, $category),
            'analysis_factors' => $analysis,
            'recommendation_reasoning' => self::generateRecommendationReasoning($category, $analysis)
        ];
    }

    public static function analyzeItem($name, $description, $category)
    {
        $text = strtolower($name . ' ' . $description);
        return [
            WF_Constants::COST_CATEGORY_MATERIALS => MarketingDetector::detectMaterials($text),
            'features' => MarketingDetector::detectFeatures($text),
            'style' => MarketingDetector::detectStyle($text),
            'quality_indicators' => MarketingDetector::detectQualityIndicators($text),
            'use_cases' => self::detectUseCases($text, $category),
            'target_demographics' => MarketingTargeting::generateDemographicTargeting($category, []),
            'premium_indicators' => MarketingDetector::detectQualityIndicators($text),
            'gift_potential' => self::assessGiftPotential($text, $category)
        ];
    }

    public static function pickCategory($category, $name, $description)
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
        return $c;
    }

    public static function getPricingContext($category, $name)
    {
        $resolvedCategory = trim((string) $category);
        $n = trim((string) $name);
        $competitive = ['count' => 0];
        try {
            if ($resolvedCategory !== '') {
                $rows = Database::queryAll("SELECT retail_price FROM items WHERE category = ? AND name != ? AND retail_price > 0 ORDER BY retail_price", [$resolvedCategory, $n]);
                $prices = array_filter(array_map(fn($r) => (float) current($r), $rows), fn($p) => $p > 0);
                if (($count = count($prices)) > 0) {
                    $competitive = ['count' => $count, 'median' => $prices[(int) floor($count / 2)]];
                }
            }
        } catch (Exception $e) {
        }
        return ['category' => $resolvedCategory, 'competitive' => $competitive, 'tier' => WF_Constants::QUALITY_TIER_STANDARD];
    }

    public static function generateEnhancedTitle($name, $category, $analysis, $brandVoice = '', $contentTone = '', $existingMarketingData = null)
    {
        $enhancers = ['Premium', 'Quality'];
        if ($brandVoice === 'playful')
            array_unshift($enhancers, 'Fun');
        return implode(' ', array_slice($enhancers, 0, 1)) . ' ' . $name;
    }

    public static function generateSEOKeywords($name, $category, $analysis)
    {
        return array_unique(array_merge([strtolower($name), strtolower($category)], $analysis[WF_Constants::COST_CATEGORY_MATERIALS] ?? []));
    }

    public static function generateSellingPoints($name, $category, $analysis, $brandVoice = '')
    {
        return ['High quality materials', 'Satisfaction guaranteed'];
    }

    public static function identifyCompetitiveAdvantages($category, $analysis)
    {
        return ['Local small business support', 'Personal customer service'];
    }

    public static function recommendMarketingChannels($category, $analysis)
    {
        return ['Instagram', 'Facebook', 'Email'];
    }

    public static function calculateConfidence($analysis, $category)
    {
        return 0.85;
    }

    public static function determineBrandVoice($category, $analysis)
    {
        return 'Friendly';
    }

    public static function determineContentTone($category, $analysis)
    {
        return 'Professional';
    }

    public static function detectUseCases($text, $category)
    {
        return ['Daily use', 'Gifting'];
    }

    public static function determineMarketPositioning($category, $analysis)
    {
        $quality = in_array('premium', $analysis['quality_indicators'] ?? []) ? 'Premium' : 'Standard';
        $style = !empty($analysis['style']) ? ucfirst($analysis['style'][0]) : 'Modern';

        return "{$quality} {$style} " . rtrim($category, 's');
    }

    public static function assessGiftPotential($text, $category)
    {
        return true;
    }

    private static function generateCustomerBenefits($category, $analysis)
    {
        return ['Durable materials', 'Great giftability', 'Unique design appeal', 'Local craftsmanship'];
    }

    private static function generateCallsToAction($category)
    {
        return ['Order yours today', 'Add to cart now', 'Customize yours', 'Shop the collection'];
    }

    private static function generateUrgencyFactors($category, $analysis)
    {
        return ['Limited seasonal availability', 'Handmade batches sell quickly', 'Holiday demand is high'];
    }

    private static function generateConversionTriggers($category, $analysis)
    {
        return ['Gift-ready packaging', 'Local pickup available', 'Easy returns', 'Fast turnaround'];
    }

    private static function determineSearchIntent($category, $analysis)
    {
        return 'Commercial';
    }

    private static function determineSeasonalRelevance($name, $category)
    {
        $text = strtolower($name . ' ' . $category);
        if (strpos($text, 'christmas') !== false || strpos($text, 'holiday') !== false) {
            return 'Holiday';
        }
        return 'Year-round';
    }

    private static function generateUniqueSellingPoints($name, $category, $analysis)
    {
        return 'Handcrafted quality with thoughtful details and local artisan care.';
    }

    private static function generateValuePropositions($category, $analysis)
    {
        return 'Premium look and feel at a fair, market-competitive price.';
    }

    private static function generatePricingPsychology($category)
    {
        return 'Premium craftsmanship supports a slightly higher perceived value.';
    }

    private static function generateSocialProof($category)
    {
        return ['Loved by local customers', 'Consistently high ratings', 'Repeat buyers for gifting'];
    }

    private static function generateObjectionHandlers($category)
    {
        return ['Quality justifies the price', 'Durable materials ensure long-term value', 'Easy returns if not satisfied'];
    }

    private static function generateContentThemes($category, $analysis)
    {
        return ['Gift-giving', 'Seasonal décor', 'Handcrafted quality', 'Local artisan'];
    }

    private static function generatePainPoints($category, $analysis)
    {
        return ['Finding unique gifts', 'Avoiding mass-produced items', 'Creating memorable moments'];
    }

    private static function generateLifestyleAlignment($category)
    {
        return ['Thoughtful home décor', 'Intentional gifting', 'Support for local makers'];
    }

    private static function generateMarketTrends($category)
    {
        return ['Personalization demand rising', 'Handmade goods gaining popularity', 'Seasonal gifting remains strong'];
    }

    private static function generateRecommendationReasoning($category, $analysis)
    {
        return 'Recommendations align with category demand signals, detected materials, and giftability indicators.';
    }

    private static function itemSeed($name, $category, $salt = '')
    {
        return (int) crc32(strtolower(trim((string) $name)) . '|' . strtolower(trim((string) $category)) . '|' . strtolower(trim((string) $salt)));
    }
}
