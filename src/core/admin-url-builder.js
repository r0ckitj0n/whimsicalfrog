/**
 * Builds a URL for the admin router.
 * @param {string} section - The admin section (e.g., 'dashboard', 'inventory').
 * @param {Object.<string, string>} [params] - Optional query parameters.
 * @returns {string} The constructed URL.
 */
export function buildAdminUrl(section, params = {}) {
  const url = new URL('/sections/admin_router.php', window.location.origin);
  url.searchParams.set('section', section);

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null) {
      url.searchParams.set(key, value);
    }
  }

  return url.toString();
}
