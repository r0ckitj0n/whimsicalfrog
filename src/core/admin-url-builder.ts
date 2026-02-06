/**
 * Builds a URL for the admin router.
 * @param section - The admin section (e.g., 'dashboard', 'inventory').
 * @param params - Optional query parameters.
 * @returns The constructed URL.
 */
export function buildAdminUrl(section: string, params: Record<string, string | number | boolean | null | undefined> = {}): string {
    const url = new URL('/admin', window.location.origin);
    url.searchParams.set('section', section);

    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null) {
            url.searchParams.set(key, String(value));
        }
    }

    return url.toString();
}
