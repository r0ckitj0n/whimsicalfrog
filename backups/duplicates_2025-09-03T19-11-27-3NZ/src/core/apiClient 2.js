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
      'X-Requested-With': 'XMLHttpRequest'
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

    const response = await fetch(url, config);
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
        throw new Error(data.error || 'API request failed');
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

// Convenience wrapper functions – mirror legacy global helpers using static methods.
export const apiGet = (url, params) => ApiClient.get(url, params);
export const apiPost = (url, data, options) => ApiClient.post(url, data, options);
export const apiPut = (url, data, options) => ApiClient.put(url, data, options);
export const apiDelete = (url, options) => ApiClient.delete(url, options);
export const uploadFile = (url, formData, options) => ApiClient.upload(url, formData, options);

// During development we still warn when direct fetch is used instead of ApiClient.
if (window?.location?.hostname === 'localhost' || window?.location?.hostname.includes('dev')) {
  const originalFetch = window.fetch;
  window.fetch = function (...args) {
    if (typeof args[0] === 'string' && args[0].includes('/api/')) {
      console.warn('⚠️  Consider using ApiClient instead of direct fetch for API calls:', args[0]);
      console.warn(`Example: apiGet("${args[0]}") or apiPost("${args[0]}", data)`);
    }
    return originalFetch.apply(this, args);
  };
}
