/**
 * Centralized API client for WhimsicalFrog
 * Provides get() and post() helpers and consistent error handling.
 */
'use strict';

import { ApiClient } from '../core/apiClient.js';

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
    // If caller passes options (headers, etc.), go through request to preserve them
    if (options && Object.keys(options).length > 0) {
        return request('GET', path, null, options);
    }
    return ApiClient.get(path);
}

function post(path, data = null, options = {}) {
    return ApiClient.post(path, data, options);
}

const apiClient = { get, post, request };

export default apiClient;
