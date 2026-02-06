<?php

// WF_GUARD_TEMPLATES_CSS_IGNORE: branding tokens helper generates a reusable <style> block for CSS variables

require_once __DIR__ . '/../api/config.php';
@require_once __DIR__ . '/../includes/business_settings_helper.php';

class BrandingTokens
{
    private const TABLE = 'wf_brand_tokens';

    private const DEFAULTS = [
        'business_brand_primary' => '#87ac3a',
        'business_brand_secondary' => '#BF5700',
        'business_brand_accent' => '#22c55e',
        'business_brand_background' => '#ffffff',
        'business_brand_text' => '#111827',
        'business_toast_text' => '#ffffff',
        'business_brand_font_primary' => "'Merienda', cursive",
        'business_brand_font_secondary' => "Nunito, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        'business_brand_font_title_primary' => "'Merienda', cursive",
        'business_brand_font_title_secondary' => "'Merienda', cursive",
        'business_public_header_bg' => '#ffffff',
        'business_public_header_text' => '#000000',
        'business_public_modal_bg' => '#ffffff',
        'business_public_modal_text' => '#000000',
        'business_public_page_bg' => '#ffffff',
        'business_public_page_text' => '#000000',
        // Button palette defaults (background/border and text)
        'business_button_primary_bg' => '#87ac3a',
        'business_button_primary_hover' => '#bf5700',
        'business_button_secondary_bg' => '', // inherits brand secondary by default
        'business_button_secondary_hover' => '', // inherits brand primary by default
        'business_button_primary_text' => '#ffffff',
        'business_button_secondary_text' => '#ffffff',
        'business_button_height' => '40px',
        'business_admin_modal_radius' => '',
        'business_admin_modal_body_padding' => '',
        'business_admin_modal_shadow' => '',
        'business_brand_palette' => '[]',
        'business_css_vars' => '',
        'brand_backup' => '',
        'brand_backup_saved_at' => '',
        // Transition tokens
        'business_transition_fast' => 'all 0.2s ease',
        'business_transition_normal' => 'all 0.3s ease',
        'business_transition_smooth' => 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
        // Shadow tokens
        'business_shadow_sm' => '0 1px 3px rgba(0, 0, 0, 0.1)',
        'business_shadow_md' => '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
        'business_shadow_lg' => '0 12px 20px -8px rgba(0, 0, 0, 0.1)',
        // Scrollbar tokens (advanced - CSS Catalog)
        'business_scrollbar_thumb' => '#c1c1c1',
        'business_scrollbar_track' => '#f1f1f1',
        'business_scrollbar_width' => '12px',
        // Hover lift tokens
        'business_hover_lift' => '-2px',
        'business_hover_lift_lg' => '-4px',
    ];

    private const COLOR_FIELDS = [
        'business_brand_primary',
        'business_brand_secondary',
        'business_brand_accent',
        'business_brand_background',
        'business_brand_text',
        'business_toast_text',
        'business_public_header_bg',
        'business_public_header_text',
        'business_public_modal_bg',
        'business_public_modal_text',
        'business_public_page_bg',
        'business_public_page_text',
        'business_button_primary_bg',
        'business_button_primary_hover',
        'business_button_secondary_bg',
        'business_button_secondary_hover',
        'business_button_primary_text',
        'business_button_secondary_text',
    ];

    private const FONT_FIELDS = [
        'business_brand_font_primary',
        'business_brand_font_secondary',
        'business_brand_font_title_primary',
        'business_brand_font_title_secondary',
    ];

    /**
     * When button-specific palette values are missing, fall back to the main brand colors so
     * primary/secondary buttons always have a usable palette (critical for admin modals).
     */
    private const BUTTON_FALLBACKS = [
        'business_button_primary_bg' => 'business_brand_primary',
        'business_button_primary_hover' => 'business_brand_secondary',
        'business_button_secondary_bg' => 'business_brand_secondary',
        'business_button_secondary_hover' => 'business_brand_primary',
    ];

    private static ?array $cache = null;
    private static ?string $styleCache = null;
    private static bool $tableEnsured = false;
    private static ?string $lastUpdatedAt = null;
    private static ?string $lastUpdatedBy = null;

    public static function getRawTokens(): array
    {
        return self::getTokens();
    }

    public static function getTokens(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::ensureTable();

        $row = null;
        try {
            $row = Database::queryOne("SELECT id, tokens, updated_by, DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') AS updated_at FROM `" . self::TABLE . "` ORDER BY id ASC LIMIT 1");
        } catch (Throwable $e) {
            $row = null;
        }

        $tokens = [];
        if ($row && isset($row['tokens'])) {
            $decoded = json_decode((string) $row['tokens'], true);
            if (is_array($decoded)) {
                $tokens = $decoded;
            }
            self::$lastUpdatedAt = $row['updated_at'] ?? null;
            self::$lastUpdatedBy = $row['updated_by'] ?? null;
        }

        if (!$tokens) {
            $tokens = self::fallbackTokens();
        }

        $normalized = self::normalizeTokens($tokens);
        self::$cache = $normalized;
        return $normalized;
    }

    public static function getPaletteArray(?array $tokens = null): array
    {
        $tokens = $tokens ?? self::getTokens();
        $raw = isset($tokens['business_brand_palette']) ? (string) $tokens['business_brand_palette'] : '[]';
        $decoded = json_decode($raw, true);
        $clean = [];
        if (!is_array($decoded)) {
            return $clean;
        }
        foreach ($decoded as $entry) {
            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
            $hex = isset($entry['hex']) ? trim((string) $entry['hex']) : '';
            if ($name === '' || !self::isValidColor($hex)) {
                continue;
            }
            $clean[] = [
                'name' => $name,
                'hex' => $hex,
            ];
        }
        return $clean;
    }

    public static function createBackup(?string $updatedBy = null): bool
    {
        self::ensureTable();
        $tokens = self::getRawTokens();
        $json = json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $existing = Database::queryOne("SELECT id FROM `" . self::TABLE . "` ORDER BY id ASC LIMIT 1");
        if ($existing && isset($existing['id'])) {
            Database::execute(
                "UPDATE `" . self::TABLE . "` SET brand_backup = ?, brand_backup_saved_at = NOW() WHERE id = ?",
                [$json, (int) $existing['id']]
            );
            return true;
        }
        return false;
    }

    public static function resetToDefaults(?string $updatedBy = null): bool
    {
        return self::saveTokens(self::DEFAULTS, $updatedBy);
    }

    public static function saveTokens(array $tokens, ?string $updatedBy = null): bool
    {
        self::ensureTable();
        $normalized = self::normalizeTokens($tokens);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $existing = null;
        try {
            $existing = Database::queryOne("SELECT id FROM `" . self::TABLE . "` ORDER BY id ASC LIMIT 1");
        } catch (Throwable $e) {
            $existing = null;
        }

        $updatedBy = $updatedBy ? mb_substr($updatedBy, 0, 190) : null;

        if ($existing && isset($existing['id'])) {
            Database::execute(
                "UPDATE `" . self::TABLE . "` SET tokens = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
                [$json, $updatedBy, (int) $existing['id']]
            );
        } else {
            Database::execute(
                "INSERT INTO `" . self::TABLE . "` (tokens, updated_by) VALUES (?, ?)",
                [$json, $updatedBy]
            );
        }

        self::$cache = $normalized;
        self::$lastUpdatedBy = $updatedBy;
        self::$lastUpdatedAt = date('Y-m-d H:i:s');
        return true;
    }

    public static function buildStyleBlock(?array $tokens = null, string $styleId = 'wf-branding-vars'): string
    {
        if ($tokens === null && self::$styleCache !== null) {
            return self::$styleCache;
        }

        require_once __DIR__ . '/helpers/BrandingStyleHelper.php';
        $tokens = $tokens ?? self::getTokens();
        $style = BrandingStyleHelper::buildStyleBlock($tokens, $styleId);

        if ($tokens === self::$cache) {
            self::$styleCache = $style;
        }
        return $style;
    }

    public static function validatePayload(array $payload): array
    {
        $errors = [];

        foreach (self::COLOR_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = trim((string) $payload[$field]);
            if ($value !== '' && !self::isValidColor($value)) {
                $errors[$field] = 'Invalid color value (expecting hex).';
            }
        }

        foreach (self::FONT_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = trim((string) $payload[$field]);
            if (mb_strlen($value) > 255) {
                $errors[$field] = 'Font stack exceeds 255 characters.';
            }
        }

        if (isset($payload['business_css_vars']) && mb_strlen((string) $payload['business_css_vars']) > 5000) {
            $errors['business_css_vars'] = 'Custom CSS is limited to 5000 characters.';
        }

        return $errors;
    }

    public static function encodePaletteArray(?array $palette): string
    {
        if (!is_array($palette)) {
            return '[]';
        }
        $clean = [];
        foreach ($palette as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
            $hex = isset($entry['hex']) ? trim((string) $entry['hex']) : '';
            if ($name === '' || !self::isValidColor($hex)) {
                continue;
            }
            $clean[] = [
                'name' => $name,
                'hex' => $hex,
            ];
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function getLastUpdatedAt(): ?string
    {
        return self::$lastUpdatedAt;
    }

    public static function getLastUpdatedBy(): ?string
    {
        return self::$lastUpdatedBy;
    }

    private static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tokens` JSON NOT NULL,
            `brand_backup` JSON NULL,
            `brand_backup_saved_at` TIMESTAMP NULL,
            `updated_by` VARCHAR(191) NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        try {
            Database::execute($sql);
        } catch (Throwable $e) {
            // Ignore; read paths will fallback gracefully.
        }
        self::$tableEnsured = true;
    }

    private static function fallbackTokens(): array
    {
        // Return hardcoded defaults only.
        // We no longer fall back to legacy BusinessSettings to ensure strict separation.
        // Migration script should be used to move old data to wf_brand_tokens if needed.
        $tokens = [];
        foreach (self::DEFAULTS as $key => $default) {
            $tokens[$key] = $default;
        }
        return $tokens;
    }

    private static function normalizeTokens(array $tokens): array
    {
        $normalized = [];
        foreach (self::DEFAULTS as $key => $default) {
            if ($key === 'business_css_vars') {
                $normalized[$key] = isset($tokens[$key]) ? (string) $tokens[$key] : (string) $default;
                continue;
            }
            $value = array_key_exists($key, $tokens) ? $tokens[$key] : $default;
            if ($value === null) {
                $value = $default;
            }
            if ($key === 'business_brand_palette') {
                if (is_array($value)) {
                    $normalized[$key] = self::encodePaletteArray($value);
                } else {
                    $normalized[$key] = is_string($value) ? $value : (string) $default;
                }
                continue;
            }
            $stringValue = is_string($value) ? trim($value) : trim((string) $value);

            // If value is empty and this key has a dynamic fallback (like buttons inheriting brand color),
            // do NOT force the hardcoded default yet. Leave it empty so the fallback loop below can resolve it.
            if ($stringValue === '' && array_key_exists($key, self::BUTTON_FALLBACKS)) {
                $normalized[$key] = '';
            } elseif ($stringValue === '') {
                $normalized[$key] = (string) $default;
            } else {
                $normalized[$key] = $stringValue;
            }
        }

        // After base normalization, ensure the button palette inherits sensible values from the brand palette if blank.
        foreach (self::BUTTON_FALLBACKS as $buttonKey => $brandKey) {
            $current = $normalized[$buttonKey] ?? '';
            if ($current !== '') {
                continue;
            }
            $brandValue = $normalized[$brandKey] ?? (self::DEFAULTS[$brandKey] ?? '');
            $normalized[$buttonKey] = is_string($brandValue) ? trim($brandValue) : (string) $brandValue;
        }
        return $normalized;
    }

    private static function extractCustomCssLines(?string $raw): array
    {
        if (!$raw) {
            return [];
        }
        $lines = preg_split('/\r?\n/', (string) $raw);
        $out = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || self::startsWith($t, '#') || self::startsWith($t, '//')) {
                continue;
            }
            if (!preg_match('/^--[A-Za-z0-9_-]+\s*:\s*[^;]+;?$/', $t)) {
                continue;
            }
            if (!self::endsWith($t, ';')) {
                $t .= ';';
            }
            $out[] = $t;
        }
        return $out;
    }

    private static function isValidColor(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }
        return (bool) preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $trimmed);
    }

    private static function startsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    private static function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
