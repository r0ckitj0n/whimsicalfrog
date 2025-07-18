/*
 * Centralized API client wrapper for WhimsicalFrog
 * Provides apiGet() and apiPost() helpers and encourages consistent error handling.
 */
(function (global) {
    'use strict';

    const API_BASE = '/api/';

    // Preserve original fetch for internal use / fallback
    const nativeFetch = global.fetch.bind(global);

    function buildUrl(path) {
        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path; // absolute URL
        }
        if (path.startsWith('/')) {
            return path; // already root-relative (e.g. /api/foo.php)
        }
        // Relative path like 'get_data.php'
        return API_BASE + path.replace(/^\/?/, '');
    }

    async function apiRequest(method, path, data = null, options = {}) {
        const url = buildUrl(path);
        const config = {
            method,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {})
            },
            credentials: 'same-origin',
            ...options
        };

        if (method !== 'GET' && data !== null) {
            config.body = JSON.stringify(data);
        }

        const response = await nativeFetch(url, config);

        // Attempt to parse JSON; fall back to text
        const contentType = response.headers.get('content-type') || '';
        const parseBody = contentType.includes('application/json')
            ? response.json.bind(response)
            : response.text.bind(response);

        if (!response.ok) {
            const body = await parseBody();
            const message = typeof body === 'string' ? body : JSON.stringify(body);
            throw new Error(`API error ${response.status}: ${message}`);
        }

        return parseBody();
    }

    function apiGet(path, options = {}) {
        return apiRequest('GET', path, null, options);
    }

    function apiPost(path, data = null, options = {}) {
        return apiRequest('POST', path, data, options);
    }

    // For FormData or sendBeacon payloads
    function apiPostForm(path, formData, options = {}) {
        const url = buildUrl(path);
        const config = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            ...options
        };
        return nativeFetch(url, config).then(r => r.ok ? r.text() : Promise.reject(new Error(`API error ${r.status}`)));
    }

    // Expose helpers globally
    global.apiGet = apiGet;
    global.apiPost = apiPost;
    global.apiPostForm = apiPostForm;

    // Monkey-patch fetch to warn about direct API calls.
    global.fetch = function (input, init = {}) {
        const url = typeof input === 'string' ? input : input.url;
        if (/\/api\//.test(url)) {
            console.warn('⚠️  Consider using apiGet/apiPost instead of direct fetch for API calls:', url);
        }
        return nativeFetch(input, init);
    };

    console.log('[api-client] Initialized');
})(window);
