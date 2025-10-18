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

        // If local DB is not available (dev), avoid calling DB-backed helper
        try {
            if (class_exists('Database') && method_exists('Database', 'isAvailableQuick')) {
                $disable = getenv('WF_DB_DEV_DISABLE');
                if ($disable === '1' || strtolower((string)$disable) === 'true') {
                    return $default;
                }
                if (!\Database::isAvailableQuick(0.6)) {
                    return $default;
                }
            }
        } catch (\Throwable $e) { /* fall back to default below */ }

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
