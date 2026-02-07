import { useEffect, useRef, useState } from 'react';
import { installNetworkActivityTracker, subscribeToNetworkActivity } from '../core/networkActivity.js';

const SHOW_DELAY_MS = 120;
const MIN_VISIBLE_MS = 220;

export const useGlobalProcessing = () => {
    const [isVisible, setIsVisible] = useState(false);

    const isVisibleRef = useRef(false);
    const isActiveRef = useRef(false);
    const visibleSinceRef = useRef<number | null>(null);
    const showTimeoutRef = useRef<number | null>(null);
    const hideTimeoutRef = useRef<number | null>(null);

    useEffect(() => {
        isVisibleRef.current = isVisible;
    }, [isVisible]);

    useEffect(() => {
        installNetworkActivityTracker();

        const clearPendingTimers = () => {
            if (showTimeoutRef.current) {
                window.clearTimeout(showTimeoutRef.current);
                showTimeoutRef.current = null;
            }

            if (hideTimeoutRef.current) {
                window.clearTimeout(hideTimeoutRef.current);
                hideTimeoutRef.current = null;
            }
        };

        const unsubscribe = subscribeToNetworkActivity(({ isActive }) => {
            isActiveRef.current = isActive;

            if (isActive) {
                if (showTimeoutRef.current || isVisibleRef.current) return;

                if (hideTimeoutRef.current) {
                    window.clearTimeout(hideTimeoutRef.current);
                    hideTimeoutRef.current = null;
                }

                showTimeoutRef.current = window.setTimeout(() => {
                    showTimeoutRef.current = null;
                    if (!isActiveRef.current) return;

                    visibleSinceRef.current = Date.now();
                    isVisibleRef.current = true;
                    setIsVisible(true);
                }, SHOW_DELAY_MS);

                return;
            }

            if (showTimeoutRef.current) {
                window.clearTimeout(showTimeoutRef.current);
                showTimeoutRef.current = null;
            }

            if (!isVisibleRef.current) return;

            const visibleSince = visibleSinceRef.current;
            const elapsed = visibleSince ? Date.now() - visibleSince : MIN_VISIBLE_MS;
            const remaining = Math.max(0, MIN_VISIBLE_MS - elapsed);

            hideTimeoutRef.current = window.setTimeout(() => {
                hideTimeoutRef.current = null;
                visibleSinceRef.current = null;
                isVisibleRef.current = false;
                setIsVisible(false);
            }, remaining);
        });

        return () => {
            clearPendingTimers();
            unsubscribe();
        };
    }, []);

    return { isVisible };
};

export default useGlobalProcessing;
