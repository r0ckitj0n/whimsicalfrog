import { useEffect } from 'react';
import { PAGE } from '../core/constants.js';
import logger from '../core/logger.js';

/**
 * Hook for managing dynamic page-specific module loading.
 * Migrated from legacy app.js procedural router.
 */
export const usePageRouter = () => {
    useEffect(() => {
        const routePage = async () => {
            try {
                const page = document.body?.getAttribute('data-page') || '';
                const path = (window.location.pathname || '').toLowerCase();

                // Skip router if diagnostics or minimal mode active (handled in app.js/diagnostics)
                if (window.__wfNoRouter || window.__wfDiagMinimalApp) return;

                // Always-on core utilities
                await import('../core/image-error-handler.js').catch(() => {});
                await import('../core/ui-helpers.js').catch(() => {});

                // Global components and handlers
                // Note: global-popup functionality is now handled by ModalContext/ModalBridge

                // Page-specific routing
                const isAdmin = page.startsWith(PAGE.ADMIN) || path.startsWith(`/${PAGE.ADMIN}`);
                
                if (!isAdmin && (!window.__wfAppMinimal || window.__wfAllowEvents)) {
                    // event-manager logic ported to useGlobalListeners or specific components
                }

                // Admin-only modules
                if (isAdmin) {
                    if (!window.__wfDiagNoAutosize) {
                        // Autosize logic is now handled within React components or global bridges if necessary
                    }
                }

                const isEmbed = document.body && document.body.getAttribute('data-embed') === '1';
                if (isEmbed) {
                    // Embed logic now centralized or handled via React
                }

                logger.info('[PageRouter] Entry loaded for page:', page || path);
            } catch (e) {
                logger.warn('[PageRouter] Routing error', e);
            }
        };

        routePage();
    }, []);
};

export default usePageRouter;
