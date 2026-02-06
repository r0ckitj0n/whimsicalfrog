import React from 'react';
import useRoomScaling from '../../../hooks/useRoomScaling.js';
import { ApiClient } from '../../../core/ApiClient.js';

interface RoomBodyProps {
    room_number: string | null;
    bodyRef: React.RefObject<HTMLDivElement>;
    bgStyle: React.CSSProperties;
    content: string;
    panelColor?: string;
    renderContext?: string;
    targetAspectRatio?: number | string | null;
}

export const RoomBody: React.FC<RoomBodyProps> = ({
    room_number,
    bodyRef,
    bgStyle,
    content,
    panelColor,
    renderContext = 'modal',
    targetAspectRatio
}) => {
    const isFullscreen = renderContext === 'fullscreen';

    // Parse target aspect ratio or use defaults based on context
    const tar = typeof targetAspectRatio === 'number'
        ? targetAspectRatio
        : (parseFloat(String(targetAspectRatio)) || (isFullscreen ? 1280 / 896 : 1024 / 768));

    // Determine dimensions based on context and ratio threshold
    // Use the same threshold as MapCanvas.tsx (1.4)
    const isFullScale = room_number === 'A' || room_number === '0' || tar > 1.4;
    const dims = isFullScale ? { w: 1280, h: 896 } : { w: 1024, h: 768 };

    useRoomScaling({
        bodyRef,
        originalWidth: dims.w,
        originalHeight: dims.h,
        content,
        scaleMode: 'fill'
    });

    return (
        <div
            className="room-modal-body"
            ref={bodyRef}
            style={{
                ...bgStyle,
                // Fill mode: background fills container 100%
                backgroundSize: '100% 100%',
                flex: "1 1 auto",
                overflow: 'hidden',
                width: '100%',
                minWidth: isFullscreen ? '100%' : `${dims.w * 0.5}px`, // Prevent total collapse
                minHeight: isFullscreen ? '400px' : `${dims.h * 0.5}px`,
                height: (isFullscreen || window.location.search.includes('bare=1')) ? '100%' : 'auto',
                maxWidth: '100%',
                maxHeight: '100%',
                aspectRatio: (isFullscreen || window.location.search.includes('bare=1')) ? 'auto' : `${dims.w} / ${dims.h}`,
                margin: '0 auto',
                position: 'relative'
            }}
        >
            {window.location.search.includes('edit_mode=1') && (
                <style>{`
                    .room-door, .room-sign, .atmospheric-anchor, .action-hotspot { 
                        display: none !important; 
                    }
                `}</style>
            )}
            <div
                className="room-content-inner w-full h-full"
                dangerouslySetInnerHTML={{ __html: content }}
                style={{ '--icon-panel-color': panelColor || 'transparent' } as React.CSSProperties}
            />
        </div>
    );
};
