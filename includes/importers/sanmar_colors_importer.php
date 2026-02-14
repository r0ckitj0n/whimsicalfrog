<?php
/**
 * SanMar Colors Importer
 *
 * Extracts color names from public SanMar Digital Color Guide PDFs and imports them into:
 * - global_colors (category = "Sanmar")
 * - color_templates + color_template_items (template_name = "Sanmar")
 *
 * This is intended to be reused by:
 * - scripts/import_sanmar_colors.php (CLI)
 * - api/sanmar_import.php (admin UI button)
 */

declare(strict_types=1);

const WF_SANMAR_TEMPLATE_NAME = 'Sanmar';
const WF_SANMAR_CATEGORY = 'Sanmar';
const WF_SANMAR_LEGACY_PREFIX = 'SM-';

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

function wf_sanmar_is_valid_hex(?string $v): bool
{
    if ($v === null) return false;
    $v = trim($v);
    // Use a delimiter that won't conflict with the literal '#'.
    return (bool)preg_match('~^#[0-9a-fA-F]{6}$~', $v);
}

function wf_sanmar_rgb_to_hex(int $r, int $g, int $b): string
{
    $r = max(0, min(255, $r));
    $g = max(0, min(255, $g));
    $b = max(0, min(255, $b));
    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

function wf_sanmar_hsl_to_rgb(float $h, float $s, float $l): array
{
    // h: 0..360, s/l: 0..1
    $h = fmod(($h % 360.0 + 360.0), 360.0) / 360.0;
    $s = max(0.0, min(1.0, $s));
    $l = max(0.0, min(1.0, $l));

    $c = (1.0 - abs(2.0 * $l - 1.0)) * $s;
    $x = $c * (1.0 - abs(fmod($h * 6.0, 2.0) - 1.0));
    $m = $l - $c / 2.0;

    $r1 = 0.0; $g1 = 0.0; $b1 = 0.0;
    $hp = $h * 6.0;
    if ($hp < 1.0) { $r1 = $c; $g1 = $x; $b1 = 0.0; }
    else if ($hp < 2.0) { $r1 = $x; $g1 = $c; $b1 = 0.0; }
    else if ($hp < 3.0) { $r1 = 0.0; $g1 = $c; $b1 = $x; }
    else if ($hp < 4.0) { $r1 = 0.0; $g1 = $x; $b1 = $c; }
    else if ($hp < 5.0) { $r1 = $x; $g1 = 0.0; $b1 = $c; }
    else { $r1 = $c; $g1 = 0.0; $b1 = $x; }

    return [
        (int)round(($r1 + $m) * 255.0),
        (int)round(($g1 + $m) * 255.0),
        (int)round(($b1 + $m) * 255.0),
    ];
}

function wf_sanmar_hash_color_hex(string $name): string
{
    // Stable, reasonably pleasant fallback. Not an "official" SanMar hex, but avoids gray swatches.
    $seed = crc32(strtolower(trim($name)));
    $h = (float)($seed % 360);
    $s = 0.48;
    $l = 0.46;
    [$r, $g, $b] = wf_sanmar_hsl_to_rgb($h, $s, $l);
    return wf_sanmar_rgb_to_hex($r, $g, $b);
}

function wf_sanmar_guess_hex_from_name(string $name): string
{
    $n = strtolower(trim($name));

    // Common anchors first.
    if (str_contains($n, 'black')) return '#000000';
    if (str_contains($n, 'white')) return '#FFFFFF';
    if (str_contains($n, 'navy')) return '#000080';
    if (str_contains($n, 'maroon')) return '#800000';
    if (str_contains($n, 'red')) return '#FF0000';
    if (str_contains($n, 'orange')) return '#FFA500';
    if (str_contains($n, 'gold')) return '#FFD700';
    if (str_contains($n, 'yellow')) return '#FFFF00';
    if (str_contains($n, 'purple')) return '#800080';
    if (str_contains($n, 'pink')) return '#FFC0CB';
    if (str_contains($n, 'brown')) return '#A52A2A';
    if (str_contains($n, 'teal')) return '#008080';
    if (str_contains($n, 'turquoise')) return '#40E0D0';
    if (str_contains($n, 'cyan')) return '#00FFFF';
    if (str_contains($n, 'lime')) return '#00FF00';
    if (str_contains($n, 'green')) return '#008000';
    if (str_contains($n, 'forest')) return '#0B3D0B';
    if (str_contains($n, 'kelly')) return '#00A650';
    if (str_contains($n, 'royal')) return '#0033A0';
    if (str_contains($n, 'blue')) return '#0000FF';
    if (str_contains($n, 'charcoal')) return '#36454F';
    if (str_contains($n, 'silver')) return '#C0C0C0';
    if (str_contains($n, 'gray') || str_contains($n, 'grey')) return '#808080';

    return wf_sanmar_hash_color_hex($name);
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

function wf_sanmar_load_existing_code_map(array $colorNames): array
{
    $names = array_values(array_unique(array_filter(array_map('strval', $colorNames))));
    if (empty($names)) return [];

    // Prefer already-defined hex codes from any category.
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $rows = Database::queryAll(
        "SELECT color_name, color_code
         FROM global_colors
         WHERE color_name IN ($placeholders)
           AND color_code REGEXP '^#[0-9A-Fa-f]{6}$'",
        $names
    );

    $map = [];
    foreach ($rows as $r) {
        $cn = (string)($r['color_name'] ?? '');
        $cc = (string)($r['color_code'] ?? '');
        if ($cn !== '' && wf_sanmar_is_valid_hex($cc)) {
            $map[$cn] = strtoupper($cc);
        }
    }
    return $map;
}

function wf_sanmar_build_import_code_map(array $baseColorNames): array
{
    $baseColorNames = array_values($baseColorNames);
    $existing = wf_sanmar_load_existing_code_map($baseColorNames);

    $out = [];
    foreach ($baseColorNames as $name) {
        $code = $existing[$name] ?? null;
        if (!$code) {
            $code = wf_sanmar_guess_hex_from_name($name);
        }
        $out[$name] = strtoupper($code);
    }
    return $out;
}

function wf_sanmar_upsert_global_colors(array $baseColorNames, array $codeMap): array
{
    $added = 0;
    $updated = 0;
    $skipped = [];

    foreach ($baseColorNames as $name) {
        $full = $name;
        if (strlen($full) > 100) {
            $skipped[] = $full;
            continue;
        }
        $code = $codeMap[$name] ?? '';

        $affected = Database::execute(
            "INSERT INTO global_colors (color_name, color_code, category, description, display_order, is_active)
             VALUES (?, ?, ?, '', 0, 1)
             ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                color_code = CASE
                    WHEN (global_colors.color_code IS NULL OR global_colors.color_code = '')
                         AND (VALUES(color_code) IS NOT NULL AND VALUES(color_code) != '')
                    THEN VALUES(color_code)
                    ELSE global_colors.color_code
                END,
                is_active = 1",
            [$full, $code, WF_SANMAR_CATEGORY]
        );

        // MySQL often returns 1 for insert, 2 for update.
        if ($affected === 1) $added++;
        else if ($affected === 2) $updated++;
    }

    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
}

function wf_sanmar_upsert_template(array $baseColorNames, array $codeMap): array
{
    $template = Database::queryOne("SELECT id, is_active FROM color_templates WHERE template_name = ? LIMIT 1", [WF_SANMAR_TEMPLATE_NAME]);
    if (!$template) {
        Database::execute(
            "INSERT INTO color_templates (template_name, description, category, is_active) VALUES (?, ?, ?, 1)",
            [WF_SANMAR_TEMPLATE_NAME, 'SanMar imported color list.', WF_SANMAR_CATEGORY]
        );
        $templateId = (int) Database::lastInsertId();
    } else {
        $templateId = (int) $template['id'];
        if ((int)($template['is_active'] ?? 1) !== 1) {
            Database::execute("UPDATE color_templates SET is_active = 1 WHERE id = ?", [$templateId]);
        }
        Database::execute(
            "UPDATE color_templates SET description = ?, category = ? WHERE id = ?",
            ['SanMar imported color list.', WF_SANMAR_CATEGORY, $templateId]
        );
    }

    Database::execute("DELETE FROM color_template_items WHERE template_id = ?", [$templateId]);

    $inserted = 0;
    $skipped = [];
    $order = 1;
    foreach ($baseColorNames as $name) {
        $full = $name;
        if (strlen($full) > 50) {
            $skipped[] = $full;
            continue;
        }
        $code = $codeMap[$name] ?? '';
        Database::execute(
            "INSERT INTO color_template_items (template_id, color_name, color_code, display_order, is_active)
             VALUES (?, ?, ?, ?, 1)",
            [$templateId, $full, $code, $order++]
        );
        $inserted++;
    }

    return ['template_id' => $templateId, 'inserted' => $inserted, 'skipped' => $skipped];
}

function wf_sanmar_migrate_strip_prefix_and_backfill_codes(): array
{
    $renamed = 0;
    $merged = 0;
    $assignmentsMoved = 0;
    $legacyDeactivated = 0;
    $codesBackfilled = 0;
    $templateItemsRebuilt = 0;

    // 1) Strip legacy SM- prefix from global_colors, preserving IDs when possible.
    $legacy = Database::queryAll(
        "SELECT id, color_name, color_code
         FROM global_colors
         WHERE is_active = 1
           AND color_name LIKE 'SM-%'"
    );

    foreach ($legacy as $row) {
        $id = (int)($row['id'] ?? 0);
        $oldName = (string)($row['color_name'] ?? '');
        if ($id <= 0 || $oldName === '') continue;

        $newName = substr($oldName, strlen(WF_SANMAR_LEGACY_PREFIX));
        $newName = trim((string)$newName);
        if ($newName === '') continue;

        $target = Database::queryOne("SELECT id, color_code FROM global_colors WHERE color_name = ? LIMIT 1", [$newName]);
        if (!$target) {
            Database::execute(
                "UPDATE global_colors SET color_name = ?, category = ?, is_active = 1 WHERE id = ?",
                [$newName, WF_SANMAR_CATEGORY, $id]
            );
            $renamed++;
            continue;
        }

        $targetId = (int)($target['id'] ?? 0);
        if ($targetId <= 0 || $targetId === $id) {
            // Already fine or weird edge-case.
            Database::execute("UPDATE global_colors SET category = ?, is_active = 1 WHERE id = ?", [WF_SANMAR_CATEGORY, $id]);
            continue;
        }

        $srcCode = (string)($row['color_code'] ?? '');
        $dstCode = (string)($target['color_code'] ?? '');
        if (!wf_sanmar_is_valid_hex($dstCode) && wf_sanmar_is_valid_hex($srcCode)) {
            Database::execute("UPDATE global_colors SET color_code = ? WHERE id = ?", [strtoupper($srcCode), $targetId]);
        }

        Database::execute("UPDATE global_colors SET category = ?, is_active = 1 WHERE id = ?", [WF_SANMAR_CATEGORY, $targetId]);

        $assignmentsMoved += Database::execute(
            "UPDATE item_color_assignments SET global_color_id = ? WHERE global_color_id = ?",
            [$targetId, $id]
        );

        Database::execute("UPDATE global_colors SET is_active = 0 WHERE id = ?", [$id]);
        $legacyDeactivated++;
        $merged++;
    }

    // 2) Backfill missing hex codes for Sanmar category colors.
    $needs = Database::queryAll(
        "SELECT id, color_name
         FROM global_colors
         WHERE category = ?
           AND is_active = 1
           AND (color_code IS NULL OR color_code = '')",
        [WF_SANMAR_CATEGORY]
    );
    if (!empty($needs)) {
        $names = array_map(fn($r) => (string)($r['color_name'] ?? ''), $needs);
        $existingMap = wf_sanmar_load_existing_code_map($names);
        foreach ($needs as $r) {
            $id = (int)($r['id'] ?? 0);
            $name = (string)($r['color_name'] ?? '');
            if ($id <= 0 || $name === '') continue;
            $code = $existingMap[$name] ?? wf_sanmar_guess_hex_from_name($name);
            if (!wf_sanmar_is_valid_hex($code)) continue;
            Database::execute("UPDATE global_colors SET color_code = ? WHERE id = ? AND (color_code IS NULL OR color_code = '')", [strtoupper($code), $id]);
            $codesBackfilled++;
        }
    }

    // 3) Rebuild the Sanmar template items without SM- prefix.
    $tpl = Database::queryOne("SELECT id FROM color_templates WHERE template_name = ? LIMIT 1", [WF_SANMAR_TEMPLATE_NAME]);
    if ($tpl) {
        $templateId = (int)($tpl['id'] ?? 0);
        if ($templateId > 0) {
            $rows = Database::queryAll(
                "SELECT DISTINCT color_name
                 FROM global_colors
                 WHERE category = ?
                   AND is_active = 1
                 ORDER BY color_name ASC",
                [WF_SANMAR_CATEGORY]
            );
            $names = [];
            foreach ($rows as $rr) {
                $cn = trim((string)($rr['color_name'] ?? ''));
                if ($cn !== '') $names[] = $cn;
            }
            $codeMap = wf_sanmar_load_existing_code_map($names);
            Database::execute("DELETE FROM color_template_items WHERE template_id = ?", [$templateId]);
            $order = 1;
            foreach ($names as $cn) {
                $code = $codeMap[$cn] ?? wf_sanmar_guess_hex_from_name($cn);
                Database::execute(
                    "INSERT INTO color_template_items (template_id, color_name, color_code, display_order, is_active)
                     VALUES (?, ?, ?, ?, 1)",
                    [$templateId, $cn, strtoupper($code), $order++]
                );
                $templateItemsRebuilt++;
            }
            Database::execute(
                "UPDATE color_templates SET description = ?, category = ?, is_active = 1 WHERE id = ?",
                ['SanMar imported color list.', WF_SANMAR_CATEGORY, $templateId]
            );
        }
    }

    return [
        'renamed' => $renamed,
        'merged' => $merged,
        'item_color_assignments_moved' => $assignmentsMoved,
        'legacy_deactivated' => $legacyDeactivated,
        'codes_backfilled' => $codesBackfilled,
        'template_items_rebuilt' => $templateItemsRebuilt,
    ];
}

/**
 * Returns import stats for UI/CLI.
 *
 * @return array{
 *   extracted_base_colors:int,
 *   global_colors: array{added:int, updated:int, total_sanmar:int},
 *   template: array{id:int, name:string, items_inserted:int},
 *   migration: array{
 *     renamed:int,
 *     merged:int,
 *     item_color_assignments_moved:int,
 *     legacy_deactivated:int,
 *     codes_backfilled:int,
 *     template_items_rebuilt:int
 *   },
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
        $migration = wf_sanmar_migrate_strip_prefix_and_backfill_codes();
        $codeMap = wf_sanmar_build_import_code_map($baseColors);

        $resColors = wf_sanmar_upsert_global_colors($baseColors, $codeMap);
        $resTpl = wf_sanmar_upsert_template($baseColors, $codeMap);

        $totalSanmar = (int) (Database::queryOne("SELECT COUNT(*) c FROM global_colors WHERE category = ? AND is_active = 1", [WF_SANMAR_CATEGORY])['c'] ?? 0);
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
            'total_sanmar' => $totalSanmar,
        ],
        'template' => [
            'id' => (int) $resTpl['template_id'],
            'name' => WF_SANMAR_TEMPLATE_NAME,
            'items_inserted' => (int) $resTpl['inserted'],
        ],
        'migration' => [
            'renamed' => (int)($migration['renamed'] ?? 0),
            'merged' => (int)($migration['merged'] ?? 0),
            'item_color_assignments_moved' => (int)($migration['item_color_assignments_moved'] ?? 0),
            'legacy_deactivated' => (int)($migration['legacy_deactivated'] ?? 0),
            'codes_backfilled' => (int)($migration['codes_backfilled'] ?? 0),
            'template_items_rebuilt' => (int)($migration['template_items_rebuilt'] ?? 0),
        ],
        'skipped' => [
            'global_colors' => array_values(array_unique($resColors['skipped'] ?? [])),
            'template_items' => array_values(array_unique($resTpl['skipped'] ?? [])),
        ],
    ];
}
