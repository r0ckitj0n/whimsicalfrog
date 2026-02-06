import React from 'react';
import { RoomHeader } from './RoomHeader.js';
import { RoomBody } from './RoomBody.js';
import { ItemHoverPopup } from './ItemHoverPopup.js';
import type { IRoomMetadata } from '../../../types/room.js';

interface IItemPopupState {
    visible: boolean;
    sku: string;
    name: string;
    price: string | number;
    image: string;
    stock: number;
    x: number;
    y: number;
}

interface RoomModalOverlayProps {
    isOpen: boolean;
    onClose: () => void;
    room_number: string | null;
    content: string;
    metadata: IRoomMetadata;
    bgStyle: React.CSSProperties;
    panelColor?: string;
    renderContext: 'modal' | 'fullscreen';
    targetAspectRatio?: number | string | null;
    isLoading?: boolean;
    onOpenItem?: (sku: string, data?: IItemPopupState) => void;
    is_bare: boolean;
    bodyRef: React.RefObject<HTMLDivElement>;
    ratio: number;
    popup: IItemPopupState | null;
    setPopup: (popup: IItemPopupState | null) => void;
}

export const RoomModalOverlay: React.FC<RoomModalOverlayProps> = ({
    isOpen,
    onClose,
    room_number,
    content,
    metadata,
    bgStyle,
    panelColor,
    renderContext,
    targetAspectRatio,
    isLoading,
    onOpenItem,
    is_bare,
    bodyRef,
    ratio,
    popup,
    setPopup
}) => {
    if (!isOpen) return null;

    return (
        <div
            id="wfReactRoomModalOverlay"
            className={`room-modal-overlay show ${is_bare ? 'bare-mode' : ''}`}
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: is_bare ? 1 : 'var(--wf-z-modal)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: is_bare ? 'transparent' : 'rgba(0, 0, 0, 0.85)',
                backdropFilter: is_bare ? 'none' : 'blur(8px)',
                width: is_bare ? '100%' : '100vw',
                height: is_bare ? '100%' : '100vh',
                padding: (is_bare || renderContext === 'fullscreen') ? '0' : '2.5vh 2.5vw',
                boxSizing: 'border-box',
                overflow: 'hidden'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <style>{`
                .room-item.sold-out img {
                    filter: grayscale(1) !important;
                    opacity: 0.7;
                }
                .room-item.sold-out:hover img {
                    filter: grayscale(0.5) !important;
                    opacity: 0.9;
                }
            `}</style>
            <div
                className={`room-modal-container ${is_bare ? 'bare-mode' : ''} context-${renderContext}`}
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    backgroundColor: is_bare ? 'transparent' : 'white',
                    borderRadius: (is_bare || renderContext === 'fullscreen') ? '0' : '12px',
                    overflow: 'hidden',
                    position: 'relative',
                    width: (is_bare || renderContext === 'fullscreen') ? '100%' : `min(95vw, calc(95vh * ${ratio}))`,
                    height: (is_bare || renderContext === 'fullscreen') ? '100%' : `min(95vh, calc(95vw / ${ratio}))`,
                    minHeight: (is_bare || renderContext === 'fullscreen') ? 'none' : 'min(400px, 95vh)',
                    boxShadow: (is_bare || renderContext === 'fullscreen') ? 'none' : '0 10px 40px rgba(0,0,0,0.5)',
                    opacity: isLoading ? 0.5 : 1,
                    transition: 'all 0.3s ease'
                }}
                onClick={e => e.stopPropagation()}
            >
                {!is_bare && (
                    <RoomHeader
                        room_number={room_number}
                        room_name={metadata.room_name}
                        category={metadata.category}
                        panelColor={panelColor}
                        onClose={onClose}
                    />
                )}
                <RoomBody
                    room_number={room_number}
                    bodyRef={bodyRef}
                    bgStyle={bgStyle}
                    content={content}
                    panelColor={panelColor}
                    renderContext={renderContext}
                    targetAspectRatio={targetAspectRatio}
                />
            </div>

            {popup && (
                <ItemHoverPopup
                    popup={popup}
                    onOpenItem={onOpenItem}
                    setPopup={setPopup}
                />
            )}
        </div>
    );
};
