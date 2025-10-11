// WhimsicalFrog Core – ApiClient (ES module)
// Migrated from js/utils.js to modern ES module syntax.
// Provides a lightweight wrapper around the Fetch API with sane defaults and
// helper convenience functions.

// Normalize API URLs for relative paths only. Do not alter absolute or root-relative URLs.
function ensureApiUrl(url) {
  if (typeof url !== 'string') return url;
  if (url.startsWith('http://') || url.startsWith('https://')) return url;
  if (url.startsWith('/')) return url; // keep root-relative as-is
  // Relative shorthand like 'endpoint.php' -> '/api/endpoint.php'
  return `/api/${url}`;
}

export class ApiClient {
  /**
   * Core request helper used by all verb-specific helpers.
   * @param {string} url
   * @param {RequestInit} options
   * @returns {Promise<any>} parsed JSON data
   */
  static async request(url, options = {}) {
    const defaultHeaders = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      // Mark requests as originating from ApiClient to suppress dev warnings
      'X-WF-ApiClient': '1'
    };
    // Decide credentials policy based on target origin
    let credentials = options.credentials;
    try {
      const target = new URL(url, window.location.origin);
      const sameOrigin = target.origin === window.location.origin;
      const isRootRelative = typeof url === 'string' && url.startsWith('/');
      // Recognize backend origin explicitly provided by PHP template in dev/prod
      const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : null;
      const isBackendOrigin = !!backendOrigin && target.origin === backendOrigin;
      if (!credentials) {
        credentials = (sameOrigin || isRootRelative || isBackendOrigin) ? 'include' : 'same-origin';
      }
    } catch (_) {
      credentials = credentials || 'same-origin';
    }

    const config = {
      headers: { ...defaultHeaders, ...(options.headers || {}) },
      credentials,
      ...options,
    };
    // Mark as ApiClient-originated for dev wrapper to detect
    try { Object.defineProperty(config, '_wfFromApiClient', { value: true }); } catch(_) { config._wfFromApiClient = true; }

    // Prefer original, unwrapped fetch if wrapper installed
    const _fetch = (typeof window !== 'undefined' && window.__wfOriginalFetch) ? window.__wfOriginalFetch : fetch;
    const response = await _fetch(url, config);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    // Robust parsing: tolerate empty JSON bodies and non-JSON responses.
    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');

    if (response.status === 204 || !contentType) {
      return null;
    }

    if (isJson) {
      const text = await response.text();
      const trimmed = (text || '').trim();
      if (!trimmed) return {};
      let data;
      try {
        data = JSON.parse(trimmed);
      } catch (e) {
        console.warn('[ApiClient] Invalid JSON response', { url, snippet: trimmed.slice(0, 200) });
        throw new Error('Invalid JSON response from server.');
      }
      if (data && data.success === false) {
        throw new Error(data.error || data.message || 'API request failed');
      }
      return data;
    }

    // For non-JSON successful responses, return text.
    return response.text();
  }

  static get(url, params = {}) {
    // Normalize relative paths to '/api/' but preserve absolute and root-relative URLs.
    const normalized = ensureApiUrl(url);
    const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : null;
    const base = (typeof normalized === 'string' && normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
    const urlObj = new URL(normalized, base);
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined) {
        urlObj.searchParams.append(key, params[key]);
      }
    });
    return this.request(urlObj.toString(), { method: 'GET' });
  }

  static post(url, data = {}, options = {}) {
    const normalized = ensureApiUrl(url);
    const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : null;
    const base = (typeof normalized === 'string' && normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
    const absolute = new URL(normalized, base).toString();
    return this.request(absolute, { method: 'POST', body: JSON.stringify(data), ...options });
  }

  static put(url, data = {}, options = {}) {
    const normalized = ensureApiUrl(url);
    const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : null;
    const base = (typeof normalized === 'string' && normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
    const absolute = new URL(normalized, base).toString();
    return this.request(absolute, { method: 'PUT', body: JSON.stringify(data), ...options });
  }

  static delete(url, options = {}) {
    const normalized = ensureApiUrl(url);
    const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : null;
    const base = (typeof normalized === 'string' && normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
    const absolute = new URL(normalized, base).toString();
    return this.request(absolute, { method: 'DELETE', ...options });
  }

  static upload(url, formData, options = {}) {
    // Let browser set multipart boundary.
    const normalized = ensureApiUrl(url);
    const backendOrigin = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : null;
    const base = (typeof normalized === 'string' && normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
    const absolute = new URL(normalized, base).toString();
    return this.request(absolute, { method: 'POST', body: formData, headers: {}, ...options });
  }
}

export default ApiClient;

// During development we still warn when direct fetch is used instead of ApiClient.
if (window?.location?.hostname === 'localhost' || window?.location?.hostname.includes('dev')) {
  const originalFetch = window.fetch;
  // Expose original for ApiClient to bypass wrapper
  try { window.__wfOriginalFetch = originalFetch; } catch(_) {}
  function hasApiClientHeader(h) {
    try {
      if (!h) return false;
      if (h instanceof Headers) return h.has('X-WF-ApiClient');
      if (Array.isArray(h)) return h.some(([k]) => String(k).toLowerCase() === 'x-wf-apiclient');
      if (typeof h === 'object') return Object.keys(h).some(k => String(k).toLowerCase() === 'x-wf-apiclient');
    } catch(_) {}
    return false;
  }
  window.fetch = function (...args) {
    try {
      const url = args[0];
      const opts = args[1] || {};
      const headers = opts.headers;
      // Skip warnings for ApiClient-tagged requests
      if (hasApiClientHeader(headers) || opts._wfFromApiClient) {
        return originalFetch.apply(this, args);
      }
      // Normalize URL and path check
      let path = '';
      try { const u = new URL(url, window.location.origin); path = u.pathname || ''; } catch(_) { path = (typeof url === 'string') ? url : ''; }
      if (typeof path === 'string' && path.includes('/api/')) {
        console.warn('⚠️  Consider using ApiClient instead of direct fetch for API calls:', url);
        console.warn(`Example: apiGet("${url}") or apiPost("${url}", data)`);
      }
    } catch(_) { /* noop */ }
    return originalFetch.apply(this, args);
  };
}
