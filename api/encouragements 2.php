<?php
// Returns encouragement phrases for shop recommendations
// Method: GET -> { phrases: string[] }
// Storage: BusinessSettings category 'messages', key 'shop_encouragement_phrases'

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $category = 'messages';
    $key = 'shop_encouragement_phrases';
    $fallback = [
        'Highly rated by customers',
        'Popular choice this week',
        'Great alternative to your search',
        'Similar style with better price',
        'Pairs well with bestsellers',
        'New arrival you might love',
    ];

    $settings = BusinessSettings::getByCategory($category);
    $raw = '';
    if (is_array($settings) && array_key_exists($key, $settings)) {
        $raw = $settings[$key];
    }

    $phrases = [];
    if (is_array($raw)) {
        $phrases = array_values(array_filter(array_map('trim', $raw), fn($s) => $s !== ''));
    } else {
        $s = (string)$raw;
        if ($s !== '') {
            // Accept JSON array or newline/comma separated text
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                $phrases = array_values(array_filter(array_map('trim', $decoded), fn($s) => $s !== ''));
            } else {
                $parts = preg_split('/[\r\n,]+/', $s);
                if (is_array($parts)) {
                    $phrases = array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
                }
            }
        }
    }

    if (empty($phrases)) {
        $phrases = $fallback;
    }

    echo json_encode(['phrases' => $phrases], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['phrases' => [
        'Highly rated by customers',
        'Popular choice this week',
        'Great alternative to your search',
        'Similar style with better price',
        'Pairs well with bestsellers',
        'New arrival you might love',
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
