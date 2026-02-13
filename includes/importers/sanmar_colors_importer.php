<?php
/**
 * SanMar Colors Importer
 *
 * Extracts color names from public SanMar Digital Color Guide PDFs and imports them into:
 * - global_colors (as "SM-<Color>")
 * - color_templates + color_template_items (template_name = "Sanmar")
 *
 * This is intended to be reused by:
 * - scripts/import_sanmar_colors.php (CLI)
 * - api/sanmar_import.php (admin UI button)
 */

declare(strict_types=1);

const WF_SANMAR_TEMPLATE_NAME = 'Sanmar';
const WF_SANMAR_CATEGORY = 'Sanmar';
const WF_SANMAR_PREFIX = 'SM-';

const WF_SANMAR_PDF_SOURCES = [
    [
        'name' => 'Port & Co Digital Color Guide',
        'url' => 'https://www.sanmar.com/medias/sys_master/root/h20/hdd/30832035299358/2026%20Spring%20Port&Co%20Digital%20Color%20Guide_Final_SM%20Links/2026-Spring-Port-Co-Digital-Color-Guide-Final-SM-Links.pdf',
    ],
    [
        'name' => 'District Digital Color Guide',
        'url' => 'https://www.sanmar.com/medias/sys_master/root/h93/hfb/30826636378142/2026%20Spring%20District%20Digital%20Color%20Guide_SM%20Links/2026-Spring-District-Digital-Color-Guide-SM-Links.pdf',
    ],
    [
        'name' => 'Sport-Tek Digital Color Guide',
        'url' => 'https://www.sanmar.com/medias/sys_master/root/h6c/hf8/30826631790622/SP26%20Sport-Tek%20Digital%20Color%20Guide_SM%20Links/SP26-Sport-Tek-Digital-Color-Guide-SM-Links.pdf',
    ],
];

function wf_sanmar_http_get_bytes(string $url): string
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 60,
            'header' => [
                'User-Agent: WhimsicalFrog/1.0 (+https://whimsicalfrog.us)',
                'Accept: application/pdf,*/*;q=0.8',
            ],
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $bytes = @file_get_contents($url, false, $ctx);
    if ($bytes === false || $bytes === '') {
        $err = error_get_last();
        throw new RuntimeException('Failed to download PDF: ' . $url . ($err ? (' (' . ($err['message'] ?? 'unknown error') . ')') : ''));
    }
    return $bytes;
}

function wf_sanmar_extract_search_q_values(string $bytes): array
{
    $out = [];
    if (preg_match_all('#https?://(?:www\.)?sanmar\.com/search\?q=([^&\)\s]+)#i', $bytes, $m)) {
        foreach ($m[1] as $q) {
            $q = str_replace('+', ' ', $q);
            $q = rawurldecode($q);
            $q = trim(preg_replace('#\s+#', ' ', $q));
            if ($q !== '') $out[] = $q;
        }
    }
    return $out;
}

function wf_sanmar_extract_xdefault_rdf_li(string $bytes): array
{
    $out = [];
    if (preg_match_all('#<rdf:li\s+xml:lang="x-default">([^<]{1,120})</rdf:li>#', $bytes, $m)) {
        foreach ($m[1] as $v) {
            $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5);
            $v = trim(preg_replace('#\s+#', ' ', $v));
            if ($v !== '') $out[] = $v;
        }
    }
    return $out;
}

function wf_sanmar_looks_like_pms_code(string $v): bool
{
    return (bool)preg_match('#^\d{2,5}c$#i', trim($v));
}

function wf_sanmar_split_multi(string $v): array
{
    $parts = preg_split('#,\s*#', $v) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out ?: [trim($v)];
}

function wf_sanmar_de_camel(string $s): string
{
    $s = preg_replace('#([a-z])([A-Z])#', '$1 $2', $s) ?? $s;
    $s = preg_replace('#([A-Z])([A-Z][a-z])#', '$1 $2', $s) ?? $s;
    return $s;
}

function wf_sanmar_normalize_color_name(string $raw): ?string
{
    $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
    if ($raw === '') return null;
    if (wf_sanmar_looks_like_pms_code($raw)) return null;

    $lower = strtolower($raw);
    if (str_contains($lower, 'copyright') || str_contains($lower, 'all rights reserved')) return null;
    if (str_contains($raw, '©')) return null;
    if (str_contains($raw, 'D:\\') || str_contains($raw, 'd:\\')) return null;
    if (str_contains($lower, 'xmp.did:') || str_contains($lower, 'adobe:docid:')) return null;
    if (str_contains($lower, 'profiles') || $lower === 'swatch') return null;

    $dropIfContains = [
        'port & co',
        'district',
        'sport-tek',
        'posi',
        'tee',
        'pullover',
        'fleece',
        'hoodie',
        'tank',
        'polo',
        'adult/men',
        'editorial',
        'flat',
        'form',
    ];
    foreach ($dropIfContains as $needle) {
        if (str_contains($lower, $needle)) return null;
    }

    $s = $raw;
    $s = str_replace('_', ' ', $s);
    $s = wf_sanmar_de_camel($s);
    $s = preg_replace('#\s*/\s*#', '/', $s) ?? $s;
    $s = preg_replace('#\s+#', ' ', $s) ?? $s;
    $s = trim($s);

    if ($s === '') return null;
    if (strlen($s) > 60) return null;
    if (preg_match('#^[0-9./:-]+$#', $s)) return null;
    if (!preg_match('#[A-Za-z]#', $s)) return null;

    if ($s === strtolower($s)) {
        $s = ucwords($s);
    }
    // Prevent case-insensitive UNIQUE collisions (utf8mb4_unicode_ci)
    if ($s === strtoupper($s) && preg_match('#[A-Z]#', $s)) {
        $s = ucwords(strtolower($s));
    }

    $s = preg_replace('#\bS\.\s*#', 'S. ', $s) ?? $s;
    $s = preg_replace('#\bCvc\b#', 'CVC', $s) ?? $s;
    $s = preg_replace('#\bDtg\b#', 'DTG', $s) ?? $s;

    return $s;
}

function wf_sanmar_build_color_list(): array
{
    $rawCandidates = [];
    foreach (WF_SANMAR_PDF_SOURCES as $src) {
        $bytes = wf_sanmar_http_get_bytes($src['url']);

        foreach (wf_sanmar_extract_search_q_values($bytes) as $q) {
            foreach (wf_sanmar_split_multi($q) as $part) $rawCandidates[] = $part;
        }
        foreach (wf_sanmar_extract_xdefault_rdf_li($bytes) as $v) {
            foreach (wf_sanmar_split_multi($v) as $part) $rawCandidates[] = $part;
        }
    }

    $colors = [];
    foreach ($rawCandidates as $cand) {
        $norm = wf_sanmar_normalize_color_name($cand);
        if (!$norm) continue;
        $colors[$norm] = true;
    }

    $list = array_keys($colors);
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

function wf_sanmar_upsert_global_colors(array $baseColorNames): array
{
    $added = 0;
    $updated = 0;
    $skipped = [];

    foreach ($baseColorNames as $name) {
        $full = WF_SANMAR_PREFIX . $name;
        if (strlen($full) > 100) {
            $skipped[] = $full;
            continue;
        }

        $affected = Database::execute(
            "INSERT INTO global_colors (color_name, color_code, category, description, display_order, is_active)
             VALUES (?, NULL, ?, '', 0, 1)
             ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                is_active = 1",
            [$full, WF_SANMAR_CATEGORY]
        );

        // MySQL often returns 1 for insert, 2 for update.
        if ($affected === 1) $added++;
        else if ($affected === 2) $updated++;
    }

    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
}

function wf_sanmar_upsert_template(array $baseColorNames): array
{
    $template = Database::queryOne("SELECT id, is_active FROM color_templates WHERE template_name = ? LIMIT 1", [WF_SANMAR_TEMPLATE_NAME]);
    if (!$template) {
        Database::execute(
            "INSERT INTO color_templates (template_name, description, category, is_active) VALUES (?, ?, ?, 1)",
            [WF_SANMAR_TEMPLATE_NAME, 'SanMar imported color list (prefixed SM-).', WF_SANMAR_CATEGORY]
        );
        $templateId = (int) Database::lastInsertId();
    } else {
        $templateId = (int) $template['id'];
        if ((int)($template['is_active'] ?? 1) !== 1) {
            Database::execute("UPDATE color_templates SET is_active = 1 WHERE id = ?", [$templateId]);
        }
        Database::execute(
            "UPDATE color_templates SET description = ?, category = ? WHERE id = ?",
            ['SanMar imported color list (prefixed SM-).', WF_SANMAR_CATEGORY, $templateId]
        );
    }

    Database::execute("DELETE FROM color_template_items WHERE template_id = ?", [$templateId]);

    $inserted = 0;
    $skipped = [];
    $order = 1;
    foreach ($baseColorNames as $name) {
        $full = WF_SANMAR_PREFIX . $name;
        if (strlen($full) > 50) {
            $skipped[] = $full;
            continue;
        }
        Database::execute(
            "INSERT INTO color_template_items (template_id, color_name, color_code, display_order, is_active)
             VALUES (?, ?, NULL, ?, 1)",
            [$templateId, $full, $order++]
        );
        $inserted++;
    }

    return ['template_id' => $templateId, 'inserted' => $inserted, 'skipped' => $skipped];
}

/**
 * Returns import stats for UI/CLI.
 *
 * @return array{
 *   extracted_base_colors:int,
 *   global_colors: array{added:int, updated:int, total_sm:int},
 *   template: array{id:int, name:string, items_inserted:int},
 *   skipped: array{global_colors: string[], template_items: string[]}
 * }
 */
function wf_import_sanmar_colors(): array
{
    Database::getInstance();

    $baseColors = wf_sanmar_build_color_list();
    if (empty($baseColors)) {
        throw new RuntimeException('No colors were extracted from SanMar sources.');
    }

    Database::beginTransaction();
    try {
        $resColors = wf_sanmar_upsert_global_colors($baseColors);
        $resTpl = wf_sanmar_upsert_template($baseColors);

        $totalSm = (int) (Database::queryOne("SELECT COUNT(*) c FROM global_colors WHERE color_name LIKE 'SM-%'")['c'] ?? 0);
        Database::commit();
    } catch (Throwable $e) {
        Database::rollBack();
        throw $e;
    }

    return [
        'extracted_base_colors' => count($baseColors),
        'global_colors' => [
            'added' => (int) $resColors['added'],
            'updated' => (int) $resColors['updated'],
            'total_sm' => $totalSm,
        ],
        'template' => [
            'id' => (int) $resTpl['template_id'],
            'name' => WF_SANMAR_TEMPLATE_NAME,
            'items_inserted' => (int) $resTpl['inserted'],
        ],
        'skipped' => [
            'global_colors' => array_values(array_unique($resColors['skipped'] ?? [])),
            'template_items' => array_values(array_unique($resTpl['skipped'] ?? [])),
        ],
    ];
}

