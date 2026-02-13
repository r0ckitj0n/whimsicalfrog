<?php
/**
 * Import SanMar shirt colors into:
 * - global_colors (as "SM-<Color>")
 * - color_templates + color_template_items (template_name = "Sanmar")
 *
 * Source: SanMar "Digital Color Guide" PDFs (public links).
 *
 * Idempotent:
 * - global_colors is upserted by unique color_name
 * - template items are replaced on each run
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';

const SANMAR_TEMPLATE_NAME = 'Sanmar';
const SANMAR_CATEGORY = 'Sanmar';
const SANMAR_PREFIX = 'SM-';

// Public SanMar PDFs (shirting brands commonly used for printing blanks).
const SANMAR_PDF_SOURCES = [
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

function http_get_bytes(string $url): string
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 60,
            'header' => [
                // Some CDNs are picky; send a normal UA.
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

function extract_search_q_values(string $bytes): array
{
    // Many of these PDFs embed hyperlinks like:
    // https://www.sanmar.com/search?q=AthleticHeather&perPage=...
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

function extract_xdefault_rdf_li(string $bytes): array
{
    // Some PDFs also embed useful color names in XMP metadata:
    // <rdf:li xml:lang="x-default">Athletic Heather</rdf:li>
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

function looks_like_pms_code(string $v): bool
{
    // Common Pantone-ish codes embedded as q=127C etc.
    return (bool)preg_match('#^\d{2,5}c$#i', trim($v));
}

function split_multi(string $v): array
{
    // Some sources provide "Lime,White,Kelly" etc.
    $parts = preg_split('#,\s*#', $v) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out ?: [trim($v)];
}

function de_camel(string $s): string
{
    // AthleticHeather -> Athletic Heather, GreyConcrete -> Grey Concrete
    $s = preg_replace('#([a-z])([A-Z])#', '$1 $2', $s) ?? $s;
    // BlackTriadSolid -> Black Triad Solid
    $s = preg_replace('#([A-Z])([A-Z][a-z])#', '$1 $2', $s) ?? $s;
    return $s;
}

function normalize_color_name(string $raw): ?string
{
    $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
    if ($raw === '') return null;
    if (looks_like_pms_code($raw)) return null;

    // Weed out obvious non-color metadata.
    $lower = strtolower($raw);
    if (str_contains($lower, 'copyright') || str_contains($lower, 'all rights reserved')) return null;
    if (str_contains($raw, '©')) return null;
    if (str_contains($raw, 'D:\\') || str_contains($raw, 'd:\\')) return null;
    if (str_contains($lower, 'xmp.did:') || str_contains($lower, 'adobe:docid:')) return null;
    if (str_contains($lower, 'profiles') || $lower === 'swatch') return null;

    // Many product titles show up in metadata. Drop the most common.
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
    $s = de_camel($s);
    $s = preg_replace('#\s*/\s*#', '/', $s) ?? $s;
    $s = preg_replace('#\s+#', ' ', $s) ?? $s;
    $s = trim($s);

    if ($s === '') return null;
    if (strlen($s) > 60) return null; // sanity guard
    if (preg_match('#^[0-9./:-]+$#', $s)) return null;
    if (!preg_match('#[A-Za-z]#', $s)) return null;

    // Normalize case: if it's all lower, Title Case it.
    if ($s === strtolower($s)) {
        $s = ucwords($s);
    }

    // If it's ALL CAPS (common in embedded metadata), normalize to Title Case too.
    // This prevents case-insensitive UNIQUE collisions in global_colors (utf8mb4_unicode_ci).
    if ($s === strtoupper($s) && preg_match('#[A-Z]#', $s)) {
        $s = ucwords(strtolower($s));
    }

    // Normalize "S.Green" or "S. Green" style tokens.
    $s = preg_replace('#\bS\.\s*#', 'S. ', $s) ?? $s;

    // Preserve a few known acronyms if they occur.
    $s = preg_replace('#\bCvc\b#', 'CVC', $s) ?? $s;
    $s = preg_replace('#\bDtg\b#', 'DTG', $s) ?? $s;

    return $s;
}

function build_sanmar_color_list(): array
{
    $rawCandidates = [];

    foreach (SANMAR_PDF_SOURCES as $src) {
        $bytes = http_get_bytes($src['url']);

        foreach (extract_search_q_values($bytes) as $q) {
            foreach (split_multi($q) as $part) $rawCandidates[] = $part;
        }
        foreach (extract_xdefault_rdf_li($bytes) as $v) {
            foreach (split_multi($v) as $part) $rawCandidates[] = $part;
        }
    }

    $colors = [];
    foreach ($rawCandidates as $cand) {
        $norm = normalize_color_name($cand);
        if (!$norm) continue;
        $colors[$norm] = true;
    }

    $list = array_keys($colors);
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

function upsert_global_colors(array $colorNames): array
{
    $added = 0;
    $updated = 0;
    $skipped = [];

    foreach ($colorNames as $idx => $name) {
        $full = SANMAR_PREFIX . $name;
        if (strlen($full) > 100) {
            $skipped[] = $full;
            continue;
        }

        // Upsert by unique color_name.
        $affected = Database::execute(
            "INSERT INTO global_colors (color_name, color_code, category, description, display_order, is_active)
             VALUES (?, NULL, ?, '', 0, 1)
             ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                is_active = 1",
            [$full, SANMAR_CATEGORY]
        );

        // MySQL returns 1 for insert, 2 for update in many configs.
        if ($affected === 1) $added++;
        else if ($affected === 2) $updated++;
    }

    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
}

function upsert_sanmar_template(array $colorNames): array
{
    $template = Database::queryOne("SELECT id, is_active FROM color_templates WHERE template_name = ? LIMIT 1", [SANMAR_TEMPLATE_NAME]);
    if (!$template) {
        Database::execute(
            "INSERT INTO color_templates (template_name, description, category, is_active) VALUES (?, ?, ?, 1)",
            [SANMAR_TEMPLATE_NAME, 'SanMar imported color list (prefixed SM-).', SANMAR_CATEGORY]
        );
        $templateId = (int)Database::lastInsertId();
    } else {
        $templateId = (int)$template['id'];
        if ((int)($template['is_active'] ?? 1) !== 1) {
            Database::execute("UPDATE color_templates SET is_active = 1 WHERE id = ?", [$templateId]);
        }
        Database::execute(
            "UPDATE color_templates SET description = ?, category = ? WHERE id = ?",
            ['SanMar imported color list (prefixed SM-).', SANMAR_CATEGORY, $templateId]
        );
    }

    Database::execute("DELETE FROM color_template_items WHERE template_id = ?", [$templateId]);

    $inserted = 0;
    $skipped = [];
    $order = 1;
    foreach ($colorNames as $name) {
        $full = SANMAR_PREFIX . $name;
        if (strlen($full) > 50) {
            // color_template_items.color_name is varchar(50)
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

// -------------------------------
// Run
// -------------------------------

try {
    Database::getInstance();

    $colors = build_sanmar_color_list();
    if (empty($colors)) {
        throw new RuntimeException('No colors were extracted from SanMar sources.');
    }

    Database::beginTransaction();
    $resColors = upsert_global_colors($colors);
    $resTpl = upsert_sanmar_template($colors);
    Database::commit();

    echo "OK\n";
    echo "Extracted: " . count($colors) . " base color names\n";
    echo "Global colors upserted: added={$resColors['added']} updated={$resColors['updated']} skipped=" . count($resColors['skipped']) . "\n";
    echo "Template '" . SANMAR_TEMPLATE_NAME . "': id={$resTpl['template_id']} items_inserted={$resTpl['inserted']} skipped=" . count($resTpl['skipped']) . "\n";

    if (!empty($resColors['skipped']) || !empty($resTpl['skipped'])) {
        echo "Skipped (too long):\n";
        foreach (array_unique(array_merge($resColors['skipped'], $resTpl['skipped'])) as $s) {
            echo " - {$s}\n";
        }
    }

} catch (Throwable $e) {
    try { Database::rollBack(); } catch (Throwable $____) {}
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
