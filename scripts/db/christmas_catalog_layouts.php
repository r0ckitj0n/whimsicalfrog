<?php
/**
 * Dense unique item layouts for Christmas Catalog pages (rooms 20–31).
 * Coordinate space: 1280 × 896 (target_aspect_ratio 1.42857).
 *
 * .area-1 / .area-2 reserved for previous / next nav plaques (bottom band).
 * Item selectors start at .area-3.
 *
 * Content band: y ≈ 36–700. Bottom nav band: y ≈ 720–880.
 */

declare(strict_types=1);

/**
 * @return list<array{id:string,top:int,left:int,width:int,height:int,selector:string}>
 */
function wf_christmas_catalog_item_slots(int $page): array
{
    $layouts = [
        // Page 1 Cover — featured top row + dense grid beneath (~40)
        1 => wf_catalog_layout_cover_feature_grid(),
        // Page 2 — classic 8×5 catalog grid
        2 => wf_catalog_layout_grid(8, 5, 36, 36, 1210, 660, 6, false),
        // Page 3 — 8×5 dense magazine grid (no stagger keeps 40)
        3 => wf_catalog_layout_grid(8, 5, 32, 36, 1210, 660, 6, false),
        // Page 4 — center diamond / cross with surrounding ring
        4 => wf_catalog_layout_center_cross(),
        // Page 5 — two vertical panels of 4×5
        5 => wf_catalog_layout_twin_panels(),
        // Page 6 — masonry staggered rows (8-7-8-7-8)
        6 => wf_catalog_layout_masonry_rows(),
        // Page 7 — circular / radial approx around center
        7 => wf_catalog_layout_radial(),
        // Page 8 — Christmas-tree pyramid (rows 3,5,7,9,11 truncated to ~40)
        8 => wf_catalog_layout_pyramid(),
        // Page 9 — diagonal cascade bands
        9 => wf_catalog_layout_diagonal_bands(),
        // Page 10 — frame border + dense inner 6×5
        10 => wf_catalog_layout_frame_border(),
        // Page 11 — asymmetric L + dense fill
        11 => wf_catalog_layout_asymmetric_l(),
        // Page 12 Finale — showcase strip + dense closer grid
        12 => wf_catalog_layout_finale(),
    ];

    $slots = $layouts[$page] ?? wf_catalog_layout_grid(8, 5, 36, 40, 1220, 650, 8, false);
    $out = [];
    $i = 0;
    foreach ($slots as $slot) {
        $i++;
        $out[] = [
            'id' => $slot['id'] ?? ("slot-{$i}"),
            'top' => (int) $slot['top'],
            'left' => (int) $slot['left'],
            'width' => (int) $slot['width'],
            'height' => (int) $slot['height'],
            'selector' => '.area-' . ($i + 2), // area-3+
        ];
    }
    return $out;
}

/**
 * @return list<array{id:string,top:int,left:int,width:int,height:int}>
 */
function wf_catalog_layout_grid(
    int $cols,
    int $rows,
    int $top0,
    int $left0,
    int $bandW,
    int $bandH,
    int $gap,
    bool $stagger
): array {
    $slots = [];
    $cellW = (int) floor(($bandW - ($cols - 1) * $gap) / $cols);
    $cellH = (int) floor(($bandH - ($rows - 1) * $gap) / $rows);
    $n = 0;
    for ($r = 0; $r < $rows; $r++) {
        $rowOffset = ($stagger && ($r % 2 === 1)) ? (int) floor($cellW * 0.2) : 0;
        $colsThis = $cols;
        if ($stagger && ($r % 2 === 1)) {
            $colsThis = $cols - 1;
        }
        for ($c = 0; $c < $colsThis; $c++) {
            $n++;
            $slots[] = [
                'id' => "g-{$n}",
                'top' => $top0 + $r * ($cellH + $gap),
                'left' => $left0 + $rowOffset + $c * ($cellW + $gap),
                'width' => $cellW,
                'height' => $cellH,
            ];
        }
    }
    return $slots;
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_cover_feature_grid(): array
{
    $slots = [];
    // Top featured row of 4 larger tiles
    $fw = 280;
    $fh = 140;
    $gap = 12;
    $left0 = 48;
    for ($i = 0; $i < 4; $i++) {
        $slots[] = [
            'id' => 'feat-' . ($i + 1),
            'top' => 36,
            'left' => $left0 + $i * ($fw + $gap),
            'width' => $fw,
            'height' => $fh,
        ];
    }
    // Dense 9×4 beneath (=36) → 40 total
    $slots = array_merge($slots, wf_catalog_layout_grid(9, 4, 192, 36, 1210, 500, 6, false));
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_center_cross(): array
{
    // Large center piece + 3 surrounding rings of small ornaments (~40).
    $slots = [];
    $slots[] = ['id' => 'cross-c', 'top' => 300, 'left' => 560, 'width' => 160, 'height' => 160];

    $rings = [
        // radius, count, size
        [150, 8, 100],
        [260, 14, 95],
        [370, 18, 90],
    ];
    $n = 0;
    foreach ($rings as [$radius, $count, $size]) {
        for ($i = 0; $i < $count; $i++) {
            $angle = deg2rad(($i / $count) * 360 - 90);
            $left = (int) round(640 + cos($angle) * $radius - $size / 2);
            $top = (int) round(380 + sin($angle) * $radius - $size / 2);
            if ($top < 28 || $top + $size > 700 || $left < 20 || $left + $size > 1260) {
                continue;
            }
            $n++;
            $slots[] = [
                'id' => "ring-{$n}",
                'top' => $top,
                'left' => $left,
                'width' => $size,
                'height' => $size,
            ];
            if (count($slots) >= 40) {
                return array_slice($slots, 0, 40);
            }
        }
    }
    // Pad with bottom strip if needed
    $pad = 0;
    while (count($slots) < 40) {
        $pad++;
        $slots[] = [
            'id' => "cross-pad-{$pad}",
            'top' => 620,
            'left' => 40 + (($pad - 1) % 10) * 120,
            'width' => 110,
            'height' => 70,
        ];
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_twin_panels(): array
{
    $left = wf_catalog_layout_grid(4, 5, 40, 40, 560, 640, 8, false);
    $right = wf_catalog_layout_grid(4, 5, 40, 680, 560, 640, 8, false);
    $i = 0;
    $out = [];
    foreach (array_merge($left, $right) as $s) {
        $i++;
        $s['id'] = "panel-{$i}";
        $out[] = $s;
    }
    return array_slice($out, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_masonry_rows(): array
{
    // Slight horizontal offsets alternate for a masonry feel while keeping 8 per row (40).
    $pattern = [8, 8, 8, 8, 8];
    $slots = [];
    $top = 36;
    $bandH = 640;
    $rowH = (int) floor(($bandH - 4 * 8) / 5);
    $n = 0;
    foreach ($pattern as $ri => $cols) {
        $gap = 8;
        $left0 = 36;
        $bandW = 1208;
        $cellW = (int) floor(($bandW - ($cols - 1) * $gap) / $cols);
        $offset = ($ri % 2 === 1) ? 10 : 0;
        for ($c = 0; $c < $cols; $c++) {
            $n++;
            $slots[] = [
                'id' => "m-{$n}",
                'top' => $top,
                'left' => $left0 + $offset + $c * ($cellW + $gap),
                'width' => $cellW,
                'height' => $rowH,
            ];
        }
        $top += $rowH + 8;
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_radial(): array
{
    $slots = [];
    $cx = 640;
    $cy = 360;
    $size = 100;
    // center
    $slots[] = ['id' => 'rad-0', 'top' => $cy - 55, 'left' => $cx - 55, 'width' => 110, 'height' => 110];
    $n = 1;
    foreach ([160, 260, 360] as $radius) {
        $count = $radius === 160 ? 8 : ($radius === 260 ? 12 : 16);
        for ($i = 0; $i < $count; $i++) {
            $angle = deg2rad(($i / $count) * 360 - 90);
            $left = (int) round($cx + cos($angle) * $radius - $size / 2);
            $top = (int) round($cy + sin($angle) * $radius - $size / 2);
            if ($top < 30 || $top + $size > 700 || $left < 20 || $left + $size > 1260) {
                continue;
            }
            $n++;
            $slots[] = [
                'id' => "rad-{$n}",
                'top' => $top,
                'left' => $left,
                'width' => $size,
                'height' => $size,
            ];
            if (count($slots) >= 40) {
                return array_slice($slots, 0, 40);
            }
        }
    }
    // fill remainder with bottom row
    while (count($slots) < 40) {
        $i = count($slots);
        $slots[] = [
            'id' => "rad-fill-{$i}",
            'top' => 620,
            'left' => 40 + ($i % 10) * 120,
            'width' => 110,
            'height' => 70,
        ];
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_pyramid(): array
{
    $rows = [4, 6, 8, 10, 12];
    $slots = [];
    $top = 40;
    $rowH = 118;
    $gap = 8;
    $n = 0;
    foreach ($rows as $cols) {
        if ($n >= 40) {
            break;
        }
        $cols = min($cols, 40 - $n);
        $cellW = (int) floor((1200 - ($cols - 1) * $gap) / $cols);
        $left0 = (int) floor((1280 - ($cellW * $cols + $gap * ($cols - 1))) / 2);
        for ($c = 0; $c < $cols; $c++) {
            $n++;
            $slots[] = [
                'id' => "py-{$n}",
                'top' => $top,
                'left' => $left0 + $c * ($cellW + $gap),
                'width' => $cellW,
                'height' => $rowH,
            ];
            if ($n >= 40) {
                break;
            }
        }
        $top += $rowH + 10;
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_diagonal_bands(): array
{
    $slots = [];
    $n = 0;
    $cell = 115;
    $gap = 8;
    for ($band = 0; $band < 5; $band++) {
        $baseTop = 40 + $band * 128;
        $shift = $band * 24;
        for ($c = 0; $c < 8; $c++) {
            $n++;
            $slots[] = [
                'id' => "diag-{$n}",
                'top' => $baseTop + (($c % 2) * 12),
                'left' => 30 + $shift + $c * ($cell + $gap),
                'width' => $cell,
                'height' => $cell,
            ];
            if ($n >= 40) {
                return $slots;
            }
        }
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_frame_border(): array
{
    $slots = [];
    $n = 0;
    $s = 108;
    $gap = 8;
    // Outer ring positions along edges
    for ($i = 0; $i < 10; $i++) { // top
        $n++;
        $slots[] = ['id' => "fr-t{$i}", 'top' => 36, 'left' => 36 + $i * ($s + $gap), 'width' => $s, 'height' => $s];
    }
    for ($i = 0; $i < 4; $i++) { // left (skip corners)
        $n++;
        $slots[] = ['id' => "fr-l{$i}", 'top' => 152 + $i * ($s + $gap), 'left' => 36, 'width' => $s, 'height' => $s];
    }
    for ($i = 0; $i < 4; $i++) { // right
        $n++;
        $slots[] = ['id' => "fr-r{$i}", 'top' => 152 + $i * ($s + $gap), 'left' => 1136, 'width' => $s, 'height' => $s];
    }
    for ($i = 0; $i < 10; $i++) { // bottom of content
        $n++;
        $slots[] = ['id' => "fr-b{$i}", 'top' => 580, 'left' => 36 + $i * ($s + $gap), 'width' => $s, 'height' => $s];
    }
    // Inner 4×3
    $inner = wf_catalog_layout_grid(4, 3, 160, 170, 940, 400, 10, false);
    foreach ($inner as $sLot) {
        $n++;
        $sLot['id'] = "fr-in-{$n}";
        $slots[] = $sLot;
        if (count($slots) >= 40) {
            break;
        }
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_asymmetric_l(): array
{
    $slots = [];
    // Tall left column featured 2×6
    $leftCol = wf_catalog_layout_grid(2, 6, 40, 36, 320, 640, 8, false);
    // Right dense 6×5
    $right = wf_catalog_layout_grid(6, 5, 40, 380, 860, 640, 8, false);
    $n = 0;
    foreach (array_merge($leftCol, $right) as $s) {
        $n++;
        $s['id'] = "asy-{$n}";
        $slots[] = $s;
        if (count($slots) >= 40) {
            break;
        }
    }
    return array_slice($slots, 0, 40);
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int}> */
function wf_catalog_layout_finale(): array
{
    $slots = [];
    // Top showcase row of 5 medium
    for ($i = 0; $i < 5; $i++) {
        $slots[] = [
            'id' => 'fin-show-' . ($i + 1),
            'top' => 36,
            'left' => 40 + $i * 240,
            'width' => 224,
            'height' => 130,
        ];
    }
    // Dense closer grid 7×5 (=35) → 40 total
    $grid = wf_catalog_layout_grid(7, 5, 184, 40, 1200, 510, 6, false);
    return array_slice(array_merge($slots, $grid), 0, 40);
}

/**
 * Previous / next nav plaques in bottom band.
 *
 * @return list<array{id:string,top:int,left:int,width:int,height:int,selector:string}>
 */
function wf_christmas_catalog_nav_slots(): array
{
    return [
        [
            'id' => 'catalog-prev-page',
            'top' => 745,
            'left' => 30,
            'width' => 200,
            'height' => 130,
            'selector' => '.area-1',
        ],
        [
            'id' => 'catalog-next-page',
            'top' => 745,
            'left' => 1050,
            'width' => 200,
            'height' => 130,
            'selector' => '.area-2',
        ],
    ];
}
