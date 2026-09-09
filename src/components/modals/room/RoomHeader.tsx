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
                {/* Desktop / tablet: match Shop "Back to Main Room" button */}
                <button
                    type="button"
                    onClick={onClose}
                    className="hidden md:inline-flex px-6 py-2.5 text-[14px] font-merienda rounded-full bg-brand-primary text-white shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.3)] transition-all duration-300 hover:brightness-110 hover:scale-105 active:scale-95"
                    aria-label="Back to Main Room"
                    data-help-id="common-close"
                >
                    Back to Main Room
                </button>
                {/* Mobile: match Shop circular back control */}
                <button
                    type="button"
                    onClick={onClose}
                    className="md:hidden p-2.5 rounded-full bg-brand-primary text-white shadow-lg flex items-center justify-center"
                    aria-label="Back to Main Room"
                    data-help-id="common-close"
                >
                    <span className="btn-icon--back" style={{ fontSize: '20px' }} />
                </button>
            </div>
            <div className="room-modal-title-container">
                <h2 id="room-modal-title" className="room-modal-title wf-brand-font">
                    {room_name || category || `Room ${room_number}`}
                </h2>
            </div>
        </div>
    );
};
