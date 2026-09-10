<?php
/**
 * Unique item + nav layouts for Christmas Catalog pages (rooms 20–31).
 * Coordinate space: 1280 × 896 (target_aspect_ratio 1.42857).
 *
 * Content band: y ≈ 40–700. Bottom nav band: y ≈ 720–880 (intentional
 * clear space for previous/next plaques — display frames stay above it).
 *
 * Page 1 (cover) is an index of all catalog pages (clickable TOC rows).
 * Pages 2–12 keep unique empty product frames.
 */

declare(strict_types=1);

/**
 * Short TOC labels baked into the cover art and used for link labels.
 *
 * @return list<array{page:int,room:string,label:string}>
 */
function wf_christmas_catalog_index_entries(): array
{
    return [
        ['page' => 1, 'room' => '20', 'label' => 'Cover & Index'],
        ['page' => 2, 'room' => '21', 'label' => 'Ornaments'],
        ['page' => 3, 'room' => '22', 'label' => 'Tree Trimmings'],
        ['page' => 4, 'room' => '23', 'label' => 'Lights & Sparkle'],
        ['page' => 5, 'room' => '24', 'label' => 'Mantel & Stockings'],
        ['page' => 6, 'room' => '25', 'label' => 'Gifts Under the Tree'],
        ['page' => 7, 'room' => '26', 'label' => 'Table & Centerpieces'],
        ['page' => 8, 'room' => '27', 'label' => 'Kitchen Cheer'],
        ['page' => 9, 'room' => '28', 'label' => 'Kids & Toys'],
        ['page' => 10, 'room' => '29', 'label' => 'Cozy Apparel'],
        ['page' => 11, 'room' => '30', 'label' => 'Outdoor Yard'],
        ['page' => 12, 'room' => '31', 'label' => 'Wishlist Finale'],
    ];
}

/**
 * Cover index row rectangles (two columns of six). Selectors start at .area-3.
 *
 * @return list<array{id:string,top:int,left:int,width:int,height:int,selector:string,page:int,room:string,label:string}>
 */
function wf_christmas_catalog_index_slots(): array
{
    $entries = wf_christmas_catalog_index_entries();
    $rowH = 78;
    $rowGap = 6;
    $startY = 168;
    $colW = 540;
    $leftX = 70;
    $rightX = 670;

    $rects = [];
    foreach ($entries as $i => $entry) {
        $col = $i < 6 ? 0 : 1;
        $row = $i % 6;
        $left = $col === 0 ? $leftX : $rightX;
        $top = $startY + $row * ($rowH + $rowGap);
        $areaIndex = 3 + $i;
        $rects[] = [
            'id' => 'index-p' . str_pad((string) $entry['page'], 2, '0', STR_PAD_LEFT),
            'top' => $top,
            'left' => $left,
            'width' => $colW,
            'height' => $rowH,
            'selector' => '.area-' . $areaIndex,
            'page' => $entry['page'],
            'room' => $entry['room'],
            'label' => $entry['label'],
        ];
    }
    return $rects;
}

/** @return list<array{id:string,top:int,left:int,width:int,height:int,selector:string}> */
function wf_christmas_catalog_item_slots(int $page): array
{
    // Cover: clickable index rows (not product frames).
    if ($page === 1) {
        $rects = [];
        foreach (wf_christmas_catalog_index_slots() as $slot) {
            $rects[] = [
                'id' => $slot['id'],
                'top' => $slot['top'],
                'left' => $slot['left'],
                'width' => $slot['width'],
                'height' => $slot['height'],
                'selector' => $slot['selector'],
            ];
        }
        return $rects;
    }

    // Item selectors start at .area-3 so .area-1/.area-2 stay reserved for nav.
    $layouts = [
        // Ornaments: clean 2×4 grid with décor margins
        2 => [
            ['id' => 'slot-1', 'top' => 70, 'left' => 70, 'width' => 260, 'height' => 280],
            ['id' => 'slot-2', 'top' => 70, 'left' => 350, 'width' => 260, 'height' => 280],
            ['id' => 'slot-3', 'top' => 70, 'left' => 630, 'width' => 260, 'height' => 280],
            ['id' => 'slot-4', 'top' => 70, 'left' => 910, 'width' => 260, 'height' => 280],
            ['id' => 'slot-5', 'top' => 390, 'left' => 70, 'width' => 260, 'height' => 280],
            ['id' => 'slot-6', 'top' => 390, 'left' => 350, 'width' => 260, 'height' => 280],
            ['id' => 'slot-7', 'top' => 390, 'left' => 630, 'width' => 260, 'height' => 280],
            ['id' => 'slot-8', 'top' => 390, 'left' => 910, 'width' => 260, 'height' => 280],
        ],
        // Tree Trimmings: tall featured left + 2×2 right
        3 => [
            ['id' => 'slot-feature', 'top' => 70, 'left' => 70, 'width' => 480, 'height' => 600],
            ['id' => 'slot-1', 'top' => 70, 'left' => 600, 'width' => 280, 'height' => 280],
            ['id' => 'slot-2', 'top' => 70, 'left' => 920, 'width' => 280, 'height' => 280],
            ['id' => 'slot-3', 'top' => 390, 'left' => 600, 'width' => 280, 'height' => 280],
            ['id' => 'slot-4', 'top' => 390, 'left' => 920, 'width' => 280, 'height' => 280],
        ],
        // Lights: three staggered columns
        4 => [
            ['id' => 'slot-1', 'top' => 70, 'left' => 70, 'width' => 340, 'height' => 400],
            ['id' => 'slot-2', 'top' => 250, 'left' => 470, 'width' => 340, 'height' => 400],
            ['id' => 'slot-3', 'top' => 70, 'left' => 870, 'width' => 340, 'height' => 400],
            ['id' => 'slot-4', 'top' => 500, 'left' => 70, 'width' => 340, 'height' => 170],
            ['id' => 'slot-5', 'top' => 70, 'left' => 470, 'width' => 340, 'height' => 160],
            ['id' => 'slot-6', 'top' => 500, 'left' => 870, 'width' => 340, 'height' => 170],
        ],
        // Mantel: four large quadrants
        5 => [
            ['id' => 'slot-1', 'top' => 70, 'left' => 70, 'width' => 540, 'height' => 280],
            ['id' => 'slot-2', 'top' => 70, 'left' => 670, 'width' => 540, 'height' => 280],
            ['id' => 'slot-3', 'top' => 390, 'left' => 70, 'width' => 540, 'height' => 280],
            ['id' => 'slot-4', 'top' => 390, 'left' => 670, 'width' => 540, 'height' => 280],
        ],
        // Gifts: top trio + bottom row of four
        6 => [
            ['id' => 'slot-1', 'top' => 70, 'left' => 70, 'width' => 360, 'height' => 300],
            ['id' => 'slot-2', 'top' => 70, 'left' => 460, 'width' => 360, 'height' => 300],
            ['id' => 'slot-3', 'top' => 70, 'left' => 850, 'width' => 360, 'height' => 300],
            ['id' => 'slot-4', 'top' => 410, 'left' => 60, 'width' => 260, 'height' => 260],
            ['id' => 'slot-5', 'top' => 410, 'left' => 350, 'width' => 260, 'height' => 260],
            ['id' => 'slot-6', 'top' => 410, 'left' => 640, 'width' => 260, 'height' => 260],
            ['id' => 'slot-7', 'top' => 410, 'left' => 930, 'width' => 260, 'height' => 260],
        ],
        // Table: center feature + four corner supports
        7 => [
            ['id' => 'slot-center', 'top' => 190, 'left' => 350, 'width' => 580, 'height' => 340],
            ['id' => 'slot-1', 'top' => 60, 'left' => 70, 'width' => 240, 'height' => 220],
            ['id' => 'slot-2', 'top' => 60, 'left' => 970, 'width' => 240, 'height' => 220],
            ['id' => 'slot-3', 'top' => 450, 'left' => 70, 'width' => 240, 'height' => 220],
            ['id' => 'slot-4', 'top' => 450, 'left' => 970, 'width' => 240, 'height' => 220],
        ],
        // Kitchen: 3×3 equal grid
        8 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 70, 'width' => 360, 'height' => 190],
            ['id' => 'slot-2', 'top' => 60, 'left' => 460, 'width' => 360, 'height' => 190],
            ['id' => 'slot-3', 'top' => 60, 'left' => 850, 'width' => 360, 'height' => 190],
            ['id' => 'slot-4', 'top' => 280, 'left' => 70, 'width' => 360, 'height' => 190],
            ['id' => 'slot-5', 'top' => 280, 'left' => 460, 'width' => 360, 'height' => 190],
            ['id' => 'slot-6', 'top' => 280, 'left' => 850, 'width' => 360, 'height' => 190],
            ['id' => 'slot-7', 'top' => 500, 'left' => 70, 'width' => 360, 'height' => 180],
            ['id' => 'slot-8', 'top' => 500, 'left' => 460, 'width' => 360, 'height' => 180],
            ['id' => 'slot-9', 'top' => 500, 'left' => 850, 'width' => 360, 'height' => 180],
        ],
        // Kids: two tall columns of three
        9 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 90, 'width' => 500, 'height' => 190],
            ['id' => 'slot-2', 'top' => 280, 'left' => 90, 'width' => 500, 'height' => 190],
            ['id' => 'slot-3', 'top' => 500, 'left' => 90, 'width' => 500, 'height' => 180],
            ['id' => 'slot-4', 'top' => 60, 'left' => 690, 'width' => 500, 'height' => 190],
            ['id' => 'slot-5', 'top' => 280, 'left' => 690, 'width' => 500, 'height' => 190],
            ['id' => 'slot-6', 'top' => 500, 'left' => 690, 'width' => 500, 'height' => 180],
        ],
        // Apparel: L-shape (tall left + stacked right)
        10 => [
            ['id' => 'slot-tall', 'top' => 60, 'left' => 70, 'width' => 460, 'height' => 610],
            ['id' => 'slot-1', 'top' => 60, 'left' => 570, 'width' => 640, 'height' => 190],
            ['id' => 'slot-2', 'top' => 280, 'left' => 570, 'width' => 300, 'height' => 190],
            ['id' => 'slot-3', 'top' => 280, 'left' => 910, 'width' => 300, 'height' => 190],
            ['id' => 'slot-4', 'top' => 500, 'left' => 570, 'width' => 640, 'height' => 180],
        ],
        // Outdoor: masonry six
        11 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 60, 'width' => 380, 'height' => 260],
            ['id' => 'slot-2', 'top' => 60, 'left' => 470, 'width' => 340, 'height' => 190],
            ['id' => 'slot-3', 'top' => 60, 'left' => 850, 'width' => 370, 'height' => 260],
            ['id' => 'slot-4', 'top' => 280, 'left' => 470, 'width' => 340, 'height' => 170],
            ['id' => 'slot-5', 'top' => 360, 'left' => 60, 'width' => 380, 'height' => 310],
            ['id' => 'slot-6', 'top' => 480, 'left' => 470, 'width' => 750, 'height' => 200],
        ],
        // Finale: center showcase + bottom trio
        12 => [
            ['id' => 'slot-showcase', 'top' => 60, 'left' => 200, 'width' => 880, 'height' => 400],
            ['id' => 'slot-1', 'top' => 500, 'left' => 70, 'width' => 360, 'height' => 180],
            ['id' => 'slot-2', 'top' => 500, 'left' => 460, 'width' => 360, 'height' => 180],
            ['id' => 'slot-3', 'top' => 500, 'left' => 850, 'width' => 360, 'height' => 180],
        ],
    ];

    $slots = $layouts[$page] ?? $layouts[2];
    $rects = [];
    $areaIndex = 3;
    foreach ($slots as $slot) {
        $rects[] = [
            'id' => $slot['id'],
            'top' => $slot['top'],
            'left' => $slot['left'],
            'width' => $slot['width'],
            'height' => $slot['height'],
            'selector' => '.area-' . $areaIndex,
        ];
        $areaIndex++;
    }
    return $rects;
}

/**
 * @return list<array{id:string,top:int,left:int,width:int,height:int,selector:string}>
 */
function wf_christmas_catalog_nav_rects(bool $hasPrev, bool $hasNext): array
{
    $rects = [];
    // Keep nav plaques below the cover index (last row ends ~y=666) with a
    // comfortable gap so hitboxes cannot collide with TOC rows.
    if ($hasPrev) {
        $rects[] = [
            'id' => 'catalog-prev-page',
            'top' => 740,
            'left' => 50,
            'width' => 200,
            'height' => 120,
            'selector' => '.area-1',
        ];
    }
    if ($hasNext) {
        $rects[] = [
            'id' => 'catalog-next-page',
            'top' => 740,
            'left' => 1030,
            'width' => 200,
            'height' => 120,
            'selector' => '.area-2',
        ];
    }
    return $rects;
}

/** Human-readable layout notes used when prompting image generation. */
function wf_christmas_catalog_layout_prompt(int $page): string
{
    $notes = [
        1 => 'Cover index layout: two columns of six clickable table-of-contents rows listing every catalog page; no product frames. Decorative holly, pine, ribbon, and ornaments in the margins outside the index panel.',
        2 => 'Ornaments layout: a neat 2-row by 4-column grid of eight equal empty rectangular product frames.',
        3 => 'Tree trimmings layout: one tall full-height empty product frame on the left half, and a 2-by-2 grid of four square empty frames on the right half.',
        4 => 'Lights layout: three staggered-height vertical columns of empty frames with mixed tall and short empty product frames creating a rhythm across the spread.',
        5 => 'Mantel layout: four large equal quadrant empty product frames in a 2-by-2 arrangement.',
        6 => 'Gifts layout: a top row of three wide empty frames, and a bottom row of four smaller equal empty frames.',
        7 => 'Table layout: one large centered empty product frame, with four smaller empty corner frames around it.',
        8 => 'Kitchen layout: a balanced 3-by-3 grid of nine equal empty rectangular product frames.',
        9 => 'Kids layout: two tall columns of three horizontal empty product frames each (six total).',
        10 => 'Apparel layout: one very tall empty frame on the left forming an L, with stacked empty frames filling the right side.',
        11 => 'Outdoor layout: masonry-style arrangement of six empty frames with mixed widths and heights, denser on the right.',
        12 => 'Finale layout: one large centered showcase empty frame on top, with three equal empty frames in a bottom row above the nav band.',
    ];
    return $notes[$page] ?? $notes[2];
}
