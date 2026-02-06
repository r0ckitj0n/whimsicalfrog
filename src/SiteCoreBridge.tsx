import { useEffect } from 'react';
import { ApiClient } from './core/ApiClient.js';
import { eventBus } from './core/event-bus.js';
import logger from './core/logger.js';

interface IModuleDef {
    init?: () => void;
    [key: string]: unknown;
}

interface IModuleRecord extends IModuleDef {
    name: string;
    initialized: boolean;
}

interface ICoreState {
    version: string;
    initialized: boolean;
    debug: boolean;
    modules: Record<string, IModuleRecord>;
    config: Record<string, unknown>;
}

/**
 * SiteCoreBridge Component
 * Consolidates the unified WhimsicalFrog global object and utilities.
 * Replaces the functionality of legacy site-core.js.
 */
export const SiteCoreBridge = () => {
    useEffect(() => {
        if (typeof window === 'undefined') return;

        // Core system state
        const WF_CORE: ICoreState = {
            version: '2.1.0',
            initialized: true,
            debug: window.__WF_DEBUG || false,
            modules: {},
            config: {}
        };

        // Utility functions
        const utils = {
            debounce<T extends (...args: unknown[]) => unknown>(func: T, wait: number) {
                let timeout: ReturnType<typeof setTimeout> | null = null;
                return (...args: Parameters<T>) => {
                    const later = () => {
                        timeout = null;
                        func(...args);
                    };
                    if (timeout) clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            },

            formatCurrency(amount: number) {
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD'
                }).format(amount);
            },

            escapeHtml(text: string) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },

            generateId() {
                return '_' + Math.random().toString(36).substring(2, 11);
            }
        };

        // Module registration system (legacy support)
        const registerModule = (name: string, moduleDef: unknown) => {
            if (WF_CORE.modules[name]) {
                logger.warn(`Module '${name}' already registered, skipping`);
                return false;
            }

            if (!moduleDef || typeof moduleDef !== 'object') {
                logger.warn(`Module '${name}' registration failed: invalid module definition`);
                return false;
            }

            const typedModuleDef = moduleDef as IModuleDef;
            WF_CORE.modules[name] = {
                name,
                ...typedModuleDef,
                initialized: false
            };

            if (typedModuleDef.init && typeof typedModuleDef.init === 'function') {
                try {
                    typedModuleDef.init();
                    WF_CORE.modules[name].initialized = true;
                } catch (err) {
                    logger.error(`Failed to auto-init module '${name}':`, err);
                }
            }

            logger.info(`Module '${name}' registered via Bridge`);
            return true;
        };

        const notify = (message: string, type: GlobalNotificationType) => {
            if (window.showNotification) {
                window.showNotification(message, type);
            }
        };

        // Expose global object
        const wfApi = {
            Core: WF_CORE,
            log: logger.info,
            warn: (...args: unknown[]) => {
                const msg = args.map(arg => typeof arg === 'string' ? arg : JSON.stringify(arg)).join(' ');
                logger.warn(msg);
                notify(msg, 'warning');
            },
            error: (...args: unknown[]) => {
                const msg = args.map(arg => typeof arg === 'string' ? arg : JSON.stringify(arg)).join(' ');
                logger.error(msg);
                notify(msg, 'error');
            },
            notifySuccess: (msg: string) => {
                notify(msg, 'success');
            },
            notifyError: (msg: string) => {
                notify(msg, 'error');
            },
            setDebug: (enabled: boolean) => {
                WF_CORE.debug = enabled;
                window.__WF_DEBUG = enabled;
            },
            registerModule,
            addModule: registerModule,
            on: (event: string, cb: (...args: unknown[]) => void) => eventBus.on(event, cb),
            emit: (event: string, data: unknown) => eventBus.emit(event, data),
            off: (event: string, cb: (...args: unknown[]) => void) => eventBus.off(event, cb),
            utils,
            api: {
                request: ApiClient.request,
                get: ApiClient.get,
                post: ApiClient.post,
                put: ApiClient.put,
                delete: ApiClient.delete
            },
            getState: () => WF_CORE,
            getConfig: () => WF_CORE.config,
            getModule: (name: string) => WF_CORE.modules[name],
            ready: (callback: (wf: WhimsicalFrog) => void) => {
                if (WF_CORE.initialized) callback(wfApi as unknown as WhimsicalFrog);
                else eventBus.on('core:ready', () => callback(wfApi as unknown as WhimsicalFrog));
            }
        };

        window.WhimsicalFrog = wfApi;
        window.WF = wfApi;
        window.wf = wfApi;

        // API Aliases (legacy compatibility)
        window.apiGet = ApiClient.get;
        window.apiPost = ApiClient.post;
        window.apiRequest = ApiClient.request;
        window.apiDelete = ApiClient.delete;
        window.apiPut = ApiClient.put;
        window.apiUpload = ApiClient.upload;

        // Image height auto-setup (legacy data-height support)
        const setupImageHeights = () => {
            const images = document.querySelectorAll('img[data-height]');
            images.forEach(img => {
                const h = (img as HTMLElement).dataset.height;
                if (h) (img as HTMLElement).style.height = `${h}px`;
            });
        };

        if (document.readyState === 'complete') setupImageHeights();
        else window.addEventListener('load', setupImageHeights);

        logger.info('🎉 SiteCoreBridge: WhimsicalFrog global object initialized');
        eventBus.emit('core:ready', window.WhimsicalFrog);

    }, []);

    return null;
};
