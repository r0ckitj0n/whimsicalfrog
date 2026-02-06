import { useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { useApp, type ITooltipData } from '../../context/AppContext.js';
export { type ITooltipData };

export const useTooltips = () => {
    const {
        hintsEnabled: enabled,
        toggleHints: toggleTooltips,
        tooltips,
        setTooltips,
        activeTooltip,
        setActiveTooltip
    } = useApp();

    const fetchTooltips = useCallback(async (context: string) => {
        try {
            const res = await ApiClient.get<{ success: boolean; tooltips: ITooltipData[] }>('/api/help_tooltips.php', { page_context: context });
            if (res && res.success) {
                setTooltips(prev => {
                    const existingIds = new Set(prev.map(t => t.id));
                    const newItems = res.tooltips.filter(t => !existingIds.has(t.id));
                    return [...prev, ...newItems];
                });
            }
        } catch (err) {
            logger.error('[useTooltips] fetch failed', err);
        }
    }, [setTooltips]);

    const showTooltip = useCallback((target: HTMLElement, data: ITooltipData) => {
        setActiveTooltip({ target, data });
    }, [setActiveTooltip]);

    const hideTooltip = useCallback(() => {
        setActiveTooltip(null);
    }, [setActiveTooltip]);

    return {
        enabled,
        tooltips,
        activeTooltip,
        fetchTooltips,
        showTooltip,
        hideTooltip,
        toggleTooltips
    };
};
