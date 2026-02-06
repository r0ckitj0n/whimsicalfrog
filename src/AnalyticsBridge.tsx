import { useEffect } from 'react';
import { useAnalytics } from './hooks/useAnalytics.js';
import logger from './core/logger.js';

/**
 * AnalyticsBridge Component
 * Exposes the React analytics system to the legacy window objects.
 * Replaces the functionality of legacy analytics.js.
 */
export const AnalyticsBridge = () => {
    const analytics = useAnalytics();

    useEffect(() => {
        if (typeof window === 'undefined') return;

        const handleScroll = () => analytics.trackScroll();
        const handleVisibilityChange = () => {
            if (document.hidden) analytics.trackPageExit();
        };
        const handleBeforeUnload = () => analytics.trackPageExit();

        // 1. Initial tracking
        analytics.trackVisit();
        analytics.trackPageView();

        // 2. Event Listeners
        window.addEventListener('scroll', handleScroll, { passive: true });
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('beforeunload', handleBeforeUnload);

        // 3. Periodic updates (every 30s)
        const interval = setInterval(() => {
            if (!document.hidden) analytics.trackPageView();
        }, 30000);

        // 4. Global API exposure
        window.trackConversion = (value: number, order_id: string | number | null) => analytics.trackConversion(value, order_id);
        window.trackCustomEvent = (name: string, data: Record<string, unknown>) => analytics.trackCustomEvent(name, data);
        window.optOutOfAnalytics = () => {
            localStorage.setItem('analytics_opt_out', 'true');
            analytics.disableTracking();
        };
        window.optInToAnalytics = () => {
            localStorage.removeItem('analytics_opt_out');
            analytics.enableTracking();
        };

        // Attach to legacy analyticsTracker object if code expects it
        window.analyticsTracker = {
            trackConversion: analytics.trackConversion,
            trackCustomEvent: analytics.trackCustomEvent,
            enableTracking: analytics.enableTracking,
            disableTracking: analytics.disableTracking
        };

        logger.info('🎉 AnalyticsBridge: Global tracking initialized');

        return () => {
            window.removeEventListener('scroll', handleScroll);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            window.removeEventListener('beforeunload', handleBeforeUnload);
            clearInterval(interval);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- analytics methods are stable refs, run once on mount
    }, []);

    return null;
};
