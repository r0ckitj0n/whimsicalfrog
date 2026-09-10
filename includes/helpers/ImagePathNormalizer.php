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
     * Preserve backgrounds/realistic/... instead of collapsing to basename-only
     * paths that 404 and force cartoon disk fallbacks.
     */
    private static function backgroundRelativePath(string $raw): string
    {
        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $raw;
        }
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#(?:^|/)(backgrounds/(?:realistic/)?[^/]+)$#i', $normalized, $m) === 1) {
            return $m[1];
        }
        if (preg_match('#(?:^|/)(realistic/[^/]+)$#i', $normalized, $m) === 1) {
            return 'backgrounds/' . $m[1];
        }
        $filename = self::extractFilename($raw);
        return $filename === '' ? '' : ('backgrounds/' . $filename);
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
        return self::backgroundRelativePath($raw);
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
        $relative = self::backgroundRelativePath($raw);
        return $relative === '' ? '' : ('/images/' . ltrim($relative, '/'));
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
        $filename = self::extractFilename($raw);
        return $filename === '' ? '' : ('/images/signs/' . $filename);
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
