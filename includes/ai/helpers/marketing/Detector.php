<?php
/**
 * Marketing Detector - Extracts attributes from text.
 */

class MarketingDetector
{
    public static function detectMaterials($text)
    {
        $materials = [];
        $materialMap = [
            'cotton' => ['cotton', '100% cotton', 'organic cotton'],
            'stainless steel' => ['stainless', 'steel', 'metal'],
            'canvas' => ['canvas', 'stretched canvas'],
            'ceramic' => ['ceramic', 'porcelain'],
            'vinyl' => ['vinyl', 'adhesive'],
            'wood' => ['wood', 'wooden', 'bamboo'],
            'glass' => ['glass', 'tempered']
        ];
        foreach ($materialMap as $material => $keywords) {
            foreach ($keywords as $keyword) {
                if (self::containsKeyword($text, $keyword)) {
                    $materials[] = $material;
                    break;
                }
            }
        }
        return array_unique($materials);
    }

    public static function detectFeatures($text)
    {
        $features = [];
        $featureMap = [
            'insulated' => ['insulated', 'thermal', 'keeps hot', 'keeps cold'],
            'waterproof' => ['waterproof', 'water resistant'],
            'dishwasher safe' => ['dishwasher', 'easy clean'],
            'handmade' => ['handmade', 'hand crafted', 'artisan'],
            'custom' => ['custom', 'personalized', 'customizable'],
            'eco-friendly' => ['eco', 'sustainable', 'green', 'organic'],
            'durable' => ['durable', 'long lasting', 'sturdy'],
            'lightweight' => ['lightweight', 'portable']
        ];
        foreach ($featureMap as $feature => $keywords) {
            foreach ($keywords as $keyword) {
                if (self::containsKeyword($text, $keyword)) {
                    $features[] = $feature;
                    break;
                }
            }
        }
        return array_unique($features);
    }

    private static function containsKeyword($text, $keyword)
    {
        $t = strtolower((string)$text);
        $t = preg_replace('/[^a-z0-9\s]+/i', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));
        
        $k = strtolower((string)$keyword);
        $k = preg_replace('/[^a-z0-9\s]+/i', ' ', $k);
        $k = trim(preg_replace('/\s+/', ' ', $k));
        
        if ($t === '' || $k === '') return false;
        return preg_match('/(?:^|\s)' . preg_quote($k, '/') . '(?:$|\s)/i', $t) === 1;
    }

    public static function detectStyle($text)
    {
        $styles = [];
        if (stripos($text, 'modern') !== false) $styles[] = 'modern';
        if (stripos($text, 'classic') !== false) $styles[] = 'classic';
        if (stripos($text, 'vintage') !== false) $styles[] = 'vintage';
        return $styles;
    }

    public static function detectQualityIndicators($text)
    {
        $indicators = [];
        if (stripos($text, 'premium') !== false) $indicators[] = 'premium';
        if (stripos($text, 'quality') !== false) $indicators[] = 'high-quality';
        if (stripos($text, 'durable') !== false) $indicators[] = 'durable';
        return $indicators;
    }
}
