<?php
/**
 * Theme Words Manager - CRUD and logic operations.
 */

function wf_theme_words_increment_usage(PDO $db, $themeWordId, $variantId, $variantText, $context, $source): array
{
    $twId = (int) $themeWordId;
    $vId = (int) $variantId;
    $vt = trim((string) $variantText);
    $ctx = trim((string) $context);
    $src = trim((string) $source);
    $today = date('Y-m-d');

    Database::execute('INSERT INTO theme_word_usage_events (theme_word_id, variant_id, variant_text, context, source, used_at) VALUES (?, ?, ?, ?, ?, NOW())', [$twId ?: null, $vId ?: null, $vt ?: null, $ctx ?: null, $src ?: null]);

    if ($vId > 0) {
        Database::execute('UPDATE theme_word_variants SET usage_count = usage_count + 1, last_used_at = NOW(), daily_usage_count = CASE WHEN daily_usage_date = ? THEN daily_usage_count + 1 ELSE 1 END, daily_usage_date = ? WHERE id = ?', [$today, $today, $vId]);
    }
    if ($twId > 0) {
        Database::execute('UPDATE theme_words SET usage_count = usage_count + 1, last_used_at = NOW(), daily_usage_count = CASE WHEN daily_usage_date = ? THEN daily_usage_count + 1 ELSE 1 END, daily_usage_date = ? WHERE id = ?', [$today, $today, $twId]);
    }

    return ['theme_word_id' => $twId, 'variant_id' => $vId, 'variant_text' => $vt];
}

function normalize_tags($tags): string
{
    $raw = is_array($tags) ? implode(',', $tags) : (string) $tags;
    $parts = preg_split('/\s*,\s*/', strtolower(trim($raw))) ?: [];
    return implode(',', array_unique(array_filter(array_map('trim', $parts))));
}

function wf_theme_words_has_category_id_column(): bool
{
    static $hasCategoryId = null;
    if ($hasCategoryId !== null) {
        return $hasCategoryId;
    }

    try {
        $rows = Database::queryAll("SHOW COLUMNS FROM theme_words LIKE 'category_id'");
        $hasCategoryId = !empty($rows);
    } catch (Throwable $e) {
        $hasCategoryId = false;
    }

    return $hasCategoryId;
}

function get_theme_words_list($db)
{
    $rows = Database::queryAll('SELECT * FROM theme_words ORDER BY base_word ASC');
    $ids = array_column($rows, 'id');
    $variantsByWord = [];
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $vRows = Database::queryAll("SELECT * FROM theme_word_variants WHERE theme_word_id IN ($in) ORDER BY theme_word_id ASC, sort_order ASC", $ids);
        foreach ($vRows as $v) {
            $variantsByWord[$v['theme_word_id']][] = $v;
        }
    }
    foreach ($rows as &$r) {
        $r['variants'] = $variantsByWord[$r['id']] ?? [];
    }
    return $rows;
}

function get_theme_word_categories(PDO $db): array
{
    return Database::queryAll('SELECT * FROM theme_word_categories ORDER BY sort_order ASC, name ASC');
}

function add_theme_word_category(PDO $db, array $data): int
{
    $sql = "INSERT INTO theme_word_categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, ?)";
    Database::execute($sql, [
        $data['name'],
        $data['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $data['name'])),
        $data['sort_order'] ?? 0,
        $data['is_active'] ?? 1
    ]);
    return (int) Database::lastInsertId();
}

function update_theme_word_category(PDO $db, array $data): bool
{
    $sql = "UPDATE theme_word_categories SET name = ?, slug = ?, sort_order = ?, is_active = ? WHERE id = ?";
    return Database::execute($sql, [
        $data['name'],
        $data['slug'],
        $data['sort_order'],
        $data['is_active'],
        $data['id']
    ]);
}

function delete_theme_word_category(PDO $db, $id): bool
{
    return Database::execute("DELETE FROM theme_word_categories WHERE id = ?", [$id]);
}

/**
 * Anti-Saturation Logic: Fetch words with lowest usage or oldest usage.
 */
function get_diverse_theme_words($limit = 3, $category = null): array
{
    // Lifetime anti-saturation:
    // We want broad variety over all time, not per-day throttling.
    // Default cap is 5 uses total per theme word unless explicitly set lower.
    $defaultMaxUsageTotal = 5;
    $effectiveMaxUsageTotal = "COALESCE(NULLIF(tw.max_usage_total, 0), {$defaultMaxUsageTotal})";

    $where = "tw.is_active = 1 AND COALESCE(twc.is_active, 1) = 1";
    $params = [];
    $hasCategoryId = wf_theme_words_has_category_id_column();

    if ($category) {
        if (is_numeric($category)) {
            if ($hasCategoryId) {
                $where .= " AND (tw.category_id = ? OR twc.id = ?)";
                $params[] = (int) $category;
                $params[] = (int) $category;
            } else {
                $where .= " AND twc.id = ?";
                $params[] = (int) $category;
            }
        } else {
            $where .= " AND LOWER(tw.category) = LOWER(?)";
            $params[] = (string) $category;
        }
    }

    $joinClause = $hasCategoryId
        ? "LEFT JOIN theme_word_categories twc
           ON (
                (tw.category_id IS NOT NULL AND tw.category_id = twc.id)
                OR
                (tw.category_id IS NULL AND LOWER(tw.category) = LOWER(twc.name))
           )"
        : "LEFT JOIN theme_word_categories twc ON LOWER(tw.category) = LOWER(twc.name)";

    $sql = "SELECT tw.* FROM theme_words tw
            {$joinClause}
            WHERE $where
            AND tw.usage_count < {$effectiveMaxUsageTotal}
            ORDER BY tw.usage_count ASC, tw.last_used_at ASC
            LIMIT " . (int) $limit;

    $words = Database::queryAll($sql, $params);

    // Attach one random active variant for each word
    foreach ($words as &$w) {
        // Prefer least-used variants to spread phrasing; still enforce the lifetime cap.
        $effectiveVariantMaxUsageTotal = "COALESCE(NULLIF(max_usage_total, 0), {$defaultMaxUsageTotal})";
        $v = Database::queryOne(
            "SELECT *
             FROM theme_word_variants
             WHERE theme_word_id = ?
               AND is_active = 1
               AND usage_count < {$effectiveVariantMaxUsageTotal}
             ORDER BY usage_count ASC, last_used_at ASC, RAND()
             LIMIT 1",
            [$w['id']]
        );
        $w['selected_variant'] = $v;
    }

    return $words;
}

/**
 * High-level helper for AI prompt injection.
 */
function get_whimsical_inspiration($limit = 5): array
{
    $words = get_diverse_theme_words($limit);
    $inspiration = [];
    foreach ($words as $w) {
        $inspiration[] = [
            'id' => $w['id'],
            'base' => $w['base_word'],
            'variant_id' => $w['selected_variant']['id'] ?? 0,
            'text' => $w['selected_variant']['variant_text'] ?? $w['base_word']
        ];
    }
    return $inspiration;
}

/**
 * Log usage for multiple words.
 */
function log_theme_words_usage(array $inspiration, $context, $source): void
{
    $db = Database::getInstance();
    foreach ($inspiration as $item) {
        wf_theme_words_increment_usage($db, $item['id'], $item['variant_id'], $item['text'], $context, $source);
    }
}
