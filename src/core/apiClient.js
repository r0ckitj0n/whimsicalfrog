// WhimsicalFrog Core – ApiClient (ES module)
// Migrated from js/utils.js to modern ES module syntax.
// Provides a lightweight wrapper around the Fetch API with sane defaults and
// helper convenience functions.

export class ApiClient {
  /**
   * Core request helper used by all verb-specific helpers.
   * @param {string} url
   * @param {RequestInit} options
   * @returns {Promise<any>} parsed JSON data
   */
  static async request(url, options = {}) {
    const defaultOptions = {
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    };

    const config = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...(options.headers || {})
      }
    };

    const response = await fetch(url, config);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    if (data && data.success === false) {
      throw new Error(data.error || 'API request failed');
    }
    return data;
  }

  static get(url, params = {}) {
    // Auto-prefix shorthand endpoint paths (e.g., 'get_room_data.php') with '/api/'
    if (!url.startsWith('http') && !url.startsWith('/api/')) {
      // If it already starts with '/' but lacks /api/, insert it
      url = url.startsWith('/') ? `/api${url}` : `/api/${url}`;
    }
    const urlObj = new URL(url, window.location.origin);
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined) {
        urlObj.searchParams.append(key, params[key]);
      }
    });
    return this.request(urlObj.toString(), { method: 'GET' });
  }

  static post(url, data = {}, options = {}) {
    return this.request(url, {
      method: 'POST',
      body: JSON.stringify(data),
      ...options
    });
  }

  static put(url, data = {}, options = {}) {
    return this.request(url, {
      method: 'PUT',
      body: JSON.stringify(data),
      ...options
    });
  }

  static delete(url, options = {}) {
    return this.request(url, { method: 'DELETE', ...options });
  }

  static upload(url, formData, options = {}) {
    // Let browser set multipart boundary.
    return this.request(url, { method: 'POST', body: formData, headers: {}, ...options });
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
