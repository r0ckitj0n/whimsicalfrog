import React, { useEffect, useRef } from 'react';
import { useTooltips, ITooltipData } from '../hooks/admin/useTooltips.js';
import { PAGE } from '../core/constants.js';

/**
 * TooltipManager Component
 * Manages global tooltip attachment and lifecycle.
 */
export const TooltipManager: React.FC = () => {
    const {
        enabled,
        tooltips,
        fetchTooltips,
        showTooltip,
        hideTooltip,
        toggleTooltips
    } = useTooltips();

    const attachedTargets = useRef<WeakSet<HTMLElement>>(new WeakSet());
    const timeoutRef = useRef<number | null>(null);

    useEffect(() => {
        // Fetch common and admin tooltips on mount
        fetchTooltips('common');
        fetchTooltips(PAGE.ADMIN);

        // Also fetch current page context tooltips
        const searchParams = new URLSearchParams(window.location.search);
        const section = searchParams.get('section');
        if (section) fetchTooltips(section);

        // Expose toggle to legacy code
        window.toggleGlobalTooltips = toggleTooltips;
    }, [fetchTooltips, toggleTooltips]);

    useEffect(() => {
        if (!enabled || tooltips.length === 0) return;

        const attachToElement = (el: HTMLElement, data: ITooltipData) => {
            if (attachedTargets.current.has(el)) return;

            const handleEnter = () => {
                // Obscuration Check: Ensure the element isn't hidden behind a modal overlay
                const rect = el.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const topElement = document.elementFromPoint(centerX, centerY);

                if (topElement && !el.contains(topElement)) {
                    // Something is covering this element (likely a modal)
                    return;
                }

                if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
                timeoutRef.current = window.setTimeout(() => {
                    showTooltip(el, data);
                }, 1000);
            };

            const handleLeave = () => {
                if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
                hideTooltip();
            };

            el.addEventListener('mouseenter', handleEnter);
            el.addEventListener('mouseleave', handleLeave);
            el.addEventListener('focus', handleEnter);
            el.addEventListener('blur', handleLeave);

            attachedTargets.current.add(el);
        };

        const scanAndAttach = () => {
            tooltips.forEach(data => {
                // Try ID
                const el = document.getElementById(data.element_id);
                if (el) attachToElement(el, data);

                // Try data-action
                document.querySelectorAll(`[data-action="${data.element_id}"]`).forEach(node => {
                    attachToElement(node as HTMLElement, data);
                });

                // Try data-help-id
                document.querySelectorAll(`[data-help-id="${data.element_id}"]`).forEach(node => {
                    attachToElement(node as HTMLElement, data);
                });
            });
        };

        // Initial scan
        scanAndAttach();

        // Observe for dynamic elements
        const observer = new MutationObserver(() => {
            scanAndAttach();
        });

        observer.observe(document.body, { childList: true, subtree: true });

        return () => {
            observer.disconnect();
            if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
            hideTooltip();
        };
    }, [enabled, tooltips, showTooltip, hideTooltip]);

    return null;
};
