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

function isLegacyShopTarget(raw: string): boolean {
    const normalized = raw.replace(/^\//, '').toLowerCase();
    return normalized === 'shop' || normalized === 'shop.php';
}

/**
 * True when a click/href is heading to the shop catalog (or a product page).
 * Used to raise the shop boot overlay before the previous room photo can paint on top.
 */
export function hrefNeedsShopLoader(href: string): boolean {
    const raw = (href || '').trim();
    if (!raw || raw.startsWith('#') || raw.startsWith('mailto:') || raw.startsWith('tel:')) {
        return false;
    }
    if (isLegacyShopTarget(raw)) return true;

    try {
        const base = typeof window !== 'undefined' ? window.location.origin : 'http://wf.local';
        const url = new URL(raw, base);
        if (typeof window !== 'undefined' && url.origin !== window.location.origin) {
            return false;
        }
        return locationNeedsShopData(url.pathname, url.search);
    } catch {
        return false;
    }
}

/** SPA path for a shop door/link target (`shop.php` → `/shop`). */
export function shopNavigationHref(href: string): string {
    const raw = (href || '').trim();
    if (isLegacyShopTarget(raw)) return '/shop';
    try {
        const base = typeof window !== 'undefined' ? window.location.origin : 'http://wf.local';
        const url = new URL(raw, base);
        return `${url.pathname}${url.search}`;
    } catch {
        return '/shop';
    }
}
