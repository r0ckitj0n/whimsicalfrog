<?php
// includes/ai/helpers/VisionHeuristics.php

/**
 * Helper class for vision-related heuristics and validations
 */
class VisionHeuristics
{
    /**
     * Validate and sanitize crop bounds from AI response
     */
    public static function validateCropBounds($response)
    {
        if (!is_array($response)) {
            return null;
        }

        $required = ['crop_left_percent', 'crop_top_percent', 'crop_right_percent', 'crop_bottom_percent'];
        foreach ($required as $field) {
            if (!isset($response[$field]) || !is_numeric($response[$field])) {
                return null;
            }
        }

        // Sanitize values to ensure they're within valid ranges
        $bounds = [
            'left' => max(0, min(0.9, floatval($response['crop_left_percent']))),
            'top' => max(0, min(0.9, floatval($response['crop_top_percent']))),
            'right' => max(0.1, min(1.0, floatval($response['crop_right_percent']))),
            'bottom' => max(0.1, min(1.0, floatval($response['crop_bottom_percent']))),
            'confidence' => floatval($response['confidence'] ?? 0.5),
            'description' => $response['description'] ?? 'AI-detected crop bounds'
        ];

        // Ensure right > left and bottom > top
        if ($bounds['right'] <= $bounds['left']) {
            $bounds['right'] = $bounds['left'] + 0.1;
        }
        if ($bounds['bottom'] <= $bounds['top']) {
            $bounds['bottom'] = $bounds['top'] + 0.1;
        }

        return $bounds;
    }

    /**
     * Get fallback crop bounds with basic edge trimming
     */
    public static function getFallbackCropBounds($imagePath, $trimPercent = 0.05)
    {
        // symmetric trim as ultimate fallback
        return [
            'left' => $trimPercent,
            'top' => $trimPercent,
            'right' => 1.0 - $trimPercent,
            'bottom' => 1.0 - $trimPercent,
            'confidence' => 0.3,
            'description' => 'Fallback symmetric trim'
        ];
    }
}
