import React from 'react';

interface RoomHeaderProps {
    room_number: string | null;
    room_name?: string;
    category?: string;
    panelColor?: string;
    onClose: () => void;
}

export const RoomHeader: React.FC<RoomHeaderProps> = ({ room_number, room_name, category, panelColor, onClose }) => {
    return (
        <div className="room-modal-header" style={{
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
            pointerEvents: 'none'
        }}>
            <div className="back-button-container" style={{ pointerEvents: 'auto', position: 'relative', top: 'unset', left: 'unset' }}>
                <button
                    type="button"
                    className="admin-action-btn btn-icon--close"
                    onClick={onClose}
                    aria-label="Close"
                    data-help-id="common-close"
                />
            </div>
            <div className="room-modal-title-container">
                <h2 id="room-modal-title" className="room-modal-title wf-brand-font">
                    {room_name || category || `Room ${room_number}`}
                </h2>
            </div>
        </div>
    );
};
