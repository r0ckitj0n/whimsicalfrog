import React, { useEffect } from 'react';
import { useHeader } from '../hooks/useHeader.js';
import logger from '../core/logger.js';

/**
 * HeaderManager Component
 * Consolidates all header-related behaviors (auth sync, height measurement, hints toggle)
 * Replaces: header-bootstrap.js, header-auth-sync.js, header-offset.js
 */
export const HeaderManager: React.FC = () => {
    const { 
        headerHeight, 
        hintsEnabled, 
        toggleHints, 
        updateHeaderHeight 
    } = useHeader();

    useEffect(() => {
        // Expose hints toggle to legacy global data-actions
        window.toggleGlobalTooltips = toggleHints;
        
        // Expose a function to trigger re-measurement manually if needed
        window.refreshHeaderHeight = updateHeaderHeight;

        logger.info('🎉 HeaderManager: Global header utilities attached');
    }, [toggleHints, updateHeaderHeight]);

    // This component doesn't render visual UI, but it could manage the Help Docs modal
    // that is currently in components/admin_nav_tabs.php if we refactor that next.
    
    return null;
};
