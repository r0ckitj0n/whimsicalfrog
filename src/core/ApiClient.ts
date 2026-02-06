// src/core/ApiClient.ts
/**
 * WhimsicalFrog Core – ApiClient (TypeScript)
 * Provides a lightweight wrapper around the Fetch API with sane defaults.
 * v1.3.0 - Refactored for compliance (<250 lines).
 */
import { RequestOptions } from './api/types.js';
import { ensureApiUrl, getBackendOrigin } from './api/utils.js';
import { JsonResponseParser } from './api/JsonResponseParser.js';
import { ApiErrorHandler } from './api/ApiErrorHandler.js';

export class ApiClient {
    /**
     * Core request helper used by all verb-specific helpers.
     */
    static async request<T = unknown>(url: string, options: RequestOptions = {}): Promise<T> {
        const cacheBuster = `cb=${Date.now()}`;
        const finalUrl = url.includes('?') ? `${url}&${cacheBuster}` : `${url}?${cacheBuster}`;

        const defaultHeaders: Record<string, string> = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-WF-ApiClient': '1'
        };

        // Automatic dev bypass for localhost
        const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (isLocalhost) {
            defaultHeaders['X-WF-Dev-Admin'] = '1';
        }

        let credentials = options.credentials;
        try {
            const target = new URL(url, window.location.origin);
            const sameOrigin = target.origin === window.location.origin;
            const backendOrigin = getBackendOrigin();
            const isBackendOrigin = !!backendOrigin && target.origin === backendOrigin;

            if (!credentials) {
                credentials = (sameOrigin || url.startsWith('/') || isBackendOrigin) ? 'include' : 'same-origin';
            }
        } catch {
            // URL parsing failed (e.g., relative URL) - use safe default
            credentials = credentials || 'same-origin';
        }

        const mergedHeaders = { ...defaultHeaders, ...(options.headers as Record<string, string> || {}) };
        if (options.body instanceof FormData) delete mergedHeaders['Content-Type'];

        const config: RequestOptions = { ...options, headers: mergedHeaders, credentials };

        const response = await fetch(finalUrl, config);

        if (!response.ok) await ApiErrorHandler.handle(response);
        if (options.responseType === 'text') return (await response.text()) as unknown as T;

        const contentType = response.headers.get('content-type') || '';
        if (response.status === 204 || !contentType) return null as unknown as T;
        if (contentType.includes('application/json')) return await JsonResponseParser.parse<T>(response);

        return (await response.text()) as unknown as T;
    }

    static get<T = unknown>(url: string, params: Record<string, any> = {}): Promise<T> {
        const normalized = ensureApiUrl(url);
        const backendOrigin = getBackendOrigin();
        const base = (normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
        const urlObj = new URL(normalized, base);

        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined) urlObj.searchParams.append(k, String(v));
        });
        return this.request<T>(urlObj.toString(), { method: 'GET' });
    }

    static post<T = unknown>(url: string, data: unknown = {}, opts: RequestOptions = {}): Promise<T> {
        return this.request<T>(this.getAbsUrl(url), { method: 'POST', body: JSON.stringify(data), ...opts });
    }

    static put<T = unknown>(url: string, data: unknown = {}, opts: RequestOptions = {}): Promise<T> {
        return this.request<T>(this.getAbsUrl(url), { method: 'PUT', body: JSON.stringify(data), ...opts });
    }

    static delete<T = unknown>(url: string, opts: RequestOptions = {}): Promise<T> {
        return this.request<T>(this.getAbsUrl(url), { method: 'DELETE', ...opts });
    }

    private static getAbsUrl(url: string): string {
        const normalized = ensureApiUrl(url);
        const backendOrigin = getBackendOrigin();
        const base = (normalized.startsWith('/') && backendOrigin) ? backendOrigin : window.location.origin;
        return new URL(normalized, base).toString();
    }

    static async upload<T = unknown>(url: string, formData: FormData, options: RequestOptions = {}): Promise<T> {
        const absolute = this.getAbsUrl(url);
        if (!options.onProgress && !options.signal) {
            return this.request<T>(absolute, { method: 'POST', body: formData, ...options });
        }

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', absolute);
            xhr.withCredentials = true;

            const hdrs = (options.headers as Record<string, string>) || {};
            Object.entries(hdrs).forEach(([k, v]) => {
                if (k.toLowerCase() !== 'content-type') xhr.setRequestHeader(k, v);
            });

            if (options.onProgress && xhr.upload) xhr.upload.addEventListener('progress', options.onProgress);
            if (options.signal) options.signal.addEventListener('abort', () => xhr.abort(), { once: true });

            xhr.onreadystatechange = async () => {
                if (xhr.readyState !== 4) return;
                const text = xhr.responseText || '';
                if (xhr.status >= 200 && xhr.status < 300) {
                    const ct = String(xhr.getResponseHeader('content-type') || '');
                    if (ct.includes('application/json')) {
                        try { resolve(JSON.parse(text)); } catch (e) { resolve({} as T); }
                    } else { resolve(text as unknown as T); }
                } else {
                    let msg = `HTTP ${xhr.status}`;
                    try {
                        const obj = text ? JSON.parse(text) : null;
                        if (obj && (obj.error || obj.message)) msg = `${msg}: ${obj.error || obj.message}`;
                    } catch { /* JSON parse failed - use basic message */ }
                    reject(new Error(msg));
                }
            };
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.send(formData);
        });
    }
}

export default ApiClient;

