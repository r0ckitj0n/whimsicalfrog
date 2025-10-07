/**
 * Centralized API client for WhimsicalFrog
 * Provides get() and post() helpers and consistent error handling.
 */
'use strict';

import { ApiClient } from '../core/api-client.js';

const API_BASE = '/api/';

function buildUrl(path) {
    if (typeof path !== 'string') return path;
    if (path.startsWith('http://') || path.startsWith('https://')) return path; // absolute
    if (path.startsWith('/')) return path; // root-relative
    return `${API_BASE}${path}`; // relative -> /api/
}

async function request(method, path, data = null, options = {}) {
    const m = String(method || 'GET').toUpperCase();
    if (m === 'GET') {
        // Preserve legacy ability to pass fetch options on GET
        if (options && Object.keys(options).length > 0) {
            return ApiClient.request(buildUrl(path), { method: 'GET', ...options });
        }
        return ApiClient.get(path);
    }
    if (m === 'POST') return ApiClient.post(path, data, options);
    if (m === 'PUT') return ApiClient.put(path, data, options);
    if (m === 'DELETE') return ApiClient.delete(path, options);
    // Fallback for uncommon verbs
    const cfg = { method: m, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options };
    if (data !== null && typeof cfg.body === 'undefined') cfg.body = JSON.stringify(data);
    return ApiClient.request(buildUrl(path), cfg);
}

function get(path, options = {}) {
    // Support both (path) and (path, params) signatures.
    if (!options || Object.keys(options).length === 0) {
        return ApiClient.get(path);
    }
    // If options.params provided explicitly, treat as query params
    if (options && typeof options === 'object' && options.params && typeof options.params === 'object') {
        return ApiClient.get(path, options.params);
    }
    // Heuristic: if options doesn't contain common fetch config keys, treat it as params
    const fetchKeys = ['headers','credentials','mode','cache','redirect','referrer','referrerPolicy','integrity','keepalive','signal','method','body'];
    const looksLikeFetchOpts = Object.keys(options).some(k => fetchKeys.includes(k));
    if (!looksLikeFetchOpts) {
        return ApiClient.get(path, options);
    }
    // Otherwise, forward as fetch options
    return request('GET', path, null, options);
}

function post(path, data = null, options = {}) {
    return ApiClient.post(path, data, options);
}

const apiClient = { get, post, request };

export default apiClient;
