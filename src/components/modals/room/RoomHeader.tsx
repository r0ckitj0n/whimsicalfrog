import React from 'react';
import {
    CHRISTMAS_ROOM_NUMBER,
    isChristmasCatalogRoom,
} from '../../../core/constants/christmasCatalog.js';

interface RoomHeaderProps {
    room_number: string | null;
    room_name?: string;
    category?: string;
    panelColor?: string;
    onClose: () => void;
}

function openChristmasRoom(): void {
    const target = CHRISTMAS_ROOM_NUMBER;
    if (window.roomModalManager?.show) {
        window.roomModalManager.show(target);
        return;
    }
    if (typeof window.openRoom === 'function') {
        window.openRoom(target);
        return;
    }
    window.location.href = `/room_main?room=${encodeURIComponent(target)}`;
}

export const RoomHeader: React.FC<RoomHeaderProps> = ({
    room_number,
    room_name,
    category,
    panelColor,
    onClose,
}) => {
    const showCatalogBack = isChristmasCatalogRoom(room_number);

    return (
        <div
            className="room-modal-header"
            style={{
                flex: '0 0 auto',
                position: 'absolute',
                top: 0,
                left: 0,
                right: 0,
                zIndex: 100,
                background: 'transparent',
                display: 'flex',
                alignItems: 'flex-start',
                justifyContent: 'space-between',
                padding: '10px 20px',
                pointerEvents: 'none',
            }}
        >
            <div
                className="back-button-container"
                style={{ pointerEvents: 'auto', position: 'relative', top: 'unset', left: 'unset' }}
            >
                {showCatalogBack ? (
                    <button
                        type="button"
                        className="room-modal-back-btn"
                        onClick={openChristmasRoom}
                        aria-label="Back to Christmas Room"
                        data-help-id="christmas-catalog-back"
                    >
                        Back to Christmas Room
                    </button>
                ) : (
                    <button
                        type="button"
                        className="admin-action-btn btn-icon--close"
                        onClick={onClose}
                        aria-label="Close"
                        data-help-id="common-close"
                    />
                )}
            </div>
            <div className="room-modal-title-container" style={panelColor ? { ['--room-panel' as string]: panelColor } : undefined}>
                <h2 id="room-modal-title" className="room-modal-title wf-brand-font">
                    {room_name || category || `Room ${room_number}`}
                </h2>
            </div>
        </div>
    );
};
