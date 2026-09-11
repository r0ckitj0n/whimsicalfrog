/**
 * Christmas Catalog page rooms (holiday catalog spreads).
 * Room 6 (Christmas) opens page 1; each page is its own room with unique art.
 * Items from the assigned category auto-fill across pages in stable SKU order.
 */
export const CHRISTMAS_ROOM_NUMBER = '6';

/** Category that auto-populates the Christmas Catalog pages. */
export const CHRISTMAS_CATALOG_CATEGORY_ID = 1012;
export const CHRISTMAS_CATALOG_CATEGORY_NAME = '3D Christmas Ornaments';

/** Ordered catalog pages: room number => page label (matches renamed live pages). */
export const CHRISTMAS_CATALOG_PAGES: ReadonlyArray<{ room: string; page: number; title: string }> = [
    { room: '20', page: 1, title: 'Christmas Catalog — 3D Ornaments Cover' },
    { room: '21', page: 2, title: 'Christmas Catalog — 3D Ornaments 01' },
    { room: '22', page: 3, title: 'Christmas Catalog — 3D Ornaments 02' },
    { room: '23', page: 4, title: 'Christmas Catalog — 3D Ornaments 03' },
    { room: '24', page: 5, title: 'Christmas Catalog — 3D Ornaments 04' },
    { room: '25', page: 6, title: 'Christmas Catalog — 3D Ornaments 05' },
    { room: '26', page: 7, title: 'Christmas Catalog — 3D Ornaments 06' },
    { room: '27', page: 8, title: 'Christmas Catalog — 3D Ornaments 07' },
    { room: '28', page: 9, title: 'Christmas Catalog — 3D Ornaments 08' },
    { room: '29', page: 10, title: 'Christmas Catalog — 3D Ornaments 09' },
    { room: '30', page: 11, title: 'Christmas Catalog — 3D Ornaments 10' },
    { room: '31', page: 12, title: 'Christmas Catalog — 3D Ornaments Wishlist Finale' },
];

export const CHRISTMAS_CATALOG_ROOM_NUMBERS: ReadonlySet<string> = new Set(
    CHRISTMAS_CATALOG_PAGES.map((p) => p.room)
);

export const CHRISTMAS_CATALOG_FIRST_ROOM = CHRISTMAS_CATALOG_PAGES[0].room;

export function isChristmasCatalogRoom(roomNumber: string | null | undefined): boolean {
    if (!roomNumber) return false;
    return CHRISTMAS_CATALOG_ROOM_NUMBERS.has(String(roomNumber));
}

export function getChristmasCatalogPageIndex(roomNumber: string | null | undefined): number {
    if (!roomNumber) return -1;
    return CHRISTMAS_CATALOG_PAGES.findIndex((p) => p.room === String(roomNumber));
}
