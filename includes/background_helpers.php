<?php

/**
 * Shared background helper utilities.
 */

if (!function_exists('get_landing_background_path')) {
    /**
     * Safe wrapper around get_active_background().
     * Returns a valid background image path for the landing page.
     * Falls back to a default image if anything fails.
     */
    function get_landing_background_path(): string
    {
        $default = 'images/backgrounds/background-home.webp';

        // If the core helper is not available, just return default.
        if (!function_exists('get_active_background')) {
            return $default;
        }

        try {
            $path = get_active_background('landing');
            if ($path && is_string($path) && file_exists($path)) {
                return $path;
            }
        } catch (Throwable $e) {
            // swallow and fall back
        }

        return $default;
    }
}
