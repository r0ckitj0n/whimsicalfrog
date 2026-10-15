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

    public static function normalizeBackgroundDbRef(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            return $raw;
        }
        $filename = self::extractFilename($raw);
        return $filename === '' ? '' : ('backgrounds/' . $filename);
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
        $filename = self::extractFilename($raw);
        return $filename === '' ? '' : ('/images/backgrounds/' . $filename);
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
        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = str_replace('\\', '/', $raw);
        } else {
            $path = str_replace('\\', '/', $path);
        }
        // Preserve a single theme subdirectory (e.g. realistic/) under /images/signs/.
        if (preg_match('#(?:^|/)images/signs/([^/]+/[^/]+)$#i', $path, $m)) {
            return '/images/signs/' . $m[1];
        }
        if (preg_match('#(?:^|/)signs/([^/]+/[^/]+)$#i', $path, $m)) {
            return '/images/signs/' . $m[1];
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
