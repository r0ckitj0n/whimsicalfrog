export function normalizeAssetUrl(path) {
  if (!path || typeof path !== 'string') return '';
  try {
    const base = window.location?.origin || 'http://localhost';
    const url = new URL(path, base);
    if (url.hostname === '127.0.0.1') url.hostname = 'localhost';
    if (typeof window !== 'undefined' && window.location) {
      url.protocol = window.location.protocol;
    }
    return url.toString();
  } catch (err) {
    console.warn('[asset-utils] Invalid asset URL dropped:', path, err);
    return '';
  }
}

export function removeBrokenImage(node) {
  if (!node) return;
  try {
    node.remove();
  } catch (err) {
    console.warn('[asset-utils] Failed to remove broken image node', err);
  }
}

export function attachStrictImageGuards(container, selector = 'img') {
  if (!container || container._wfStrictImageGuard) return;
  const handler = (event) => {
    const target = event?.target;
    if (!target || typeof target.matches !== 'function') return;
    if (target.matches(selector)) {
      removeBrokenImage(target);
    }
  };
  container.addEventListener('error', handler, true);
  container._wfStrictImageGuard = handler;
}
