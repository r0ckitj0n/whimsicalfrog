/**
 * Shared storefront route helpers for SPA navigation + bootstrap.
 */
export function detectPageFromLocation(pathname: string, search: string): string {
    const path = pathname.toLowerCase();
    const searchParams = new URLSearchParams(search);
    const section = searchParams.get('section') || '';
    const segments = path.split('/').filter(Boolean);
    const pageSlug = segments[0] || 'landing';

    const isAdminPath = pageSlug === 'admin' || path.includes('/admin') || path.includes('admin_router.php');
    if (isAdminPath) {
        return section ? `admin/${section}` : 'admin';
    }
    if (!segments[0] && section) {
        return `admin/${section}`;
    }
    if (searchParams.get('room_id') === 'X') {
        return 'admin/settings';
    }
    if (path.includes('/product/')) {
        return 'product';
    }
    return pageSlug;
}

export function locationNeedsShopData(pathname: string, search: string): boolean {
    const path = pathname.toLowerCase();
    const roomId = new URLSearchParams(search).get('room_id');
    return path.includes('/shop') || path.includes('/product/') || roomId === 'S';
}
