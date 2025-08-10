/**
 * Centralized API client for WhimsicalFrog
 * Provides get() and post() helpers and consistent error handling.
 */
'use strict';

const API_BASE = '/api/';

function buildUrl(path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path; // Absolute URL, do not modify
    }
    if (path.startsWith('/')) {
        return path; // Already root-relative (e.g., /functions/process_login.php)
    }
    // Relative path (e.g., 'get_data.php')
    return `${API_BASE}${path}`;
}

async function request(method, path, data = null, options = {}) {
    const url = buildUrl(path);
    const config = {
        method,
        headers: {
            'Content-Type': 'application/json',
            ...options.headers,
        },
        credentials: 'same-origin',
        ...options,
    };

    if (method !== 'GET' && data !== null) {
        config.body = JSON.stringify(data);
    }

    const response = await fetch(url, config);

    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');

    if (!response.ok) {
        let errorBody;
        try {
            errorBody = isJson ? await response.json() : await response.text();
        } catch (e) {
            errorBody = 'Could not parse error response.';
        }

        let message = 'An unknown error occurred.';
        if (typeof errorBody === 'string') {
            message = errorBody;
        } else if (errorBody && errorBody.message) {
            message = errorBody.message;
        } else {
            message = JSON.stringify(errorBody);
        }

        throw new Error(`API Error (${response.status}): ${message}`);
    }

    // If response is OK but empty (e.g. 204 No Content), return null for JSON to avoid parsing errors
    if (response.status === 204 || !contentType) {
        return null;
    }

    return isJson ? response.json() : response.text();
}

function get(path, options = {}) {
    return request('GET', path, null, options);
}

function post(path, data = null, options = {}) {
    return request('POST', path, data, options);
}

const apiClient = {
    get,
    post,
    request,
};

export default apiClient;


