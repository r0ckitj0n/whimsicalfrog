<?php

declare(strict_types=1);

final class ImagePathNormalizer
{
    private static function extractFilename(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $raw;
        }
        $filename = basename(str_replace('\\', '/', $path));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return '';
        }
        return $filename;
    }

    /**
     * Preserve safe relative paths under a library root (e.g. backgrounds/, signs/),
     * including one-level subdirectories such as realistic/.
     */
    private static function extractLibraryRelativePath(string $value, string $libraryRoot): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $raw;
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        $root = trim($libraryRoot, '/');
        $imagesRoot = 'images/' . $root . '/';

        if (stripos($normalized, $imagesRoot) === 0) {
            $normalized = substr($normalized, strlen($imagesRoot));
        } elseif (stripos($normalized, $root . '/') === 0) {
            $normalized = substr($normalized, strlen($root) + 1);
        } elseif (stripos($normalized, 'images/') === 0) {
            $normalized = substr($normalized, strlen('images/'));
            if (stripos($normalized, $root . '/') === 0) {
                $normalized = substr($normalized, strlen($root) + 1);
            }
        }

        $parts = array_values(array_filter(
            explode('/', $normalized),
            static fn(string $part): bool => $part !== '' && $part !== '.' && $part !== '..'
        ));

        if ($parts === []) {
            return '';
        }

        // Allow filename only, or a single safe subdirectory (e.g. realistic/file.webp).
        if (count($parts) === 1) {
            return $parts[0];
        }

        if (count($parts) === 2 && preg_match('/^[A-Za-z0-9_-]+$/', $parts[0])) {
            return $parts[0] . '/' . $parts[1];
        }

        // Fallback: keep basename only for unexpected deeper/unsafe paths.
        return (string) end($parts);
    }

    public static function normalizeBackgroundDbRef(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }
        $relative = self::extractLibraryRelativePath($raw, 'backgrounds');
        return $relative === '' ? '' : ('backgrounds/' . $relative);
    }

    public static function normalizeBackgroundUrl(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }
        $relative = self::extractLibraryRelativePath($raw, 'backgrounds');
        return $relative === '' ? '' : ('/images/backgrounds/' . $relative);
    }

    public static function normalizeSignUrl(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }
        $relative = self::extractLibraryRelativePath($raw, 'signs');
        return $relative === '' ? '' : ('/images/signs/' . $relative);
    }

    public static function normalizeItemDbPath(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }
        $filename = self::extractFilename($raw);
        return $filename === '' ? '' : ('images/items/' . $filename);
    }
}
