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
