<?php
/**
 * Unique item + nav layouts for Christmas Catalog pages (rooms 20–31).
 * Coordinate space: 1280 × 896 (target_aspect_ratio 1.42857).
 *
 * Content band: y ≈ 40–700. Bottom nav band: y ≈ 720–880 (intentional
 * clear space for previous/next plaques — display frames stay above it).
 */

declare(strict_types=1);

/** @return list<array{id:string,top:int,left:int,width:int,height:int,selector:string}> */
function wf_christmas_catalog_item_slots(int $page): array
{
    // Item selectors start at .area-3 so .area-1/.area-2 stay reserved for nav.
    $layouts = [
        // Cover: wide hero + two supporting frames
        1 => [
            ['id' => 'slot-hero', 'top' => 70, 'left' => 160, 'width' => 960, 'height' => 430],
            ['id' => 'slot-a', 'top' => 520, 'left' => 80, 'width' => 520, 'height' => 170],
            ['id' => 'slot-b', 'top' => 520, 'left' => 680, 'width' => 520, 'height' => 170],
        ],
        // Ornaments: clean 2×4 grid
        2 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 50, 'width' => 280, 'height' => 300],
            ['id' => 'slot-2', 'top' => 60, 'left' => 350, 'width' => 280, 'height' => 300],
            ['id' => 'slot-3', 'top' => 60, 'left' => 650, 'width' => 280, 'height' => 300],
            ['id' => 'slot-4', 'top' => 60, 'left' => 950, 'width' => 280, 'height' => 300],
            ['id' => 'slot-5', 'top' => 380, 'left' => 50, 'width' => 280, 'height' => 300],
            ['id' => 'slot-6', 'top' => 380, 'left' => 350, 'width' => 280, 'height' => 300],
            ['id' => 'slot-7', 'top' => 380, 'left' => 650, 'width' => 280, 'height' => 300],
            ['id' => 'slot-8', 'top' => 380, 'left' => 950, 'width' => 280, 'height' => 300],
        ],
        // Tree Trimmings: tall featured left + 2×2 right
        3 => [
            ['id' => 'slot-feature', 'top' => 60, 'left' => 50, 'width' => 520, 'height' => 620],
            ['id' => 'slot-1', 'top' => 60, 'left' => 600, 'width' => 300, 'height' => 300],
            ['id' => 'slot-2', 'top' => 60, 'left' => 930, 'width' => 300, 'height' => 300],
            ['id' => 'slot-3', 'top' => 380, 'left' => 600, 'width' => 300, 'height' => 300],
            ['id' => 'slot-4', 'top' => 380, 'left' => 930, 'width' => 300, 'height' => 300],
        ],
        // Lights: three staggered columns
        4 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 60, 'width' => 360, 'height' => 420],
            ['id' => 'slot-2', 'top' => 250, 'left' => 460, 'width' => 360, 'height' => 420],
            ['id' => 'slot-3', 'top' => 60, 'left' => 860, 'width' => 360, 'height' => 420],
            ['id' => 'slot-4', 'top' => 500, 'left' => 60, 'width' => 360, 'height' => 180],
            ['id' => 'slot-5', 'top' => 60, 'left' => 460, 'width' => 360, 'height' => 170],
            ['id' => 'slot-6', 'top' => 500, 'left' => 860, 'width' => 360, 'height' => 180],
        ],
        // Mantel: four large quadrants
        5 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 50, 'width' => 570, 'height' => 300],
            ['id' => 'slot-2', 'top' => 60, 'left' => 660, 'width' => 570, 'height' => 300],
            ['id' => 'slot-3', 'top' => 380, 'left' => 50, 'width' => 570, 'height' => 300],
            ['id' => 'slot-4', 'top' => 380, 'left' => 660, 'width' => 570, 'height' => 300],
        ],
        // Gifts: top trio + bottom row of four
        6 => [
            ['id' => 'slot-1', 'top' => 60, 'left' => 50, 'width' => 380, 'height' => 320],
            ['id' => 'slot-2', 'top' => 60, 'left' => 450, 'width' => 380, 'height' => 320],
            ['id' => 'slot-3', 'top' => 60, 'left' => 850, 'width' => 380, 'height' => 320],
            ['id' => 'slot-4', 'top' => 410, 'left' => 40, 'width' => 280, 'height' => 270],
            ['id' => 'slot-5', 'top' => 410, 'left' => 340, 'width' => 280, 'height' => 270],
            ['id' => 'slot-6', 'top' => 410, 'left' => 640, 'width' => 280, 'height' => 270],
            ['id' => 'slot-7', 'top' => 410, 'left' => 940, 'width' => 280, 'height' => 270],
        ],
        // Table: center feature + four corner supports
        7 => [
            ['id' => 'slot-center', 'top' => 180, 'left' => 340, 'width' => 600, 'height' => 360],
            ['id' => 'slot-1', 'top' => 50, 'left' => 50, 'width' => 260, 'height' => 240],
            ['id' => 'slot-2', 'top' => 50, 'left' => 970, 'width' => 260, 'height' => 240],
            ['id' => 'slot-3', 'top' => 440, 'left' => 50, 'width' => 260, 'height' => 240],
            ['id' => 'slot-4', 'top' => 440, 'left' => 970, 'width' => 260, 'height' => 240],
        ],
        // Kitchen: 3×3 equal grid
        8 => [
            ['id' => 'slot-1', 'top' => 50, 'left' => 50, 'width' => 380, 'height' => 200],
            ['id' => 'slot-2', 'top' => 50, 'left' => 450, 'width' => 380, 'height' => 200],
            ['id' => 'slot-3', 'top' => 50, 'left' => 850, 'width' => 380, 'height' => 200],
            ['id' => 'slot-4', 'top' => 270, 'left' => 50, 'width' => 380, 'height' => 200],
            ['id' => 'slot-5', 'top' => 270, 'left' => 450, 'width' => 380, 'height' => 200],
            ['id' => 'slot-6', 'top' => 270, 'left' => 850, 'width' => 380, 'height' => 200],
            ['id' => 'slot-7', 'top' => 490, 'left' => 50, 'width' => 380, 'height' => 190],
            ['id' => 'slot-8', 'top' => 490, 'left' => 450, 'width' => 380, 'height' => 190],
            ['id' => 'slot-9', 'top' => 490, 'left' => 850, 'width' => 380, 'height' => 190],
        ],
        // Kids: two tall columns of three
        9 => [
            ['id' => 'slot-1', 'top' => 50, 'left' => 80, 'width' => 520, 'height' => 200],
            ['id' => 'slot-2', 'top' => 270, 'left' => 80, 'width' => 520, 'height' => 200],
            ['id' => 'slot-3', 'top' => 490, 'left' => 80, 'width' => 520, 'height' => 190],
            ['id' => 'slot-4', 'top' => 50, 'left' => 680, 'width' => 520, 'height' => 200],
            ['id' => 'slot-5', 'top' => 270, 'left' => 680, 'width' => 520, 'height' => 200],
            ['id' => 'slot-6', 'top' => 490, 'left' => 680, 'width' => 520, 'height' => 190],
        ],
        // Apparel: L-shape (tall left + stacked right)
        10 => [
            ['id' => 'slot-tall', 'top' => 50, 'left' => 50, 'width' => 480, 'height' => 630],
            ['id' => 'slot-1', 'top' => 50, 'left' => 560, 'width' => 660, 'height' => 200],
            ['id' => 'slot-2', 'top' => 270, 'left' => 560, 'width' => 320, 'height' => 200],
            ['id' => 'slot-3', 'top' => 270, 'left' => 900, 'width' => 320, 'height' => 200],
            ['id' => 'slot-4', 'top' => 490, 'left' => 560, 'width' => 660, 'height' => 190],
        ],
        // Outdoor: masonry six
        11 => [
            ['id' => 'slot-1', 'top' => 50, 'left' => 40, 'width' => 400, 'height' => 280],
            ['id' => 'slot-2', 'top' => 50, 'left' => 460, 'width' => 360, 'height' => 200],
            ['id' => 'slot-3', 'top' => 50, 'left' => 840, 'width' => 400, 'height' => 280],
            ['id' => 'slot-4', 'top' => 270, 'left' => 460, 'width' => 360, 'height' => 180],
            ['id' => 'slot-5', 'top' => 350, 'left' => 40, 'width' => 400, 'height' => 330],
            ['id' => 'slot-6', 'top' => 470, 'left' => 460, 'width' => 780, 'height' => 210],
        ],
        // Finale: center showcase + bottom trio
        12 => [
            ['id' => 'slot-showcase', 'top' => 50, 'left' => 180, 'width' => 920, 'height' => 420],
            ['id' => 'slot-1', 'top' => 490, 'left' => 50, 'width' => 380, 'height' => 190],
            ['id' => 'slot-2', 'top' => 490, 'left' => 450, 'width' => 380, 'height' => 190],
            ['id' => 'slot-3', 'top' => 490, 'left' => 850, 'width' => 380, 'height' => 190],
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
    if ($hasPrev) {
        $rects[] = [
            'id' => 'catalog-prev-page',
            'top' => 745,
            'left' => 30,
            'width' => 200,
            'height' => 130,
            'selector' => '.area-1',
        ];
    }
    if ($hasNext) {
        $rects[] = [
            'id' => 'catalog-next-page',
            'top' => 745,
            'left' => 1050,
            'width' => 200,
            'height' => 130,
            'selector' => '.area-2',
        ];
    }
    return $rects;
}

/** Human-readable layout notes used when prompting image generation. */
function wf_christmas_catalog_layout_prompt(int $page): string
{
    $notes = [
        1 => 'Cover layout: one wide horizontal hero empty product frame across the upper-middle, plus two wide supporting empty frames side-by-side below it.',
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
