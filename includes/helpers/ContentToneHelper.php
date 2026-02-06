<?php
/**
 * includes/helpers/ContentToneHelper.php
 * Helper class for content tone options
 */

class ContentToneHelper {
    public static function getDefaultOptions(): array {
        return [
            ['professional', 'Professional', 'Clear, authoritative, and business-focused tone', 1],
            ['friendly', 'Friendly', 'Warm, approachable, and conversational tone', 2],
            ['casual', 'Casual', 'Relaxed, informal, and easy-going tone', 3],
            ['energetic', 'Energetic', 'Dynamic, enthusiastic, and exciting tone', 4],
            ['sophisticated', 'Sophisticated', 'Elegant, refined, and polished tone', 5],
            ['playful', 'Playful', 'Fun, lighthearted, and entertaining tone', 6],
            ['urgent', 'Urgent', 'Time-sensitive, compelling, and action-oriented tone', 7],
            ['informative', 'Informative', 'Educational, detailed, and fact-focused tone', 8],
            ['persuasive', 'Persuasive', 'Convincing, compelling, and sales-oriented tone', 9],
            ['emotional', 'Emotional', 'Heartfelt, touching, and sentiment-driven tone', 10],
            ['conversational', 'Conversational', 'Natural, dialogue-like, and personal tone', 11],
            ['authoritative', 'Authoritative', 'Expert, confident, and commanding tone', 12],
            ['inspiring', 'Inspiring', 'Motivational, uplifting, and encouraging tone', 13],
            ['humorous', 'Humorous', 'Witty, amusing, and light-hearted tone', 14],
            ['minimalist', 'Minimalist', 'Simple, concise, and straightforward tone', 15],
            ['luxurious', 'Luxurious', 'Premium, exclusive, and high-end tone', 16],
            ['technical', 'Technical', 'Precise, detailed, and specification-focused tone', 17],
            ['storytelling', 'Storytelling', 'Narrative-driven, engaging, and descriptive tone', 18]
        ];
    }

    public static function initializeDefaults(): array {
        $defaultOptions = self::getDefaultOptions();
        $inserted = 0;
        foreach ($defaultOptions as $option) {
            $result = Database::execute("INSERT IGNORE INTO content_tone_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", $option);
            if ($result > 0) $inserted++;
        }
        return ['inserted' => $inserted, 'total' => count($defaultOptions)];
    }
}
