<?php
/**
 * Shared Christmas Catalog page metadata (rooms 20–31).
 */

declare(strict_types=1);

const WF_CHRISTMAS_ROOM = '6';
const WF_CHRISTMAS_CATALOG_CATEGORY_ID = 1012;

/**
 * @return list<array{room:string,page:int,title:string,file:string}>
 */
function wf_christmas_catalog_pages(): array
{
    // Pages 01–10 share one display title on purpose (looked up by room number, not name).
    return [
        ['room' => '20', 'page' => 1, 'title' => 'Christmas Catalog — 3D Ornaments Cover', 'file' => 'realistic-room20-christmas-catalog-p01'],
        ['room' => '21', 'page' => 2, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room21-christmas-catalog-p02'],
        ['room' => '22', 'page' => 3, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room22-christmas-catalog-p03'],
        ['room' => '23', 'page' => 4, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room23-christmas-catalog-p04'],
        ['room' => '24', 'page' => 5, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room24-christmas-catalog-p05'],
        ['room' => '25', 'page' => 6, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room25-christmas-catalog-p06'],
        ['room' => '26', 'page' => 7, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room26-christmas-catalog-p07'],
        ['room' => '27', 'page' => 8, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room27-christmas-catalog-p08'],
        ['room' => '28', 'page' => 9, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room28-christmas-catalog-p09'],
        ['room' => '29', 'page' => 10, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room29-christmas-catalog-p10'],
        ['room' => '30', 'page' => 11, 'title' => 'Christmas Catalog — 3D Ornaments', 'file' => 'realistic-room30-christmas-catalog-p11'],
        ['room' => '31', 'page' => 12, 'title' => 'Christmas Catalog — 3D Ornaments Wishlist Finale', 'file' => 'realistic-room31-christmas-catalog-p12'],
    ];
}

function wf_is_christmas_catalog_room(?string $roomNumber): bool
{
    if ($roomNumber === null || $roomNumber === '') {
        return false;
    }
    foreach (wf_christmas_catalog_pages() as $page) {
        if ($page['room'] === (string) $roomNumber) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{room:string,page:int,title:string,file:string}|null
 */
function wf_christmas_catalog_page_meta(?string $roomNumber): ?array
{
    foreach (wf_christmas_catalog_pages() as $page) {
        if ($page['room'] === (string) $roomNumber) {
            return $page;
        }
    }
    return null;
}

/**
 * Ordered list of item-slot selectors for a catalog page (from active room_map, fallback to layout file).
 *
 * @return list<string>
 */
function wf_christmas_catalog_item_selectors_for_room(string $roomNumber): array
{
    require_once __DIR__ . '/../scripts/db/christmas_catalog_layouts.php';

    $meta = wf_christmas_catalog_page_meta($roomNumber);
    if ($meta === null) {
        return [];
    }

    $coordsRow = Database::queryOne(
        'SELECT coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
        [$roomNumber]
    );
    $selectors = [];
    if ($coordsRow && !empty($coordsRow['coordinates'])) {
        $decoded = json_decode((string) $coordsRow['coordinates'], true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        $rects = [];
        if (is_array($decoded)) {
            if (isset($decoded['rectangles']) && is_array($decoded['rectangles'])) {
                $rects = $decoded['rectangles'];
            } else {
                $rects = $decoded;
            }
        }
        foreach ($rects as $rect) {
            $sel = (string) ($rect['selector'] ?? '');
            if (preg_match('/^\.area-(\d+)$/', $sel, $m) && (int) $m[1] >= 3) {
                $selectors[(int) $m[1]] = $sel;
            }
        }
        ksort($selectors, SORT_NUMERIC);
        if (!empty($selectors)) {
            return array_values($selectors);
        }
    }

    $slots = wf_christmas_catalog_item_slots((int) $meta['page']);
    return array_values(array_map(static fn(array $s): string => $s['selector'], $slots));
}

/**
 * Cumulative item offset for a catalog room.
 * Spreads the category evenly across all catalog pages so every renamed page shows items.
 */
function wf_christmas_catalog_item_offset(string $roomNumber): int
{
    $meta = wf_christmas_catalog_page_meta($roomNumber);
    if ($meta === null) {
        return 0;
    }

    $pages = wf_christmas_catalog_pages();
    $pageCount = count($pages);
    $pageIndex = max(0, ((int) $meta['page']) - 1);

    // Prefer live category count; fall back to slot packing if unavailable.
    $totalItems = 0;
    try {
        $row = Database::queryOne(
            "SELECT COUNT(*) AS c
             FROM items
             WHERE category_id = ?
               AND status = 'live'
               AND is_archived = 0
               AND is_active = 1",
            [WF_CHRISTMAS_CATALOG_CATEGORY_ID]
        );
        $totalItems = (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        $totalItems = 0;
    }

    if ($totalItems <= 0) {
        $offset = 0;
        foreach ($pages as $page) {
            if ($page['room'] === $roomNumber) {
                break;
            }
            $offset += count(wf_christmas_catalog_item_selectors_for_room($page['room']));
        }
        return $offset;
    }

    $base = intdiv($totalItems, $pageCount);
    $rem = $totalItems % $pageCount;
    // First $rem pages get one extra item.
    $offset = 0;
    for ($i = 0; $i < $pageIndex; $i++) {
        $offset += $base + ($i < $rem ? 1 : 0);
    }
    return $offset;
}

/**
 * How many items this catalog page should take from the category list.
 */
function wf_christmas_catalog_item_limit(string $roomNumber): int
{
    $meta = wf_christmas_catalog_page_meta($roomNumber);
    if ($meta === null) {
        return 0;
    }
    $slotCount = count(wf_christmas_catalog_item_selectors_for_room($roomNumber));
    $pages = wf_christmas_catalog_pages();
    $pageCount = count($pages);
    $pageIndex = max(0, ((int) $meta['page']) - 1);

    $totalItems = 0;
    try {
        $row = Database::queryOne(
            "SELECT COUNT(*) AS c
             FROM items
             WHERE category_id = ?
               AND status = 'live'
               AND is_archived = 0
               AND is_active = 1",
            [WF_CHRISTMAS_CATALOG_CATEGORY_ID]
        );
        $totalItems = (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        $totalItems = 0;
    }

    if ($totalItems <= 0) {
        return $slotCount;
    }

    $base = intdiv($totalItems, $pageCount);
    $rem = $totalItems % $pageCount;
    $want = $base + ($pageIndex < $rem ? 1 : 0);
    return max(0, min($slotCount, $want));
}
