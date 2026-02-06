import React from 'react';
import { createPortal } from 'react-dom';
import { useTooltips } from '../hooks/admin/useTooltips.js';

/**
 * Tooltip UI Component
 * Renders the active tooltip based on state from useTooltips
 */
export const Tooltip: React.FC = () => {
    const { activeTooltip, enabled } = useTooltips();
    const tooltipRef = React.useRef<HTMLDivElement>(null);
    const [coords, setCoords] = React.useState({ top: 0, left: 0, flip: false });

    React.useLayoutEffect(() => {
        if (!enabled || !activeTooltip || !tooltipRef.current) return;

        const { target } = activeTooltip;
        const rect = target.getBoundingClientRect();
        const tooltip = tooltipRef.current.getBoundingClientRect();

        const padding = 10;
        let top = rect.bottom + padding;
        let left = rect.left + (rect.width / 2);
        let flip = false;

        // Flip to top if overflowing bottom
        if (top + tooltip.height > window.innerHeight) {
            top = rect.top - tooltip.height - padding;
            flip = true;
        }

        // Bounding check for horizontal overflow
        const halfWidth = tooltip.width / 2;
        if (left - halfWidth < padding) {
            left = halfWidth + padding;
        } else if (left + halfWidth > window.innerWidth - padding) {
            left = window.innerWidth - halfWidth - padding;
        }

        setCoords({ top, left, flip });
    }, [activeTooltip, enabled]);

    if (!enabled || !activeTooltip) return null;

    const { data } = activeTooltip;

    const styles: React.CSSProperties = {
        top: coords.top,
        left: coords.left,
        transform: 'translateX(-50%)',
        visibility: coords.top === 0 ? 'hidden' : 'visible'
    };

    return createPortal(
        <div ref={tooltipRef} className={`wf-tooltip ${coords.flip ? 'flipped' : ''}`} style={styles}>
            <div className="wf-tooltip-title">{data.title}</div>
            <div className="wf-tooltip-content">{data.content}</div>
        </div>,
        document.body
    );
};
