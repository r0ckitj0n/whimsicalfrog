import { useEffect, useCallback, useRef } from 'react';
import { PAGE } from '../core/constants.js';
import logger from '../core/logger.js';

export const useAnalytics = () => {
    const sessionStartTime = useRef(Date.now());
    const pageStartTime = useRef(Date.now());
    const maxScrollDepth = useRef(0);
    const isTracking = useRef(true);

    const getPageType = useCallback(() => {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || PAGE.LANDING;
        
        if (page === PAGE.SHOP) return PAGE.SHOP;
        if (page.startsWith('room')) return 'item_room';
        if (page === PAGE.CART) return PAGE.CART;
        if (page === PAGE.ADMIN) return PAGE.ADMIN;
        if (page === PAGE.LANDING) return PAGE.LANDING;
        
        return 'other';
    }, []);

    const getItemSku = useCallback(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('item')) return params.get('item');
        if (params.get('sku')) return params.get('sku');
        if (params.get('edit')) return params.get('edit');
        
        const itemElements = document.querySelectorAll('[data-item-id], [data-sku], [data-item-sku]');
        if (itemElements.length > 0) {
            const el = itemElements[0] as HTMLElement;
            return el.dataset.item_id || el.dataset.sku || el.dataset.item_sku;
        }
        
        return null;
    }, []);

    const sendData = useCallback(async (action: string, data: Record<string, unknown>) => {
        if (!isTracking.current) return;
        
        try {
            const response = await fetch(`/api/analytics_tracker.php?action=${encodeURIComponent(action)}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            if (!response.ok) {
                throw new Error(`Analytics request failed (${response.status})`);
            }
        } catch (error) {
            logger.warn('Analytics tracking failed:', error);
        }
    }, []);

    const sendDataSync = useCallback((action: string, data: Record<string, unknown>) => {
        if (!isTracking.current) return;

        const formData = new FormData();
        formData.append('action', action);
        formData.append('data', JSON.stringify(data));
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(`/api/analytics_tracker.php?action=${action}`, formData);
        } else {
            sendData(action, data);
        }
    }, [sendData]);

    const trackVisit = useCallback(() => {
        sendData('track_visit', {
            landing_page: window.location.href,
            referrer: document.referrer,
            timestamp: Date.now()
        });
    }, [sendData]);

    const trackPageView = useCallback(() => {
        sendData('track_page_view', {
            page_url: window.location.href,
            page_title: document.title,
            page_type: getPageType(),
            item_sku: getItemSku(),
            timestamp: Date.now()
        });
    }, [sendData, getPageType, getItemSku]);

    const trackInteraction = useCallback((type: string, event: React.MouseEvent | MouseEvent | null = null, additionalData: Record<string, unknown> = {}) => {
        if (!isTracking.current) return;
        
        let elementInfo: Record<string, string> = {};
        if (event && event.target) {
            const target = event.target as HTMLElement;
            elementInfo = {
                element_type: target.tagName.toLowerCase(),
                element_id: target.id,
                element_text: target.textContent?.substring(0, 100) || '',
                element_class: target.className
            };
        }
        
        const data: Record<string, unknown> = {
            page_url: window.location.href,
            interaction_type: type,
            ...elementInfo,
            interaction_data: {
                timestamp: Date.now(),
                page_x: (event as MouseEvent)?.clientX || 0,
                page_y: (event as MouseEvent)?.clientY || 0,
                ...additionalData
            },
            item_sku: (additionalData.item_sku as string) || getItemSku()
        };
        
        sendData('track_interaction', data);
    }, [sendData, getItemSku]);

    const trackScroll = useCallback(() => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (documentHeight <= 0) return;

        const scrollPercent = Math.round((scrollTop / documentHeight) * 100);
        
        if (scrollPercent > maxScrollDepth.current) {
            const prevDepth = maxScrollDepth.current;
            maxScrollDepth.current = scrollPercent;
            
            const milestones = [25, 50, 75, 90];
            for (const milestone of milestones) {
                if (scrollPercent >= milestone && prevDepth < milestone) {
                    trackInteraction('scroll', null, { scroll_depth: milestone });
                }
            }
        }
    }, [trackInteraction]);

    const trackItemView = useCallback((itemSku: string, timeSpent: number) => {
        sendData('track_item_view', {
            item_sku: itemSku,
            time_on_page: Math.round(timeSpent / 1000)
        });
    }, [sendData]);

    const trackCartAction = useCallback((action: string, itemSku: string) => {
        if (!itemSku) return;
        sendData('track_cart_action', {
            item_sku: itemSku,
            action: action
        });
    }, [sendData]);

    const trackPageExit = useCallback(() => {
        const timeOnPage = Math.round((Date.now() - pageStartTime.current) / 1000);
        const data = {
            page_url: window.location.href,
            time_on_page: timeOnPage,
            scroll_depth: maxScrollDepth.current,
            item_sku: getItemSku()
        };
        sendDataSync('track_page_view', data);
    }, [sendDataSync, getItemSku]);

    const trackConversion = useCallback((value: number = 0, order_id: string | number | null = null) => {
        trackInteraction('checkout_complete', null, {
            conversion_value: value,
            order_id: order_id,
            page_url: window.location.href
        });
    }, [trackInteraction]);

    const trackCustomEvent = useCallback((eventName: string, eventData: Record<string, unknown> = {}) => {
        trackInteraction('custom', null, {
            event_name: eventName,
            ...eventData
        });
    }, [trackInteraction]);

    return {
        trackVisit,
        trackPageView,
        trackInteraction,
        trackScroll,
        trackItemView,
        trackCartAction,
        trackPageExit,
        trackConversion,
        trackCustomEvent,
        enableTracking: () => { isTracking.current = true; },
        disableTracking: () => { isTracking.current = false; }
    };
};
