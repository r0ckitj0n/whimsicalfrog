<?php
/**
 * includes/helpers/BrandVoiceHelper.php
 * Helper class for brand voice options
 */

class BrandVoiceHelper {
    public static function getDefaultOptions(): array {
        return [
            ['friendly_approachable', 'Friendly & Approachable', 'Warm, welcoming, and easy to connect with', 1],
            ['professional_trustworthy', 'Professional & Trustworthy', 'Business-focused, reliable, and credible', 2],
            ['playful_fun', 'Playful & Fun', 'Lighthearted, entertaining, and engaging', 3],
            ['luxurious_premium', 'Luxurious & Premium', 'High-end, sophisticated, and exclusive', 4],
            ['casual_relaxed', 'Casual & Relaxed', 'Laid-back, informal, and comfortable', 5],
            ['authoritative_expert', 'Authoritative & Expert', 'Knowledgeable, confident, and commanding', 6],
            ['warm_personal', 'Warm & Personal', 'Intimate, caring, and heartfelt', 7],
            ['innovative_forward_thinking', 'Innovative & Forward-Thinking', 'Creative, cutting-edge, and progressive', 8],
            ['energetic_dynamic', 'Energetic & Dynamic', 'Enthusiastic, vibrant, and exciting', 9],
            ['sophisticated_elegant', 'Sophisticated & Elegant', 'Refined, polished, and tasteful', 10],
            ['conversational_natural', 'Conversational & Natural', 'Dialogue-like, personal, and engaging', 11],
            ['inspiring_motivational', 'Inspiring & Motivational', 'Uplifting, encouraging, and empowering', 12],
            ['minimalist_clean', 'Minimalist & Clean', 'Simple, straightforward, and uncluttered', 13],
            ['storytelling_narrative', 'Storytelling & Narrative', 'Story-driven, descriptive, and engaging', 14],
            ['humorous_witty', 'Humorous & Witty', 'Amusing, clever, and light-hearted', 15],
            ['sincere_authentic', 'Sincere & Authentic', 'Genuine, honest, and transparent', 16],
            ['bold_confident', 'Bold & Confident', 'Strong, assertive, and fearless', 17],
            ['nurturing_supportive', 'Nurturing & Supportive', 'Caring, helpful, and encouraging', 18]
        ];
    }

    public static function initializeDefaults(): array {
        $defaultOptions = self::getDefaultOptions();
        $inserted = 0;
        foreach ($defaultOptions as $option) {
            $result = Database::execute("INSERT IGNORE INTO brand_voice_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", $option);
            if ($result > 0) $inserted++;
        }
        return ['inserted' => $inserted, 'total' => count($defaultOptions)];
    }
}
