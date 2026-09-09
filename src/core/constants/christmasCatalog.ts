/**
 * Christmas Catalog page rooms (Whimsical Frog holiday catalog page spreads).
 * Room 6 (Christmas) opens page 1; each page is its own room with unique art.
 */
export const CHRISTMAS_ROOM_NUMBER = '6';

/** Ordered catalog pages: room number => page label */
export const CHRISTMAS_CATALOG_PAGES: ReadonlyArray<{ room: string; page: number; title: string }> = [
    { room: '20', page: 1, title: 'Christmas Catalog — Cover' },
    { room: '21', page: 2, title: 'Christmas Catalog — Ornaments' },
    { room: '22', page: 3, title: 'Christmas Catalog — Tree Trimmings' },
    { room: '23', page: 4, title: 'Christmas Catalog — Lights & Sparkle' },
    { room: '24', page: 5, title: 'Christmas Catalog — Mantel & Stockings' },
    { room: '25', page: 6, title: 'Christmas Catalog — Gifts Under the Tree' },
    { room: '26', page: 7, title: 'Christmas Catalog — Table & Centerpieces' },
    { room: '27', page: 8, title: 'Christmas Catalog — Kitchen Cheer' },
    { room: '28', page: 9, title: 'Christmas Catalog — Kids & Toys' },
    { room: '29', page: 10, title: 'Christmas Catalog — Cozy Apparel' },
    { room: '30', page: 11, title: 'Christmas Catalog — Outdoor Yard' },
    { room: '31', page: 12, title: 'Christmas Catalog — Wishlist Finale' },
];

export const CHRISTMAS_CATALOG_ROOM_NUMBERS: ReadonlySet<string> = new Set(
    CHRISTMAS_CATALOG_PAGES.map((p) => p.room)
);

export const CHRISTMAS_CATALOG_FIRST_ROOM = CHRISTMAS_CATALOG_PAGES[0].room;

export function isChristmasCatalogRoom(roomNumber: string | null | undefined): boolean {
    if (!roomNumber) return false;
    return CHRISTMAS_CATALOG_ROOM_NUMBERS.has(String(roomNumber));
}
