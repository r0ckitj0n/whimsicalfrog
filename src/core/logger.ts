// src/core/Logger.ts
/**
 * WhimsicalFrog Core – Logger (TypeScript)
 */

import ApiClient from './ApiClient.js';

const isBrowser = typeof window !== 'undefined';
const CLIENT_LOG_ENDPOINT = 'website_logs.php?action=ingest_client_logs';
const CLIENT_LOG_LEVELS = new Set(['debug', 'info', 'warn', 'error'] as const);

type LogLevel = 'debug' | 'info' | 'warn' | 'error';

interface LogEntry {
    level: LogLevel;
    message: string;
    context?: Record<string, unknown> | null;
    page_url: string | null;
}

let __wfClientLogQueue: LogEntry[] = [];
let __wfClientLogFlushTimer: ReturnType<typeof setTimeout> | null = null;
let __wfClientLogInFlight = false;
let __wfClientLogLastFlushAt = 0;
let __wfClientLogSquelch = false;

function isAdminContext(): boolean {
    try {
        const body = window.document && window.document.body;
        return !!(body && body.dataset && body.dataset.isAdmin === 'true');
    } catch { // DOM not available
        return false;
    }
}

function shouldShipLevel(level: LogLevel): boolean {
    if (level === 'warn' || level === 'error') return true;
    try {
        return window.__WF_DEBUG === true;
    } catch { // window.__WF_DEBUG not available
        return false;
    }
}

function normalizeMessage(payload: unknown): string {
    try {
        if (typeof payload === 'string') return payload;
        if (payload instanceof Error) return payload.message || String(payload);
        if (payload && typeof payload === 'object') {
            const obj = payload as Record<string, unknown>;
            if (typeof obj.msg === 'string') return obj.msg;
            return JSON.stringify(payload);
        }
        return String(payload);
    } catch { // Object serialization failed
        return '';
    }
}

function buildContext(payload: unknown): Record<string, unknown> | null {
    try {
        if (payload && typeof payload === 'object' && !(payload instanceof Error)) {
            return payload as Record<string, unknown>;
        }
        if (payload instanceof Error) {
            return { name: payload.name, message: payload.message, stack: payload.stack };
        }
    } catch { /* Payload extraction failed */ }
    return null;
}

function scheduleClientLogFlush(): void {
    if (__wfClientLogFlushTimer) return;
    __wfClientLogFlushTimer = setTimeout(() => {
        __wfClientLogFlushTimer = null;
        flushClientLogs();
    }, 750);
}

async function flushClientLogs(): Promise<void> {
    if (!isBrowser || !isAdminContext() || __wfClientLogInFlight || !__wfClientLogQueue.length) return;

    const now = Date.now();
    if (__wfClientLogLastFlushAt && (now - __wfClientLogLastFlushAt) < 2000) {
        scheduleClientLogFlush();
        return;
    }

    __wfClientLogInFlight = true;
    __wfClientLogLastFlushAt = now;
    __wfClientLogSquelch = true;

    const batch = __wfClientLogQueue.splice(0, 50);

    try {
        await ApiClient.post(CLIENT_LOG_ENDPOINT, { entries: batch });
    } catch {
        // Silently drop log shipping failures - don't log the logger
    } finally {
        __wfClientLogInFlight = false;
        __wfClientLogSquelch = false;
        if (__wfClientLogQueue.length) scheduleClientLogFlush();
    }
}

function callWfLogger(level: LogLevel, args: unknown[]): void {
    if (!isBrowser) return;

    const payload = args.length === 1 ? args[0] : args;
    const message = normalizeMessage(payload);

    // Always log to console for visibility during debugging
    const consoleMethod = level === 'error' ? 'error' : (level === 'warn' ? 'warn' : 'log');
    console[consoleMethod](`[WF-${level.toUpperCase()}]`, message, buildContext(payload) || '');

    if (isAdminContext() && !__wfClientLogSquelch && CLIENT_LOG_LEVELS.has(level) && shouldShipLevel(level)) {
        __wfClientLogQueue.push({
            level,
            message,
            context: buildContext(payload),
            page_url: window.location.href || null
        });
        if (__wfClientLogQueue.length > 200) __wfClientLogQueue = __wfClientLogQueue.slice(-200);
        scheduleClientLogFlush();
    }

    if (!window.WhimsicalFrog) return;

    try {
        const url = '/api/log_client.php';
        const body = {
            level,
            message,
            context: buildContext(payload),
            timestamp: new Date().toISOString(),
            url: window.location.href,
            ua: navigator.userAgent
        };

        if (window.WhimsicalFrog.api && typeof window.WhimsicalFrog.api.post === 'function') {
            window.WhimsicalFrog.api.post(url, body).catch(() => { });
        } else {
            // Fallback to ApiClient
            ApiClient.post(url, body).catch(() => { });
        }
    } catch { /* Legacy logger fallback failed */ }
}

const logger = {
    debug: (...args: unknown[]) => callWfLogger('debug', args),
    info: (...args: unknown[]) => callWfLogger('info', args),
    warn: (...args: unknown[]) => callWfLogger('warn', args),
    error: (...args: unknown[]) => callWfLogger('error', args),
};

export default logger;
