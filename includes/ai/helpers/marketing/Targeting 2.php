<?php
/**
 * Marketing Targeting Helper - Handles audience and emotional profile analysis.
 */

class MarketingTargeting
{
    public static function identifyTargetAudience($category, $analysis)
    {
        $audienceMap = [
            'T-Shirts' => 'Fashion-conscious individuals, casual wear enthusiasts, gift buyers',
            'Tumblers' => 'Busy professionals, coffee lovers, eco-conscious consumers, travelers',
            'Artwork' => 'Home decorators, art enthusiasts, gift buyers, interior designers',
            'Sublimation' => 'Personalization seekers, gift buyers, event planners',
            'Window Wraps' => 'Business owners, car enthusiasts, advertisers, decorators'
        ];
        $baseAudience = $audienceMap[$category] ?? 'General consumers';
        if (in_array('premium', $analysis['premium_indicators'] ?? [])) $baseAudience = 'Affluent ' . strtolower($baseAudience);
        if (in_array('eco-friendly', $analysis['features'] ?? [])) $baseAudience .= ', environmentally conscious buyers';
        return $baseAudience;
    }

    public static function identifyEmotionalTriggers($category, $analysis)
    {
        $triggers = ['pride in ownership', 'personal expression'];
        if (!empty($analysis['customization_options'])) $triggers[] = 'uniqueness';
        if (!empty($analysis['gift_potential'])) $triggers[] = 'thoughtful gifting';
        return $triggers;
    }

    public static function generatePsychographicProfile($category, $analysis)
    {
        $profiles = [
            'T-Shirts' => 'Creative individuals who value self-expression and comfort. They appreciate unique designs and supporting local businesses.',
            'Tumblers' => 'Environmentally conscious people who are always on-the-go. They value practicality and sustainability.',
            'Artwork' => 'Art enthusiasts and home decorators who appreciate original, local creativity and want to support artists.',
            'Sublimation' => 'Gift-givers and memory-makers who value personalization and creating lasting keepsakes.',
            'Window Wraps' => 'Business owners and professionals who understand the importance of visual marketing and brand presence.'
        ];
        return $profiles[$category] ?? 'Quality-conscious consumers who appreciate personalized items and local craftsmanship.';
    }

    public static function generateDemographicTargeting($category, $analysis)
    {
        $demographics = [
            'T-Shirts' => 'Ages 16-65, all genders, middle-income households, students, professionals, gift-buyers',
            'Tumblers' => 'Ages 25-55, health-conscious individuals, commuters, office workers, outdoor enthusiasts',
            'Artwork' => 'Ages 30-65, homeowners, art collectors, interior design enthusiasts, gift-buyers',
            'Sublimation' => 'Ages 25-60, parents, grandparents, event planners, gift-givers, memorial keepsake buyers',
            'Window Wraps' => 'Business owners, marketing managers, retail store owners, service providers'
        ];
        return $demographics[$category] ?? 'Ages 25-55, middle to upper-middle income, quality-conscious consumers';
    }
}
