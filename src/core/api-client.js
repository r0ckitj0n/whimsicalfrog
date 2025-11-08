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

    // Call native fetch directly; no global wrappers
    const response = await fetch(url, config);
    if (!response.ok) {
      // Attempt to read server-provided error payload for richer diagnostics
      let serverMsg = '';
      let detailsStr = '';
      try {
        const ct = response.headers.get('content-type') || '';
        const bodyText = await response.text();
        if (ct.includes('application/json')) {
          try {
            const obj = bodyText ? JSON.parse(bodyText) : null;
            const msg = obj && (obj.error || obj.message || (obj.data && (obj.data.error || obj.data.message)));
            if (msg) serverMsg = String(msg);
            // Include details if present (stringify safely)
            const det = obj && (obj.details || (obj.data && obj.data.details));
            if (det) {
              try { detailsStr = ` details=${JSON.stringify(det).slice(0, 400)}`; } catch(_) { /* noop */ }
            }
            // Surface debug block to console for developers
            const dbg = (obj && obj.debug) || (obj && obj.data && obj.data.debug);
            if (dbg) {
              try { console.info('[ApiClient] server debug ->', dbg); } catch(_) {}
            }
          } catch (_) {
            // JSON parse failed; fall back to text snippet
            if (bodyText) serverMsg = bodyText.slice(0, 200);
          }
        } else if (bodyText) {
          serverMsg = bodyText.slice(0, 200);
        }
      } catch (_) { /* ignore body read errors */ }

      const baseMsg = `HTTP ${response.status}: ${response.statusText}`;
      const combined = serverMsg ? `${baseMsg} - ${serverMsg}${detailsStr}` : baseMsg;
      throw new Error(combined);
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

    const onProgress = options && typeof options.onProgress === 'function' ? options.onProgress : null;
    const signal = options && options.signal ? options.signal : null;

    // If no progress tracking requested, delegate to request()
    if (!onProgress && !signal) {
      return this.request(absolute, { method: 'POST', body: formData, headers: {}, ...options });
    }

    // Use XMLHttpRequest for progress events
    return new Promise((resolve, reject) => {
      try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', absolute);
        xhr.withCredentials = true;
        // Apply custom headers (do not set Content-Type so browser sets the multipart boundary)
        const hdrs = (options && options.headers) ? options.headers : {};
        Object.keys(hdrs).forEach((k) => {
          if (k.toLowerCase() === 'content-type') return; // skip, boundary must be set by XHR
          try { xhr.setRequestHeader(k, hdrs[k]); } catch(_) {}
        });

        if (onProgress && xhr.upload && typeof xhr.upload.addEventListener === 'function') {
          xhr.upload.addEventListener('progress', (e) => {
            try { onProgress(e); } catch(_) {}
          });
        }

        if (signal && typeof signal.addEventListener === 'function') {
          const aborter = () => { try { xhr.abort(); } catch(_) {} };
          signal.addEventListener('abort', aborter, { once: true });
        }

        xhr.onreadystatechange = () => {
          if (xhr.readyState !== 4) return;
          const ct = String(xhr.getResponseHeader('content-type') || '');
          const text = xhr.responseText || '';
          if (xhr.status >= 200 && xhr.status < 300) {
            if (ct.includes('application/json')) {
              try {
                const obj = text ? JSON.parse(text) : {};
                resolve(obj);
              } catch (e) {
                resolve({});
              }
            } else {
              resolve(text);
            }
          } else {
            // Build a similar error message to request()
            let msg = `HTTP ${xhr.status}`;
            try {
              const obj = text ? JSON.parse(text) : null;
              if (obj && (obj.error || obj.message)) msg = `${msg}: ${obj.error || obj.message}`;
            } catch(_) {}
            reject(new Error(msg));
          }
        };
        xhr.onerror = () => reject(new Error('Network error'));
        xhr.send(formData);
      } catch (err) {
        reject(err);
      }
    });
  }
}

export default ApiClient;
